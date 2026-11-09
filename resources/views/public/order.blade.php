@extends('layouts.app')

@section('title', 'Tu compra')

@php
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
    $statuses = [
        'pending'   => ['Pendiente de pago', 'warning', 'bi-hourglass-split'],
        'paid'      => ['Pagada', 'success', 'bi-check-circle-fill'],
        'expired'   => ['Reserva vencida', 'secondary', 'bi-clock-history'],
        'cancelled' => ['Cancelada', 'secondary', 'bi-x-circle'],
        'refunded'  => ['Anulada / reintegrada', 'danger', 'bi-arrow-counterclockwise'],
    ];
    [$stLabel, $stColor, $stIcon] = $statuses[$order->status] ?? [$order->status, 'secondary', 'bi-circle'];
    $canPay = $order->isPending() && ! $order->reservationExpired();
    $missingAttendees = $order->event->is_nominative
        && $tickets->contains(fn ($t) => ! $t->isTransferredAway() && (empty($t->attendee_name) || empty($t->attendee_dni)));
@endphp

@push('styles')
<style>
    .ticket-card { border: 2px solid #ede9fe; border-radius: 1rem; overflow: hidden; }
    .ticket-card .qr img { width: 100%; max-width: 220px; height: auto; }
    .ticket-card.is-used { opacity: .6; }
    .ticket-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .2em; font-weight: 700; }
</style>
@endpush

