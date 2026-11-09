@extends('layouts.panel')

@section('title', 'Ingresos · ' . $event->title)

@php
    $pct = $stats['total'] ? round($stats['used'] * 100 / $stats['total']) : 0;
    $results = [
        'ok'           => ['Ingresó', 'success'],
        'already_used' => ['Ya usada', 'warning'],
        'invalid'      => ['Inválida', 'danger'],
        'wrong_event'  => ['Otro evento', 'danger'],
        'cancelled'    => ['Anulada', 'danger'],
    ];
    $maxSlot = max(1, $timeline->max('total') ?? 1);
@endphp

@push('styles')
<meta http-equiv="refresh" content="20">
<style>
    .bar-col { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; min-width: 18px; }
    .bar { width: 70%; background: #6d28d9; border-radius: 4px 4px 0 0; }
</style>
@endpush

@section('content')
<div class="mb-3">
    <a href="{{ route('organizer.events.show', $event) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> {{ $event->title }}</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Control de ingreso</h1>
        <span class="text-muted small">Se actualiza solo cada 20 segundos · {{ now()->format('H:i:s') }}</span>
    </div>
    <a href="{{ url('/escaner') }}" target="_blank" class="btn btn-primary"><i class="bi bi-qr-code-scan"></i> Abrir escáner</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-5">
        <div class="card p-4 h-100">
            <div class="text-muted small">Ingresaron</div>
            <div class="display-5 fw-bold">{{ number_format($stats['used'], 0, ',', '.') }} <span class="fs-4 text-muted">/ {{ number_format($stats['total'], 0, ',', '.') }}</span></div>
            <div class="progress mt-2" style="height: 12px;">
                <div class="progress-bar bg-success" style="width: {{ $pct }}%"></div>
            </div>
            <div class="small text-muted mt-1">{{ $pct }}% · faltan {{ number_format($stats['total'] - $stats['used'], 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card p-4 h-100">
            <div class="text-muted small mb-2">Ingresos cada 15 minutos</div>
            @if ($timeline->isEmpty())
                <div class="text-muted small">Todavía no hubo ingresos.</div>
            @else
                <div class="d-flex align-items-end gap-1" style="height: 120px;">
                    @foreach ($timeline as $slot)
                        <div class="bar-col h-100" title="{{ $slot['label'] }} · {{ $slot['total'] }}">
                            <div class="small text-muted" style="font-size: .65rem;">{{ $slot['total'] }}</div>
                            <div class="bar" style="height: {{ max(3, round($slot['total'] * 100 / $maxSlot)) }}%;"></div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between small text-muted mt-1">
                    <span>{{ $timeline->first()['label'] }}</span><span>{{ $timeline->last()['label'] }}</span>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <h2 class="h6 fw-bold mb-3">Por tipo de entrada</h2>
            @forelse ($byType as $row)
                @php $p = $row->total ? round($row->used * 100 / $row->total) : 0; @endphp
                <div class="mb-2">
                    <div class="d-flex justify-content-between small"><span>{{ $row->name }}</span><span>{{ (int) $row->used }} / {{ $row->total }}</span></div>
                    <div class="progress" style="height: 6px;"><div class="progress-bar" style="width: {{ $p }}%"></div></div>
                </div>
            @empty
                <div class="text-muted small">Sin entradas emitidas.</div>
            @endforelse
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <h2 class="h6 fw-bold mb-3">Por puerta</h2>
            @forelse ($byGate as $row)
                <div class="d-flex justify-content-between small py-1 border-bottom"><span>{{ $row->gate_name }}</span><span class="fw-semibold">{{ $row->total }}</span></div>
            @empty
                <div class="text-muted small">Sin ingresos todavía.</div>
            @endforelse
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <h2 class="h6 fw-bold mb-3">Rechazos</h2>
            @forelse ($rejected as $result => $total)
                <div class="d-flex justify-content-between small py-1 border-bottom">
                    <span>{{ $results[$result][0] ?? $result }}</span><span class="fw-semibold">{{ $total }}</span>
                </div>
            @empty
                <div class="text-muted small">Sin rechazos.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="card">
    <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Últimos escaneos</h2></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light small">
                <tr><th>Hora</th><th>Resultado</th><th>Entrada</th><th>Asistente</th><th>Puerta</th><th>Operador</th></tr>
            </thead>
            <tbody class="small">
                @forelse ($lastScans as $scan)
                    @php [$label, $color] = $results[$scan->result] ?? [$scan->result, 'secondary']; @endphp
                    <tr>
                        <td class="text-nowrap">{{ $scan->scanned_at->format('H:i:s') }}</td>
                        <td>
                            <span class="badge text-bg-{{ $color }}">{{ $label }}</span>
                            @if ($scan->offline) <i class="bi bi-wifi-off text-muted" title="Escaneado sin conexión"></i> @endif
                        </td>
                        <td>
                            @if ($scan->ticket)
                                {{ $scan->ticket->ticketType->name }} <span class="font-monospace text-muted">{{ $scan->ticket->code }}</span>
                            @else
                                <span class="text-muted font-monospace">{{ \Illuminate\Support\Str::limit($scan->scanned_code, 24) }}</span>
                            @endif
                        </td>
                        <td>{{ $scan->ticket?->attendee_name ?? '—' }} @if ($scan->ticket?->attendee_dni)<span class="text-muted">· {{ $scan->ticket->attendee_dni }}</span>@endif</td>
                        <td>{{ $scan->gate ?? '—' }}</td>
                        <td>{{ $scan->operator?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Todavía no hubo escaneos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
