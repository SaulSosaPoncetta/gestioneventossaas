@extends('layouts.app')

@section('title', 'Recaudación · Administración')

@php
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
@endphp

@section('content')
<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0"><i class="bi bi-shield-lock"></i> Recaudación de la plataforma</h1>
            <span class="text-muted">Cargo por servicio + comisión retenidos en cada venta</span>
        </div>
        <a href="{{ route('admin.organizers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-building"></i> Productoras</a>
    </div>

    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-sm-4 col-md-3">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="desde" value="{{ $from->format('Y-m-d') }}" class="form-control">
        </div>
        <div class="col-sm-4 col-md-3">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="hasta" value="{{ $to->format('Y-m-d') }}" class="form-control">
        </div>
        <div class="col-sm-4 col-md-2">
            <button class="btn btn-outline-secondary w-100"><i class="bi bi-funnel"></i> Filtrar</button>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Órdenes pagas', number_format($rows->sum('orders'), 0, ',', '.')],
            ['Entradas', number_format($rows->sum('tickets'), 0, ',', '.')],
            ['Cobrado total', $money($rows->sum('charged'))],
            ['Retenido por la plataforma', $money($rows->sum('platform_fee'))],
        ] as [$label, $value])
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-5 fw-bold">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light small">
                    <tr><th>Productora</th><th class="text-center">Órdenes</th><th class="text-center">Entradas</th><th class="text-end">Cobrado</th><th class="text-end">Cargo servicio</th><th class="text-end">Retenido total</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="fw-semibold">{{ $r->name }}</td>
                            <td class="text-center">{{ $r->orders }}</td>
                            <td class="text-center">{{ $r->tickets }}</td>
                            <td class="text-end">{{ $money($r->charged) }}</td>
                            <td class="text-end">{{ $money($r->service_fee) }}</td>
                            <td class="text-end fw-bold">{{ $money($r->platform_fee) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">Sin ventas en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="small text-muted mt-3">Con cuentas vinculadas en modo prueba (sin OAuth) la plataforma no retiene nada en Mercado Pago, aunque acá figure el importe calculado.</p>
</div>
@endsection
