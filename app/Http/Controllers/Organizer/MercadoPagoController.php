<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Services\MercadoPagoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class MercadoPagoController extends Controller
{
    public function __construct(private MercadoPagoService $mp)
    {
    }

    private function organizer(): Organizer
    {
        return app('currentOrganizer');
    }

    public function show(): View
    {
        $organizer = $this->organizer();

        return view('organizer.mercadopago', [
            'organizer'     => $organizer,
            'configured'    => $this->mp->isConfigured(),
            'sandbox'       => (bool) config('geventos.mercadopago.sandbox'),
            'isOAuth'       => $this->mp->isOAuth($organizer),
            'commission'    => $organizer->effectiveCommission(),
            'serviceFee'    => (float) config('geventos.cargo_servicio_porcentaje'),
        ]);
    }

    /** Envía a la productora a autorizar la plataforma en Mercado Pago */
    public function connect(Request $request): RedirectResponse
    {
        if (! $this->mp->isConfigured()) {
            return back()->with('error', 'La integración con Mercado Pago todavía no está configurada en la plataforma.');
        }

        $state = Str::random(40);

        $request->session()->put('mp_oauth', [
            'state'        => $state,
            'organizer_id' => $this->organizer()->id,
        ]);

        return redirect()->away($this->mp->authorizationUrl($state));
    }

    /** Vuelta desde Mercado Pago (ruta fuera del panel: /productora/mercadopago/callback) */
    public function callback(Request $request): RedirectResponse
    {
        $saved = $request->session()->pull('mp_oauth');
        $target = redirect()->route('organizer.mercadopago.show');

        if (! $saved || ! hash_equals($saved['state'], (string) $request->query('state'))) {
            return $target->with('error', 'La vinculación expiró o no es válida. Intentá de nuevo.');
        }

        $organizer = Organizer::find($saved['organizer_id']);

        if (! $organizer || ! $request->user()->canManageOrganizer($organizer)) {
            abort(403);
        }

        // La productora vuelve al panel con esa productora activa
        $request->session()->put('current_organizer_id', $organizer->id);

        if ($request->filled('error') || ! $request->filled('code')) {
            return $target->with('error', 'No se completó la autorización en Mercado Pago.');
        }

        try {
            $token = $this->mp->exchangeCode($request->query('code'));
            $this->mp->connectOrganizer($organizer, $token);
        } catch (Throwable $e) {
            Log::warning('MP OAuth: ' . $e->getMessage(), ['organizer' => $organizer->id]);

            return $target->with('error', 'No pudimos vincular la cuenta de Mercado Pago. Intentá de nuevo en unos minutos.');
        }

        return $target->with('success', '¡Listo! Tu cuenta de Mercado Pago quedó vinculada. Los pagos entran directo a tu cuenta.');
    }

    /** Solo en modo prueba: vincular pegando el access token de un usuario de prueba vendedor */
    public function manual(Request $request): RedirectResponse
    {
        abort_unless(config('geventos.mercadopago.sandbox'), 404);

        $data = $request->validate([
            'access_token' => ['required', 'string', 'regex:/^(TEST|APP_USR)-[\w-]+$/'],
        ], [
            'access_token.regex' => 'El access token debe empezar con TEST- o APP_USR-.',
        ]);

        try {
            $this->mp->connectManual($this->organizer(), $data['access_token']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cuenta de prueba vinculada. Ya podés probar compras.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->mp->disconnect($this->organizer());

        return back()->with('success', 'Se desvinculó la cuenta de Mercado Pago. La venta online queda deshabilitada hasta que vincules una.');
    }
}
