@extends('layouts.panel')

@section('title', 'Butacas · ' . $event->title)

@php
    $legend = [
        'available' => ['Libre', null],
        'blocked'   => ['Bloqueada', '#475569'],
        'held'      => ['En proceso de pago', '#f97316'],
        'sold'      => ['Vendida', '#dc2626'],
    ];
@endphp

@push('styles')
<style>
    .map-wrap { background: #fff; border: 1px solid #eee; border-radius: .75rem; overflow: auto; max-height: 72vh; min-height: 320px; padding: .5rem; }
    .legend-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; vertical-align: -1px; margin-right: 4px; }
</style>
@endpush

@section('content')
<div class="mb-3">
    <a href="{{ route('organizer.events.show', $event) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> {{ $event->title }}</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0">Butacas del evento</h1>
        <span class="text-muted">{{ $event->venue->name }} · {{ $event->starts_at->format('d/m/Y H:i') }}</span>
    </div>
    <form method="POST" action="{{ route('organizer.events.seats.update', $event) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="action" value="sync">
        <button class="btn btn-outline-secondary" title="Vuelve a tomar las butacas del mapa del lugar y los tipos de entrada">
            <i class="bi bi-arrow-repeat"></i> Sincronizar con el mapa
        </button>
    </form>
</div>

@unless ($synced)
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i>
        Las butacas se habilitan al <strong>publicar</strong> el evento (según los sectores numerados asociados a cada tipo de entrada).
        También podés usar "Sincronizar con el mapa" para prepararlas antes.
    </div>
@endunless

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3 mb-3">
    @foreach ($legend as $status => [$label])
        <div class="col-6 col-lg-3">
            <div class="card p-3">
                <div class="text-muted small">{{ $label }}</div>
                <div class="fs-5 fw-bold">{{ number_format($counts[$status] ?? 0, 0, ',', '.') }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="card p-3">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" id="zoomOut"><i class="bi bi-zoom-out"></i></button>
            <button type="button" class="btn btn-outline-secondary" id="zoomFit"><i class="bi bi-arrows-angle-contract"></i></button>
            <button type="button" class="btn btn-outline-secondary" id="zoomIn"><i class="bi bi-zoom-in"></i></button>
        </div>
        <span class="small text-muted ms-2"><span id="selCount">0</span> seleccionadas</span>
        <button type="button" class="btn btn-sm btn-link" id="selClear">Limpiar</button>

        <form method="POST" action="{{ route('organizer.events.seats.update', $event) }}" id="bulkForm" class="ms-auto d-flex gap-1">
            @csrf
            @method('PATCH')
            <div id="bulkIds"></div>
            <button name="action" value="block" class="btn btn-sm btn-dark bulk-btn" disabled><i class="bi bi-lock"></i> Bloquear</button>
            <button name="action" value="release" class="btn btn-sm btn-outline-success bulk-btn" disabled><i class="bi bi-unlock"></i> Liberar</button>
        </form>
    </div>

    <div class="map-wrap" id="seatMap"></div>

    <div class="d-flex flex-wrap gap-3 small text-muted mt-2">
        <span><span class="legend-dot" style="background:#6d28d9"></span>Libre (color del sector)</span>
        @foreach (['blocked', 'held', 'sold'] as $st)
            <span><span class="legend-dot" style="background:{{ $legend[$st][1] }}"></span>{{ $legend[$st][0] }}</span>
        @endforeach
        <span><span class="legend-dot" style="border:1px dashed #94a3b8"></span>No se vende en este evento</span>
    </div>
    <div class="small text-muted mt-2">
        Bloqueá butacas para invitaciones, prensa, técnica o visión reducida: no se ofrecen a la venta. Las vendidas o en pago no se pueden tocar.
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/seatmap.js') }}?v=2"></script>
<script>
    (function () {
        const colors = { blocked: '#475569', held: '#f97316', sold: '#dc2626' };

        const map = new SeatMap('#seatMap', @json($mapData), {
            mode: 'edit',
            seatColor: (seat) => colors[seat.st] || null,
            onChange: (seats) => {
                document.getElementById('selCount').textContent = seats.length;
                document.querySelectorAll('.bulk-btn').forEach((b) => b.disabled = seats.length === 0);
            },
        });

        document.getElementById('zoomIn').addEventListener('click', () => map.zoomIn());
        document.getElementById('zoomOut').addEventListener('click', () => map.zoomOut());
        document.getElementById('zoomFit').addEventListener('click', () => map.fit());
        document.getElementById('selClear').addEventListener('click', () => map.clear());

        document.getElementById('bulkForm').addEventListener('submit', (e) => {
            const ids = map.getSelected();
            if (!ids.length) { e.preventDefault(); return; }
            const box = document.getElementById('bulkIds');
            box.innerHTML = '';
            ids.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'seat_ids[]';
                input.value = id;
                box.appendChild(input);
            });
        });
    })();
</script>
@endpush
