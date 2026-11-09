@extends('layouts.app')

@section('title', 'Mis compras')

@php
    $statuses = [
        'pending'  => ['Pendiente de pago', 'warning'],
        'paid'     => ['Pagada', 'success'],
        'refunded' => ['Anulada', 'danger'],
    ];
@endphp

@section('content')
<div class="container" style="max-width: 920px;">
    <h1 class="h4 fw-bold mb-4">Mis compras</h1>

    @forelse ($orders as $order)
        @php [$label, $color] = $statuses[$order->status] ?? [$order->status, 'secondary']; @endphp
        <a href="{{ route('checkout.show', $order) }}" class="card border-0 shadow-sm p-3 mb-3 text-decoration-none text-reset">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-bold">{{ $order->event->title }}</div>
                    <div class="small text-muted">
                        <i class="bi bi-calendar3"></i> {{ $order->event->starts_at->format('d/m/Y H:i') }} hs
                        · <i class="bi bi-geo-alt"></i> {{ $order->event->venue?->name ?? 'A confirmar' }}
                    </div>
                    <div class="small text-muted">
                        Compra #{{ strtoupper(substr($order->uuid, 0, 8)) }} · {{ $order->quantity }} entrada(s)
                        · $ {{ number_format((float) $order->total, 2, ',', '.') }}
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge text-bg-{{ $color }}">{{ $label }}</span>
                    @if ($order->event->starts_at->isFuture() && $order->isPaid())
                        <div class="small text-primary mt-1">Próximo evento</div>
                    @endif
                </div>
            </div>
        </a>
    @empty
        <div class="card border-0 shadow-sm p-5 text-center text-muted">
            <i class="bi bi-ticket-perforated fs-1 mb-2"></i>
            Todavía no hiciste compras con esta cuenta.
            <div class="small mt-2">Si compraste sin iniciar sesión, tus entradas están en el mail de la compra.</div>
        </div>
    @endforelse

    <div class="mt-3">{{ $orders->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
