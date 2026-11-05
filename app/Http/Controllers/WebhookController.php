<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Organizer;
use App\Models\WebhookEvent;
use App\Services\CheckoutService;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Notificaciones de Mercado Pago (POST /webhooks/mercadopago).
 *
 * Nunca se confía en el contenido de la notificación: se toma el id del pago
 * y se lo consulta en la API con las credenciales de la productora.
 */
class WebhookController extends Controller
{
    public function __construct(
        private MercadoPagoService $mp,
        private CheckoutService $checkout,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // Formato webhook: {"type":"payment","data":{"id":"123"}} · Formato IPN: ?topic=payment&id=123
        $type = $request->input('type') ?? $request->query('topic');
        $dataId = (string) ($request->input('data.id') ?? $request->query('data_id') ?? $request->query('id') ?? '');

        if (! $type || $dataId === '') {
            return response('ok', 200);
        }

        if (! $this->mp->validSignature($request, $dataId)) {
            Log::warning('Webhook MP con firma inválida', ['data_id' => $dataId]);

            return response('invalid signature', 401);
        }

        // Solo interesan los pagos (merchant_order y otros se ignoran)
        if ($type !== 'payment') {
            return response('ok', 200);
        }

        $requestId = $request->header('x-request-id')
            ?: $type . '-' . $dataId . '-' . ($request->input('action') ?? 'ipn') . '-' . now()->format('YmdHi');

        $log = WebhookEvent::firstOrCreate(
            ['provider' => 'mercadopago', 'request_id' => $requestId],
            ['topic' => $type, 'external_id' => $dataId, 'payload' => $request->all()]
        );

        if ($log->processed_at) {
            return response('ok', 200);
        }

        try {
            $this->process($dataId, $request->input('user_id'));
            $log->markProcessed();
        } catch (Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error('Webhook MP: ' . $e->getMessage(), ['data_id' => $dataId]);

            // Un error devuelve 500 para que Mercado Pago reintente
            return response('error', 500);
        }

        return response('ok', 200);
    }

    private function process(string $paymentId, mixed $sellerUserId): void
    {
        $organizer = $sellerUserId
            ? Organizer::where('mp_user_id', (string) $sellerUserId)->first()
            : null;

        if (! $organizer) {
            throw new RuntimeException("No hay una productora vinculada a la cuenta de Mercado Pago {$sellerUserId}.");
        }

        $payment = $this->mp->getPayment($organizer, $paymentId);

        $order = Order::where('uuid', $payment['external_reference'] ?? '')
            ->where('organizer_id', $organizer->id)
            ->first();

        // Pagos de la misma cuenta que no son de esta plataforma: se ignoran
        if (! $order) {
            return;
        }

        $this->checkout->applyPayment($order, $payment);
    }
}
