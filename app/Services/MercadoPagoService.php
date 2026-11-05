<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Organizer;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Integración con Mercado Pago (modelo marketplace).
 *
 * - Cada productora vincula su cuenta por OAuth: el dinero entra directo a su cuenta.
 * - La plataforma retiene su parte con "marketplace_fee" en cada preferencia.
 */
class MercadoPagoService
{
    private const API = 'https://api.mercadopago.com';
    private const AUTH_URL = 'https://auth.mercadopago.com.ar/authorization';
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.vP';

    private function cfg(string $key): mixed
    {
        return config('geventos.mercadopago.' . $key);
    }

    public function isConfigured(): bool
    {
        return filled($this->cfg('client_id')) && filled($this->cfg('client_secret'));
    }

    /* ---------------------------------------------------------------- */
    /* OAuth de productoras                                              */
    /* ---------------------------------------------------------------- */

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $this->cfg('client_id'),
            'response_type' => 'code',
            'platform_id'   => 'mp',
            'state'         => $state,
            'redirect_uri'  => $this->cfg('redirect_uri'),
        ]);
    }

    /** Canjea el código de autorización por las credenciales de la productora */
    public function exchangeCode(string $code): array
    {
        $response = Http::acceptJson()->post(self::API . '/oauth/token', array_filter([
            'client_id'     => $this->cfg('client_id'),
            'client_secret' => $this->cfg('client_secret'),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->cfg('redirect_uri'),
            'test_token'    => $this->cfg('sandbox') ? 'true' : null,
        ]));

        return $this->json($response, 'No se pudo vincular la cuenta de Mercado Pago');
    }

    public function connectOrganizer(Organizer $organizer, array $token): void
    {
        $organizer->forceFill([
            'mp_user_id'          => (string) $token['user_id'],
            'mp_access_token'     => $token['access_token'],
            'mp_refresh_token'    => $token['refresh_token'] ?? null,
            'mp_public_key'       => $token['public_key'] ?? null,
            'mp_token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            'mp_connected_at'     => now(),
        ])->save();
    }

    /** Solo para pruebas: vincula pegando el access token de un usuario de prueba vendedor */
    public function connectManual(Organizer $organizer, string $accessToken): void
    {
        $me = $this->json(
            Http::withToken($accessToken)->acceptJson()->get(self::API . '/users/me'),
            'El access token no es válido'
        );

        $organizer->forceFill([
            'mp_user_id'          => (string) $me['id'],
            'mp_access_token'     => $accessToken,
            'mp_refresh_token'    => null,
            'mp_public_key'       => null,
            'mp_token_expires_at' => null,
            'mp_connected_at'     => now(),
        ])->save();
    }

    public function disconnect(Organizer $organizer): void
    {
        $organizer->forceFill([
            'mp_user_id'          => null,
            'mp_access_token'     => null,
            'mp_refresh_token'    => null,
            'mp_public_key'       => null,
            'mp_token_expires_at' => null,
            'mp_connected_at'     => null,
        ])->save();
    }

    /** Renueva el token OAuth (dura 180 días) */
    public function refresh(Organizer $organizer): void
    {
        $token = $this->json(Http::acceptJson()->post(self::API . '/oauth/token', [
            'client_id'     => $this->cfg('client_id'),
            'client_secret' => $this->cfg('client_secret'),
            'grant_type'    => 'refresh_token',
            'refresh_token' => $organizer->mp_refresh_token,
        ]), 'No se pudo renovar la vinculación con Mercado Pago');

        $this->connectOrganizer($organizer, $token + ['user_id' => $organizer->mp_user_id]);
    }

    /** Access token vigente de la productora (lo renueva si vence en menos de 7 días) */
    public function sellerToken(Organizer $organizer): string
    {
        if (! $organizer->isMpConnected()) {
            throw new RuntimeException('La productora no tiene Mercado Pago vinculado.');
        }

        if ($organizer->mp_refresh_token && $organizer->mp_token_expires_at
            && $organizer->mp_token_expires_at->copy()->subDays(7)->isPast()) {
            $this->refresh($organizer);
        }

        return $organizer->mp_access_token;
    }

    /** Vinculada por OAuth (y no a mano con un token de prueba) */
    public function isOAuth(Organizer $organizer): bool
    {
        return filled($organizer->mp_refresh_token);
    }

    /* ---------------------------------------------------------------- */
    /* Cobros                                                            */
    /* ---------------------------------------------------------------- */

    /** Crea la preferencia de Checkout Pro de una orden y devuelve [id, init_point] */
    public function createPreference(Order $order): array
    {
        $order->loadMissing(['event', 'organizer', 'items.ticketType', 'items.eventSeat.seat']);

        $event = $order->event;
        $organizer = $order->organizer;

        $detail = $order->items
            ->map(fn ($item) => $item->ticketType->name
                . ($item->eventSeat ? ' (' . $item->eventSeat->seat->displayName() . ')' : '')
                . ($item->quantity > 1 ? ' x' . $item->quantity : ''))
            ->implode(', ');

        $returnUrl = route('checkout.return', $order);
        $https = Str::startsWith($returnUrl, 'https://');

        $body = [
            'items' => [[
                'id'          => 'orden-' . $order->id,
                'title'       => Str::limit('Entradas · ' . $event->title, 250, ''),
                'description' => Str::limit($detail, 250),
                'category_id' => 'tickets',
                'quantity'    => 1,
                'currency_id' => 'ARS',
                'unit_price'  => round((float) $order->total, 2),
            ]],
            'payer' => array_filter([
                'name'           => $order->buyer_name,
                'email'          => $order->buyer_email,
                'identification' => $order->buyer_dni ? ['type' => 'DNI', 'number' => $order->buyer_dni] : null,
            ]),
            'external_reference'   => $order->uuid,
            'back_urls'            => [
                'success' => $returnUrl,
                'failure' => $returnUrl,
                'pending' => $returnUrl,
            ],
            // Aprobado o rechazado al instante: no hay pagos "pendientes" con entradas retenidas
            'binary_mode'          => true,
            'payment_methods'      => [
                'excluded_payment_types' => [['id' => 'ticket'], ['id' => 'atm']],
            ],
            'expires'              => true,
            'expiration_date_from' => now()->format(self::DATE_FORMAT),
            'expiration_date_to'   => $order->expires_at->format(self::DATE_FORMAT),
            'statement_descriptor' => Str::upper(Str::limit(Str::ascii($organizer->name), 13, '')),
            'metadata'             => ['order_id' => $order->id],
        ];

        // Mercado Pago solo acepta estos datos con URLs públicas https (en producción)
        if ($https) {
            $body['auto_return'] = 'approved';
            $body['notification_url'] = route('webhooks.mercadopago');
        }

        // Comisión de la plataforma (solo con cuentas vinculadas por OAuth)
        if ($this->isOAuth($organizer) && (float) $order->platform_fee > 0) {
            $body['marketplace_fee'] = round((float) $order->platform_fee, 2);
        }

        $pref = $this->json(
            Http::withToken($this->sellerToken($organizer))
                ->acceptJson()
                ->withHeaders(['X-Idempotency-Key' => $order->uuid . '-' . now()->timestamp])
                ->post(self::API . '/checkout/preferences', $body),
            'No se pudo iniciar el pago'
        );

        return ['id' => $pref['id'], 'init_point' => $pref['init_point']];
    }

    /** Consulta un pago con las credenciales de la productora */
    public function getPayment(Organizer $organizer, string $paymentId): array
    {
        return $this->json(
            Http::withToken($this->sellerToken($organizer))->acceptJson()->get(self::API . '/v1/payments/' . urlencode($paymentId)),
            'No se pudo consultar el pago'
        );
    }

    /** Pagos de una orden (para conciliar si no llegó el webhook) */
    public function searchPayments(Organizer $organizer, string $externalReference): array
    {
        $result = $this->json(
            Http::withToken($this->sellerToken($organizer))->acceptJson()->get(self::API . '/v1/payments/search', [
                'external_reference' => $externalReference,
                'sort'               => 'date_created',
                'criteria'           => 'desc',
            ]),
            'No se pudieron buscar los pagos'
        );

        return $result['results'] ?? [];
    }

    /** Devolución total de un pago */
    public function refund(Organizer $organizer, string $paymentId): array
    {
        return $this->json(
            Http::withToken($this->sellerToken($organizer))
                ->acceptJson()
                ->withHeaders(['X-Idempotency-Key' => 'refund-' . $paymentId])
                ->post(self::API . '/v1/payments/' . urlencode($paymentId) . '/refunds', (object) []),
            'No se pudo hacer la devolución'
        );
    }

    /* ---------------------------------------------------------------- */
    /* Webhooks                                                          */
    /* ---------------------------------------------------------------- */

    /**
     * Valida la firma "x-signature" de una notificación.
     * Si no hay clave configurada o la notificación no viene firmada se acepta:
     * de todas formas el pago siempre se vuelve a consultar en la API de Mercado Pago.
     */
    public function validSignature(Request $request, string $dataId): bool
    {
        $secret = $this->cfg('webhook_secret');
        $header = (string) $request->header('x-signature');

        if (! filled($secret) || $header === '') {
            return true;
        }

        $parts = [];
        foreach (explode(',', $header) as $chunk) {
            [$k, $v] = array_pad(array_map('trim', explode('=', $chunk, 2)), 2, null);
            if ($k !== null && $v !== null) {
                $parts[$k] = $v;
            }
        }

        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }

        $id = ctype_alnum($dataId) ? strtolower($dataId) : $dataId;
        $manifest = "id:{$id};request-id:{$request->header('x-request-id')};ts:{$parts['ts']};";

        return hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1']);
    }

    /* ---------------------------------------------------------------- */

    private function json(Response $response, string $error): array
    {
        if ($response->failed()) {
            $detail = $response->json('message') ?? $response->json('error') ?? $response->body();
            throw new RuntimeException($error . ': ' . Str::limit((string) $detail, 300));
        }

        return $response->json() ?? [];
    }
}
