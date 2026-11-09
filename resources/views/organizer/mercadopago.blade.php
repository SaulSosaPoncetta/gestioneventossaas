@extends('layouts.panel')

@section('title', 'Mercado Pago')

@php
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') . '%';
@endphp

@section('content')
<div class="mb-4">
    <h1 class="h4 fw-bold mb-0">Cobros con Mercado Pago</h1>
    <span class="text-muted">Vinculá tu cuenta: cada venta se acredita directo en tu Mercado Pago</span>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card p-4">
            @if ($organizer->isMpConnected())
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon" style="background:#dcfce7;color:#15803d;"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="fw-bold">Cuenta vinculada</div>
                        <div class="small text-muted">
                            Usuario MP N° {{ $organizer->mp_user_id }} · desde el {{ $organizer->mp_connected_at?->format('d/m/Y H:i') }}
                            @unless ($isOAuth) · <span class="text-warning">vinculada manualmente (modo prueba)</span> @endunless
                        </div>
                    </div>
                </div>

                @if ($organizer->mp_token_expires_at)
                    <p class="small text-muted">La autorización se renueva automáticamente (vence el {{ $organizer->mp_token_expires_at->format('d/m/Y') }}).</p>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('organizer.mercadopago.connect') }}">
                        @csrf
                        <button class="btn btn-outline-primary" @disabled(! $configured)><i class="bi bi-arrow-repeat"></i> Vincular otra cuenta</button>
                    </form>
                    <form method="POST" action="{{ route('organizer.mercadopago.disconnect') }}"
                          onsubmit="return confirm('Si desvinculás la cuenta no se van a poder comprar entradas de tus eventos. ¿Continuar?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Desvincular</button>
                    </form>
                </div>
            @else
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon" style="background:#e0f2fe;color:#0369a1;"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="fw-bold">Todavía no vinculaste Mercado Pago</div>
                        <div class="small text-muted">Sin una cuenta vinculada tus eventos se ven, pero no se pueden comprar entradas.</div>
                    </div>
                </div>

                @if ($configured)
                    <form method="POST" action="{{ route('organizer.mercadopago.connect') }}">
                        @csrf
                        <button class="btn btn-primary btn-lg"><i class="bi bi-link-45deg"></i> Vincular con Mercado Pago</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Te vamos a llevar a Mercado Pago para que autorices a {{ config('geventos.nombre') }} a cobrar en tu nombre. No vemos tu clave ni tus datos de acceso.</p>
                @else
                    <div class="alert alert-warning mb-0">La integración con Mercado Pago todavía no está configurada en la plataforma (faltan MP_CLIENT_ID y MP_CLIENT_SECRET).</div>
                @endif
            @endif

            @if ($sandbox)
                <hr class="my-4">
                <h2 class="h6 fw-bold"><i class="bi bi-bug"></i> Modo prueba</h2>
                <p class="small text-muted">
                    En local Mercado Pago no puede volver a <code>localhost</code> por OAuth. Para probar compras podés pegar el
                    <strong>access token de un usuario de prueba vendedor</strong> (Mercado Pago Developers → Cuentas de prueba).
                    En este modo no se aplica la comisión de la plataforma.
                </p>
                <form method="POST" action="{{ route('organizer.mercadopago.manual') }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="access_token" class="form-control font-monospace @error('access_token') is-invalid @enderror" placeholder="APP_USR-... o TEST-...">
                    <button class="btn btn-outline-secondary text-nowrap">Vincular prueba</button>
                </form>
                @error('access_token')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card p-4">
            <h2 class="h6 fw-bold mb-3">¿Cómo se reparte cada venta?</h2>
            <ul class="small text-muted ps-3 mb-3">
                <li>El comprador paga el valor de las entradas más un <strong>cargo por servicio del {{ $pct($serviceFee) }}</strong>.</li>
                <li>El dinero se acredita en <strong>tu cuenta de Mercado Pago</strong>, con los plazos y costos de tu cuenta.</li>
                <li>
                    La plataforma retiene automáticamente el cargo por servicio
                    @if ($commission > 0)
                        y una <strong>comisión del {{ $pct($commission) }}</strong> sobre el valor de las entradas
                    @endif
                </li>
            </ul>
            <div class="bg-light rounded p-3 small">
                <div class="fw-semibold mb-1">Ejemplo con una entrada de $ 10.000</div>
                @php
                    $fee = 10000 * $serviceFee / 100;
                    $com = 10000 * $commission / 100;
                @endphp
                <div class="d-flex justify-content-between"><span>Paga el comprador</span><span>$ {{ number_format(10000 + $fee, 0, ',', '.') }}</span></div>
                <div class="d-flex justify-content-between"><span>Retiene la plataforma</span><span>$ {{ number_format($fee + $com, 0, ',', '.') }}</span></div>
                <div class="d-flex justify-content-between fw-semibold"><span>Recibís (antes de costos de MP)</span><span>$ {{ number_format(10000 - $com, 0, ',', '.') }}</span></div>
            </div>
        </div>
    </div>
</div>
@endsection
