@extends('layouts.panel')

@section('title', 'Reporte · ' . $event->title)

@php
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
    $int = fn ($v) => number_format((int) $v, 0, ',', '.');
    $methods = ['visa' => 'Visa', 'master' => 'Mastercard', 'amex' => 'American Express', 'debvisa' => 'Visa Débito',
        'debmaster' => 'Mastercard Débito', 'account_money' => 'Dinero en cuenta MP', 'naranja' => 'Naranja', 'cabal' => 'Cabal'];
    $maxDay = max(1, (float) ($detail['byDay']->max('charged') ?? 1));
@endphp

@section('content')
<div class="mb-3">
    <a href="{{ route('organizer.reports.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Reportes</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">{{ $event->title }}</h1>
        <span class="text-muted">{{ $event->starts_at->format('d/m/Y H:i') }} · {{ config('geventos.estados_evento.' . $event->status)[0] }}</span>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('organizer.reports.attendees', $event) }}" class="btn btn-outline-success"><i class="bi bi-people"></i> Asistentes (Excel)</a>
        <a href="{{ route('organizer.orders.export', ['event' => $event->id]) }}" class="btn btn-outline-success"><i class="bi bi-receipt"></i> Órdenes (Excel)</a>
    </div>
</div>

{{-- Devolución masiva (eventos cancelados) --}}
@if ($event->status === 'cancelled' && in_array($currentRole, ['owner', 'admin'], true))
    <div class="card p-4 mb-4 border border-danger">
        <h2 class="h6 fw-bold text-danger mb-2"><i class="bi bi-x-octagon"></i> Evento cancelado</h2>
        @if ($refundProgress)
            <div class="small mb-2">
                Devoluciones: <strong>{{ $refundProgress['done'] }}</strong> de {{ $refundProgress['total'] }}
                @if ($refundProgress['failed']) · <span class="text-danger">{{ $refundProgress['failed'] }} con error (revisalas en Órdenes)</span> @endif
                @if ($refundProgress['running']) · <span class="text-warning">en curso… actualizá la página</span> @else · <span class="text-success">terminado</span> @endif
            </div>
        @endif
        @if (($summary->orders ?? 0) > 0 && ! ($refundProgress['running'] ?? false))
            <form method="POST" action="{{ route('organizer.events.refund-all', $event) }}"
                  onsubmit="return confirm('Se van a devolver TODAS las compras pagas ({{ $summary->orders }}) desde tu cuenta de Mercado Pago y se anulan las entradas. ¿Continuar?')">
                @csrf
                <button class="btn btn-danger"><i class="bi bi-arrow-counterclockwise"></i> Devolver las {{ $summary->orders }} compras pagas</button>
            </form>
        @elseif (! $refundProgress)
            <div class="small text-muted">No hay compras pagas para devolver.</div>
        @endif
    </div>
@endif

@if ($summary)
    <div class="row g-3 mb-4">
        @foreach ([
            ['Entradas vendidas', $int($summary->tickets) . ' · ' . $int($summary->checked_in) . ' ingresaron'],
            ['Cobrado', $money($summary->charged)],
            ['Retención plataforma', '- ' . $money($summary->platform_fee)],
            ['Neto productora', $money($summary->net)],
            ['Acreditado según Mercado Pago', $money($detail['mpNet'])],
        ] as [$label, $value])
            <div class="col-sm-6 col-xl">
                <div class="card p-3 h-100">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-6 fw-bold">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="alert alert-info">Este evento todavía no tiene ventas.</div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Por tipo de entrada y lote</h2></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light small"><tr><th>Tipo</th><th>Lote</th><th class="text-end">Precio</th><th class="text-center">Vendidas</th><th class="text-end">Importe</th></tr></thead>
                    <tbody class="small">
                        @forelse ($detail['byType'] as $r)
                            <tr>
                                <td>{{ $r->type }}</td>
                                <td>{{ $r->lot }}</td>
                                <td class="text-end">{{ $money($r->price) }}</td>
                                <td class="text-center">{{ $int($r->qty) }}</td>
                                <td class="text-end">{{ $money($r->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Sin ventas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Ventas por día</h2></div>
            <div class="p-3">
                @forelse ($detail['byDay'] as $d)
                    <div class="d-flex align-items-center gap-2 small mb-1">
                        <span style="width: 80px;">{{ \Illuminate\Support\Carbon::parse($d->day)->format('d/m/Y') }}</span>
                        <div class="flex-grow-1 bg-light rounded" style="height: 16px;">
                            <div class="bg-primary rounded" style="height: 16px; width: {{ max(2, round($d->charged * 100 / $maxDay)) }}%;"></div>
                        </div>
                        <span class="text-end" style="width: 170px;">{{ $int($d->tickets) }} entr. · {{ $money($d->charged) }}</span>
                    </div>
                @empty
                    <div class="text-muted small">Sin ventas.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Medios de pago</h2></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody class="small">
                        @forelse ($detail['byMethod'] as $m)
                            <tr>
                                <td>{{ $methods[$m->method] ?? ucfirst($m->method) }}</td>
                                <td class="text-center">{{ $m->payments }}</td>
                                <td class="text-end">{{ $money($m->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3">Sin pagos aprobados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Cupones usados</h2></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody class="small">
                        @forelse ($detail['byCoupon'] as $c)
                            <tr>
                                <td><code>{{ $c->code }}</code></td>
                                <td class="text-center">{{ $c->uses }} usos</td>
                                <td class="text-end text-success">- {{ $money($c->discount) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3">No se usaron cupones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
