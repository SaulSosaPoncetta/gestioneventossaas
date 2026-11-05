@extends('layouts.panel')

@section('title', 'Inicio')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Hola, {{ auth()->user()->name }}</h1>
        <span class="text-muted">Resumen de {{ $organizer->name }}</span>
    </div>
    @if ($currentRole !== 'door')
        <a href="{{ route('organizer.events.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo evento</a>
    @endif
</div>

{{-- Indicadores --}}
<div class="row g-3 mb-4">
    @php
        $cards = [
            ['Eventos', $stats['events_total'], 'bi-calendar-event', '#ede9fe', '#6d28d9'],
            ['Próximos publicados', $stats['events_upcoming'], 'bi-broadcast', '#dbeafe', '#1d4ed8'],
            ['Entradas vendidas', number_format($stats['tickets_sold'], 0, ',', '.'), 'bi-ticket-perforated', '#dcfce7', '#15803d'],
            ['Recaudación', '$ ' . number_format($stats['revenue'], 2, ',', '.'), 'bi-cash-coin', '#fef3c7', '#b45309'],
        ];
    @endphp
    @foreach ($cards as [$label, $value, $icon, $bg, $fg])
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: {{ $bg }}; color: {{ $fg }};"><i class="bi {{ $icon }}"></i></div>
                    <div>
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-5 fw-bold">{{ $value }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Pasos para empezar a vender --}}
@if (! $organizer->canSell())
    <div class="card p-4 mb-4">
        <h2 class="h6 fw-bold mb-3">Para empezar a vender</h2>
        <ol class="list-group list-group-numbered list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                Aprobación de la productora
                @if ($organizer->isActive())
                    <span class="badge bg-success">Listo</span>
                @else
                    <span class="badge bg-warning text-dark">En revisión</span>
                @endif
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                Vincular tu cuenta de Mercado Pago
                @if ($organizer->isMpConnected())
                    <span class="badge bg-success">Listo</span>
                @else
                    <span class="badge bg-secondary">Pendiente</span>
                @endif
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                Crear y publicar tu primer evento
                <span class="badge {{ $stats['events_total'] ? 'bg-success' : 'bg-secondary' }}">
                    {{ $stats['events_total'] ? 'Listo' : 'Pendiente' }}
                </span>
            </li>
        </ol>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <h2 class="h6 fw-bold mb-3">Próximos eventos</h2>
            @forelse ($nextEvents as $event)
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <a href="{{ route('organizer.events.show', $event) }}" class="fw-semibold text-decoration-none">{{ $event->title }}</a>
                        <div class="small text-muted">{{ $event->starts_at->translatedFormat('d/m/Y H:i') }} hs</div>
                    </div>
                    @php
                        [$stLabel, $stColor] = config('geventos.estados_evento.' . $event->status);
                    @endphp
                    <span class="badge text-bg-{{ $stColor }}">{{ $stLabel }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">Todavía no tenés eventos próximos.</p>
            @endforelse
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <h2 class="h6 fw-bold mb-3">Últimas ventas</h2>
            @forelse ($lastOrders as $order)
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="fw-semibold">{{ $order->buyer_name }}</div>
                        <div class="small text-muted">{{ $order->event->title }} · {{ $order->quantity }} entrada(s)</div>
                    </div>
                    <span class="fw-semibold">$ {{ number_format($order->total, 2, ',', '.') }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">Todavía no hay ventas.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
