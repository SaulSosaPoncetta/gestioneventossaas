@extends('layouts.panel')

@section('title', 'Reportes')

@php
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
    $int = fn ($v) => number_format((int) $v, 0, ',', '.');
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Reportes y liquidación</h1>
        <span class="text-muted">Ventas por evento y lo que te corresponde</span>
    </div>
</div>

<form method="GET" class="row g-2 align-items-end mb-4">
    <div class="col-sm-4 col-md-3">
        <label class="form-label small mb-1">Pagos desde</label>
        <input type="date" name="desde" value="{{ request('desde') }}" class="form-control">
    </div>
    <div class="col-sm-4 col-md-3">
        <label class="form-label small mb-1">Hasta</label>
        <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control">
    </div>
    <div class="col-sm-4 col-md-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-funnel"></i> Filtrar</button>
    </div>
</form>

{{-- Totales --}}
<div class="row g-3 mb-4">
    @foreach ([
        ['Entradas vendidas', $int($totals->tickets), 'bi-ticket-perforated', '#ede9fe', '#6d28d9'],
        ['Cobrado a compradores', $money($totals->charged), 'bi-cash-stack', '#dbeafe', '#1d4ed8'],
        ['Retención plataforma', $money($totals->platform_fee), 'bi-percent', '#fee2e2', '#b91c1c'],
        ['Neto productora', $money($totals->net), 'bi-wallet2', '#dcfce7', '#15803d'],
    ] as [$label, $value, $icon, $bg, $fg])
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

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Evento</th>
                    <th class="text-center">Órdenes</th>
                    <th class="text-center">Entradas</th>
                    <th class="text-center">Ingresaron</th>
                    <th class="text-end">Bruto</th>
                    <th class="text-end">Descuentos</th>
                    <th class="text-end">Cobrado</th>
                    <th class="text-end">Retención</th>
                    <th class="text-end">Neto</th>
                    <th class="text-end">Devuelto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr style="cursor: pointer;" onclick="location.href='{{ route('organizer.reports.show', $r->event) }}'">
                        <td>
                            <div class="fw-semibold">{{ $r->event->title }}</div>
                            <div class="small text-muted">{{ $r->event->starts_at->format('d/m/Y') }} · {{ $r->event->venue?->name ?? 'Sin lugar' }}</div>
                        </td>
                        <td class="text-center">{{ $int($r->orders) }}</td>
                        <td class="text-center">{{ $int($r->tickets) }}</td>
                        <td class="text-center">{{ $int($r->checked_in) }}</td>
                        <td class="text-end">{{ $money($r->gross) }}</td>
                        <td class="text-end text-success">{{ $r->discount > 0 ? '- ' . $money($r->discount) : '—' }}</td>
                        <td class="text-end">{{ $money($r->charged) }}</td>
                        <td class="text-end text-danger">- {{ $money($r->platform_fee) }}</td>
                        <td class="text-end fw-bold">{{ $money($r->net) }}</td>
                        <td class="text-end small text-muted">
                            @if ($r->refunded_orders) {{ $money($r->refunded_amount) }}<div>({{ $r->refunded_orders }})</div> @else — @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-5"><i class="bi bi-bar-chart fs-2 d-block mb-2"></i>Todavía no hay ventas en el período.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-center">{{ $int($totals->orders) }}</td>
                        <td class="text-center">{{ $int($totals->tickets) }}</td>
                        <td class="text-center">{{ $int($totals->checked_in) }}</td>
                        <td class="text-end">{{ $money($totals->gross) }}</td>
                        <td class="text-end">{{ $totals->discount > 0 ? '- ' . $money($totals->discount) : '—' }}</td>
                        <td class="text-end">{{ $money($totals->charged) }}</td>
                        <td class="text-end">- {{ $money($totals->platform_fee) }}</td>
                        <td class="text-end">{{ $money($totals->net) }}</td>
                        <td class="text-end">{{ $totals->refunded_amount > 0 ? $money($totals->refunded_amount) : '—' }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<p class="small text-muted mt-3 mb-0">
    <strong>Cobrado</strong> = entradas − descuentos + cargo por servicio. <strong>Retención</strong> = cargo por servicio + comisión de la plataforma.
    <strong>Neto</strong> = lo que se acredita en tu Mercado Pago antes de los costos propios de tu cuenta (en el detalle de cada evento ves lo que Mercado Pago informa como acreditado).
</p>
@endsection