@section('content')
<div class="container" style="max-width: 920px;">
    <div class="card border-0 shadow-sm p-4 p-md-5 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <div class="small text-muted">Compra #{{ strtoupper(substr($order->uuid, 0, 8)) }}</div>
                <h1 class="h4 fw-bold mb-1">{{ $order->event->title }}</h1>
                <div class="text-muted small">
                    <i class="bi bi-calendar3"></i> {{ $order->event->starts_at->format('d/m/Y H:i') }} hs
                    · <i class="bi bi-geo-alt"></i> {{ $order->event->venue?->name ?? 'Lugar a confirmar' }}
                </div>
            </div>
            <span class="badge text-bg-{{ $stColor }} fs-6"><i class="bi {{ $stIcon }}"></i> {{ $stLabel }}</span>
        </div>

        {{-- Estado --}}
        @if ($canPay)
            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <i class="bi bi-hourglass-split"></i>
                    Tus entradas están reservadas. Te quedan <strong id="countdown">--:--</strong> para completar el pago.
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('checkout.pay', $order) }}">
                        @csrf
                        <button class="btn btn-primary"><i class="bi bi-credit-card"></i> Pagar {{ $money($order->total) }}</button>
                    </form>
                    <form method="POST" action="{{ route('checkout.cancel', $order) }}" onsubmit="return confirm('¿Cancelar la compra y liberar las entradas?')">
                        @csrf
                        <button class="btn btn-outline-secondary">Cancelar</button>
                    </form>
                </div>
            </div>
        @elseif ($order->isPaid())
            <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-3 mb-0">
                <div>
                    <i class="bi bi-check-circle-fill"></i>
                    ¡Compra confirmada! Te enviamos las entradas a <strong>{{ $order->buyer_email }}</strong>.
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('checkout.pdf', $order) }}" class="btn btn-success"><i class="bi bi-file-earmark-pdf"></i> Descargar PDF</a>
                    <form method="POST" action="{{ route('checkout.resend', $order) }}">
                        @csrf
                        <button class="btn btn-outline-success"><i class="bi bi-envelope"></i> Reenviar por mail</button>
                    </form>
                    @if ($order->event->is_nominative || $order->event->allow_transfers)
                        <a href="{{ route('checkout.attendees', $order) }}" class="btn btn-outline-primary"><i class="bi bi-people"></i> Asistentes y transferencias</a>
                    @endif
                </div>
            </div>
        @elseif ($order->status === 'refunded')
            <div class="alert alert-danger mb-0">
                <i class="bi bi-arrow-counterclockwise"></i>
                Esta compra fue anulada y sus entradas ya no son válidas. Si pagaste, el reintegro se acredita en el mismo medio de pago.
            </div>
        @elseif (in_array($order->status, ['expired', 'cancelled'], true))
            <div class="alert alert-secondary d-flex flex-wrap justify-content-between align-items-center gap-2 mb-0">
                <span>Esta compra no se completó y las entradas se liberaron.</span>
                <a href="{{ route('events.show', $order->event) }}" class="btn btn-sm btn-primary">Volver a comprar</a>
            </div>
        @endif
    </div>

    {{-- Entradas --}}
    @if ($tickets->isNotEmpty())
        <h2 class="h5 fw-bold mb-3">Tus entradas</h2>

        @if ($missingAttendees)
            <div class="alert alert-warning">
                <i class="bi bi-person-badge"></i>
                Las entradas de este evento son <strong>nominativas</strong>: completá el nombre y DNI de cada asistente antes del evento.
                <a href="{{ route('checkout.attendees', $order) }}" class="alert-link">Completar ahora</a>
            </div>
        @endif

        <div class="row g-3 mb-4">
            @foreach ($tickets as $ticket)
                @if ($ticket->isTransferredAway())
                    <div class="col-md-6">
                        <div class="ticket-card bg-light h-100 p-3 d-flex align-items-center gap-3">
                            <i class="bi bi-send-check fs-1 text-info"></i>
                            <div>
                                <div class="fw-bold">{{ $ticket->ticketType->name }} <span class="small text-muted font-monospace">{{ $ticket->code }}</span></div>
                                @if ($ticket->eventSeat)
                                    <div class="small">{{ $ticket->eventSeat->seat->displayName() }}</div>
                                @endif
                                <div class="small text-muted">Transferida a {{ $ticket->attendee_name }} ({{ $ticket->attendee_email }}). Tu QR anterior quedó anulado.</div>
                            </div>
                        </div>
                    </div>
                    @continue
                @endif
                <div class="col-md-6">
                    <div class="ticket-card bg-white h-100 {{ $ticket->isUsed() ? 'is-used' : '' }}">
                        <div class="p-3 d-flex gap-3 align-items-center">
                            <div class="qr text-center" style="width: 45%;">
                                <img src="{{ $qrs[$ticket->id] }}" alt="QR de la entrada {{ $ticket->code }}">
                                <div class="ticket-code small mt-1">{{ $ticket->code }}</div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">{{ $ticket->ticketType->name }}</div>
                                @if ($ticket->eventSeat)
                                    <div class="text-primary fw-semibold">
                                        {{ $ticket->eventSeat->seat->section->name }}<br>
                                        Fila {{ $ticket->eventSeat->seat->row_label }} · Butaca {{ $ticket->eventSeat->seat->number }}
                                    </div>
                                @endif
                                <div class="small mt-2">
                                    @if ($ticket->attendee_name)
                                        <i class="bi bi-person"></i> {{ $ticket->attendee_name }}
                                        @if ($ticket->attendee_dni)<br><span class="text-muted">DNI {{ $ticket->attendee_dni }}</span>@endif
                                    @else
                                        <span class="text-warning"><i class="bi bi-exclamation-circle"></i> Asistente sin asignar</span>
                                    @endif
                                </div>
                                <div class="mt-2">
                                    @if ($ticket->isUsed())
                                        <span class="badge text-bg-secondary">Usada {{ $ticket->checked_in_at?->format('d/m H:i') }}</span>
                                    @else
                                        <span class="badge text-bg-success">Válida</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @elseif ($order->isPaid())
        <div class="alert alert-info">Estamos generando tus entradas. Actualizá la página en unos segundos.</div>
    @endif

    {{-- Detalle --}}
    <div class="card border-0 shadow-sm p-4 p-md-5">
        <h2 class="h6 fw-bold">Detalle de la compra</h2>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="small text-muted">
                    <tr><th>Entrada</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>
                                {{ $item->ticketType->name }}
                                @if ($item->eventSeat)
                                    <div class="small text-muted"><i class="bi bi-grid-3x3-gap"></i> {{ $item->eventSeat->seat->displayName() }}</div>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-end">{{ $money($item->unit_price) }}</td>
                            <td class="text-end">{{ $money($item->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="small">
                    <tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end">{{ $money($order->subtotal) }}</td></tr>
                    @if ((float) $order->discount > 0)
                        <tr class="text-success"><td colspan="3" class="text-end">Descuento</td><td class="text-end">- {{ $money($order->discount) }}</td></tr>
                    @endif
                    <tr><td colspan="3" class="text-end">Cargo por servicio</td><td class="text-end">{{ $money($order->service_fee) }}</td></tr>
                    <tr class="fs-6 fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end">{{ $money($order->total) }}</td></tr>
                </tfoot>
            </table>
        </div>

        <div class="row small text-muted g-2">
            <div class="col-sm-6"><i class="bi bi-person"></i> {{ $order->buyer_name }} · DNI {{ $order->buyer_dni }}</div>
            <div class="col-sm-6 text-sm-end"><i class="bi bi-envelope"></i> {{ $order->buyer_email }}</div>
            @if ($order->paid_at)
                <div class="col-12"><i class="bi bi-credit-card"></i> Pagado el {{ $order->paid_at->format('d/m/Y H:i') }} @if ($order->mp_payment_id) · Operación Mercado Pago N° {{ $order->mp_payment_id }} @endif</div>
            @endif
        </div>

        <div class="text-muted small mt-4">
            Guardá este enlace para volver a ver tu compra: <span class="user-select-all">{{ route('checkout.show', $order) }}</span>
        </div>
    </div>
</div>
@endsection

@if ($canPay)
@push('scripts')
<script>
    (function () {
        const expires = new Date(@json($order->expires_at->toIso8601String())).getTime();
        const el = document.getElementById('countdown');
        const tick = () => {
            const left = Math.max(0, Math.floor((expires - Date.now()) / 1000));
            el.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
            if (left === 0) { clearInterval(timer); setTimeout(() => location.reload(), 1500); }
        };
        const timer = setInterval(tick, 1000);
        tick();
    })();
</script>
@endpush
@endif
