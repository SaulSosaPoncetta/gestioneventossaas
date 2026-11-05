@extends('layouts.panel')

@section('title', $event->title)

@php
    [$stLabel, $stColor] = config('geventos.estados_evento.' . $event->status);
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
@endphp

@section('content')
<div class="mb-3">
    <a href="{{ route('organizer.events.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Eventos</a>
</div>

{{-- Encabezado --}}
<div class="card p-4 mb-4">
    <div class="row g-4 align-items-center">
        <div class="col-md-3">
            @if ($event->cover_path)
                <img src="{{ asset('storage/' . $event->cover_path) }}" alt="" class="img-fluid rounded" style="aspect-ratio: 16/9; object-fit: cover; width: 100%;">
            @else
                <div class="rounded bg-light d-grid text-muted" style="aspect-ratio: 16/9; place-items: center;">
                    <i class="bi bi-image fs-1"></i>
                </div>
            @endif
        </div>
        <div class="col-md-9">
            <div class="d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <span class="badge text-bg-{{ $stColor }} mb-2">{{ $stLabel }}</span>
                    <h1 class="h4 fw-bold mb-1">{{ $event->title }}</h1>
                    <div class="text-muted">
                        <i class="bi bi-calendar3"></i> {{ $event->starts_at->translatedFormat('l d/m/Y') }} · {{ $event->starts_at->format('H:i') }} hs
                        @if ($event->doors_open_at) <span class="ms-2"><i class="bi bi-door-open"></i> Puertas {{ $event->doors_open_at->format('H:i') }}</span> @endif
                    </div>
                    <div class="text-muted">
                        <i class="bi bi-geo-alt"></i> {{ $event->venue?->name ?? 'Lugar a definir' }}
                        @if ($event->venue?->fullAddress()) <span class="small">— {{ $event->venue->fullAddress() }}</span> @endif
                    </div>
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        @if ($event->category)
                            <span class="badge text-bg-light border">{{ config('geventos.categorias.' . $event->category) }}</span>
                        @endif
                        @if ($event->is_seated) <span class="badge text-bg-light border"><i class="bi bi-grid-3x3-gap"></i> Numerado</span> @endif
                        @if ($event->is_nominative) <span class="badge text-bg-light border"><i class="bi bi-person-badge"></i> Nominativo</span> @endif
                        @if ($event->allow_transfers) <span class="badge text-bg-light border"><i class="bi bi-arrow-left-right"></i> Transferible</span> @endif
                        <span class="badge text-bg-light border">Máx. {{ $event->max_tickets_per_order }} por compra</span>
                    </div>
                </div>

                {{-- Acciones --}}
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <a href="{{ route('organizer.events.edit', $event) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>

                    @php
                        $actions = [];
                        if (in_array($event->status, ['draft', 'paused'], true)) $actions['publish'] = ['Publicar', 'btn-success', 'bi-broadcast', null];
                        if ($event->status === 'published') $actions['pause'] = ['Pausar venta', 'btn-warning', 'bi-pause-circle', null];
                        if (in_array($event->status, ['published', 'paused'], true) && $stats['orders'] === 0) $actions['draft'] = ['A borrador', 'btn-outline-secondary', 'bi-pencil-square', null];
                        if (! in_array($event->status, ['cancelled', 'finished'], true)) $actions['cancel'] = ['Cancelar evento', 'btn-outline-danger', 'bi-x-circle', '¿Seguro que querés cancelar el evento? Esta acción no se puede deshacer.'];
                    @endphp

                    @foreach ($actions as $action => [$label, $btn, $icon, $confirm])
                        <form method="POST" action="{{ route('organizer.events.status', $event) }}"
                              @if ($confirm) onsubmit="return confirm(@js($confirm))" @endif>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="{{ $action }}">
                            <button class="btn {{ $btn }}"><i class="bi {{ $icon }}"></i> {{ $label }}</button>
                        </form>
                    @endforeach

                    @if ($stats['orders'] === 0 && $event->orders()->doesntExist())
                        <form method="POST" action="{{ route('organizer.events.destroy', $event) }}"
                              onsubmit="return confirm('¿Eliminar definitivamente este evento?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Indicadores --}}
<div class="row g-3 mb-4">
    @foreach ([
        ['Entradas vendidas', number_format($stats['sold'], 0, ',', '.'), 'bi-ticket-perforated'],
        ['Órdenes pagas', number_format($stats['orders'], 0, ',', '.'), 'bi-receipt'],
        ['Recaudación', $money($stats['revenue']), 'bi-cash-coin'],
        ['Ingresaron', number_format($stats['checked_in'], 0, ',', '.') . ($stats['sold'] ? ' (' . round($stats['checked_in'] * 100 / $stats['sold']) . '%)' : ''), 'bi-qr-code-scan'],
    ] as [$label, $value, $icon])
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 h-100">
                <div class="text-muted small"><i class="bi {{ $icon }}"></i> {{ $label }}</div>
                <div class="fs-5 fw-bold">{{ $value }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    {{-- Entradas --}}
    <div class="col-lg-8">
        <div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">Tipos de entrada y lotes</h2>
                <button class="btn btn-sm btn-primary" disabled title="Disponible en el próximo paso"><i class="bi bi-plus-lg"></i> Agregar</button>
            </div>

            @forelse ($event->ticketTypes as $type)
                <div class="border rounded p-3 mb-2">
                    <div class="fw-semibold">{{ $type->name }}</div>
                    @foreach ($type->lots as $lot)
                        <div class="d-flex justify-content-between small text-muted">
                            <span>{{ $lot->name }}</span>
                            <span>{{ $money($lot->price) }} · {{ $lot->sold }}/{{ $lot->quantity ?? '∞' }}</span>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="text-center text-muted py-4">
                    <i class="bi bi-ticket-perforated fs-2 d-block mb-2"></i>
                    Todavía no hay tipos de entrada. Vas a poder cargarlos (General, VIP, preventas...) en el próximo paso.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Datos de venta --}}
    <div class="col-lg-4">
        <div class="card p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Venta</h2>
            <dl class="row small mb-0">
                <dt class="col-6 text-muted fw-normal">Inicio de venta</dt>
                <dd class="col-6 text-end">{{ $event->sales_start_at?->format('d/m/Y H:i') ?? 'Al publicar' }}</dd>
                <dt class="col-6 text-muted fw-normal">Fin de venta</dt>
                <dd class="col-6 text-end">{{ $event->sales_end_at?->format('d/m/Y H:i') ?? 'Hasta el inicio' }}</dd>
                <dt class="col-6 text-muted fw-normal">Cargo por servicio</dt>
                <dd class="col-6 text-end">{{ rtrim(rtrim(number_format($event->effectiveServiceFee(), 2, ',', ''), '0'), ',') }}%</dd>
                <dt class="col-6 text-muted fw-normal">Capacidad</dt>
                <dd class="col-6 text-end">{{ $event->capacity ? number_format($event->capacity, 0, ',', '.') : '—' }}</dd>
                <dt class="col-6 text-muted fw-normal">A la venta ahora</dt>
                <dd class="col-6 text-end mb-0">
                    @if ($event->isOnSale())
                        <span class="text-success fw-semibold">Sí</span>
                    @else
                        <span class="text-muted">No</span>
                    @endif
                </dd>
            </dl>
        </div>

        @if ($event->summary)
            <div class="card p-4">
                <h2 class="h6 fw-bold mb-2">Resumen</h2>
                <p class="small text-muted mb-0">{{ $event->summary }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
