@extends('layouts.panel')

@section('title', 'Compra ' . strtoupper(substr($order->uuid, 0, 8)))

@php
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
    $statuses = [
        'paid'      => ['Pagada', 'success'],
        'pending'   => ['Pendiente', 'warning'],
        'refunded'  => ['Devuelta', 'danger'],
        'expired'   => ['Vencida', 'secondary'],
        'cancelled' => ['Cancelada', 'secondary'],
    ];
    [$label, $color] = $statuses[$order->status] ?? [$order->status, 'secondary'];
    $canRefund = in_array($currentRole, ['owner', 'admin'], true) && $order->isPaid();
@endphp

@section('content')
<div class="mb-3">
    <a href="{{ route('organizer.orders.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Órdenes</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Compra #{{ strtoupper(substr($order->uuid, 0, 8)) }} <span class="badge text-bg-{{ $color }} fs-6 align-middle">{{ $label }}</span></h1>
        <div class="text-muted">{{ $order->event->title }} · {{ $order->event->starts_at->format('d/m/Y H:i') }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('checkout.show', $order) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Ver como comprador</a>
        @if ($order->isPaid())
            <form method="POST" action="{{ route('organizer.orders.resend', $order) }}">
                @csrf
                <button class="btn btn-outline-primary"><i class="bi bi-envelope"></i> Reenviar entradas</button>
            </form>
        @endif
        @if ($canRefund)
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#refundModal"><i class="bi bi-arrow-counterclockwise"></i> Devolver compra</button>
        @endif
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Entradas --}}
        <div class="card mb-4">
            <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Entradas</h2></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light small"><tr><th>Código</th><th>Tipo / ubicación</th><th>Asistente</th><th class="text-center">Estado</th></tr></thead>
                    <tbody class="small">
                        @forelse ($order->tickets as $t)
                            <tr>
                                <td class="font-monospace">{{ $t->code }} @if ($t->version > 1)<span class="badge text-bg-light border" title="Transferida">v{{ $t->version }}</span>@endif</td>
                                <td>{{ $t->ticketType->name }} @if ($t->eventSeat)<div class="text-muted">{{ $t->eventSeat->seat->displayName() }}</div>@endif</td>
                                <td>
                                    {{ $t->attendee_name ?? '—' }}
                                    @if ($t->attendee_dni)<div class="text-muted">DNI {{ $t->attendee_dni }}</div>@endif
                                    @if ($t->transfers->isNotEmpty())<div class="text-info">{{ $t->transfers->count() }} transferencia(s)</div>@endif
                                </td>
                                <td class="text-center">
                                    @if ($t->status === 'used')
                                        <span class="badge text-bg-secondary">Ingresó {{ $t->checked_in_at?->format('d/m H:i') }}</span>
                                    @elseif ($t->status === 'cancelled')
                                        <span class="badge text-bg-danger">Anulada</span>
                                    @else
                                        <span class="badge text-bg-success">Válida</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">Sin entradas emitidas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagos --}}
        <div class="card">
            <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Pagos en Mercado Pago</h2></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light small"><tr><th>N° de pago</th><th>Estado</th><th>Medio</th><th class="text-end">Monto</th><th class="text-end">Acreditado</th><th>Fecha</th></tr></thead>
                    <tbody class="small">
                        @forelse ($order->payments as $p)
                            <tr>
                                <td class="font-monospace">{{ $p->mp_payment_id }}</td>
                                <td>{{ $p->status }} @if ($p->status_detail)<div class="text-muted">{{ $p->status_detail }}</div>@endif</td>
                                <td>{{ $p->payment_method ?? '—' }}</td>
                                <td class="text-end">{{ $money($p->amount) }}</td>
                                <td class="text-end">{{ $p->net_amount !== null ? $money($p->net_amount) : '—' }}</td>
                                <td>{{ ($p->approved_at ?? $p->created_at)->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">Sin pagos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Comprador</h2>
            <div class="fw-semibold">{{ $order->buyer_name }}</div>
            <div class="small text-muted">
                {{ $order->buyer_email }}<br>
                @if ($order->buyer_dni) DNI {{ $order->buyer_dni }}<br> @endif
                @if ($order->buyer_phone) Tel. {{ $order->buyer_phone }}<br> @endif
                Compra: {{ $order->created_at->format('d/m/Y H:i') }}
                @if ($order->paid_at) · Pago: {{ $order->paid_at->format('d/m/Y H:i') }} @endif
            </div>
        </div>

        <div class="card p-4">
            <h2 class="h6 fw-bold mb-3">Importes</h2>
            <dl class="row small mb-0">
                <dt class="col-7 fw-normal text-muted">Entradas</dt><dd class="col-5 text-end">{{ $money($order->subtotal) }}</dd>
                @if ((float) $order->discount > 0)
                    <dt class="col-7 fw-normal text-muted">Descuento @if ($order->coupon)<code>{{ $order->coupon->code }}</code>@endif</dt>
                    <dd class="col-5 text-end text-success">- {{ $money($order->discount) }}</dd>
                @endif
                <dt class="col-7 fw-normal text-muted">Cargo por servicio</dt><dd class="col-5 text-end">{{ $money($order->service_fee) }}</dd>
                <dt class="col-7">Pagó el comprador</dt><dd class="col-5 text-end fw-bold">{{ $money($order->total) }}</dd>
                <dt class="col-7 fw-normal text-muted">Retención plataforma</dt><dd class="col-5 text-end text-danger">- {{ $money($order->platform_fee) }}</dd>
                <dt class="col-7">Neto productora</dt><dd class="col-5 text-end fw-bold mb-0">{{ $money($order->organizerAmount()) }}</dd>
            </dl>
            <div class="small text-muted mt-2">El neto es antes de los costos propios de tu cuenta de Mercado Pago.</div>
        </div>
    </div>
</div>

@if ($canRefund)
    <div class="modal fade" id="refundModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('organizer.orders.refund', $order) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Devolver la compra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        Se devuelven <strong>{{ $money($order->total) }}</strong> desde tu cuenta de Mercado Pago, se anulan las
                        {{ $order->quantity }} entrada(s) y el cupo vuelve a la venta. No se puede deshacer.
                    </div>
                    <label class="form-label small">Motivo (se le informa al comprador)</label>
                    <textarea name="reason" class="form-control" rows="2" maxlength="300" placeholder="Opcional"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger">Devolver</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
