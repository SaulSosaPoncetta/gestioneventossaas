@extends('layouts.panel')

@section('title', 'Cupones')

@php
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') . '%';
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Cupones de descuento</h1>
        <span class="text-muted">Códigos para todos tus eventos o para uno en particular</span>
    </div>
    <a href="{{ route('organizer.coupons.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo cupón</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar código...">
    </div>
    <div class="col-md-4">
        <select name="event" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los eventos</option>
            @foreach ($events as $ev)
                <option value="{{ $ev->id }}" @selected((string) request('event') === (string) $ev->id)>{{ $ev->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Buscar</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Descuento</th>
                    <th>Aplica a</th>
                    <th class="text-center">Usos</th>
                    <th>Vigencia</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coupons as $coupon)
                    @php
                        $expired = $coupon->ends_at && $coupon->ends_at->isPast();
                        $exhausted = $coupon->remainingUses() === 0;
                    @endphp
                    <tr>
                        <td><code class="fs-6">{{ $coupon->code }}</code></td>
                        <td class="fw-semibold">
                            {{ $coupon->type === 'percent' ? $pct($coupon->value) : $money($coupon->value) }}
                            @if ($coupon->min_tickets > 1)<div class="small text-muted fw-normal">desde {{ $coupon->min_tickets }} entradas</div>@endif
                        </td>
                        <td class="small">{{ $coupon->event?->title ?? 'Todos los eventos' }}</td>
                        <td class="text-center">
                            {{ $coupon->used_count }} / {{ $coupon->max_uses ?? '∞' }}
                            @if ($coupon->reserved_count)<div class="small text-warning">+{{ $coupon->reserved_count }} en pago</div>@endif
                        </td>
                        <td class="small text-muted">
                            {{ $coupon->starts_at?->format('d/m/Y') ?? 'Desde ya' }} → {{ $coupon->ends_at?->format('d/m/Y H:i') ?? 'sin vencimiento' }}
                        </td>
                        <td class="text-center">
                            @if (! $coupon->is_active)
                                <span class="badge text-bg-secondary">Inactivo</span>
                            @elseif ($expired)
                                <span class="badge text-bg-dark">Vencido</span>
                            @elseif ($exhausted)
                                <span class="badge text-bg-danger">Agotado</span>
                            @else
                                <span class="badge text-bg-success">Activo</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('organizer.coupons.edit', $coupon) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('organizer.coupons.destroy', $coupon) }}" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar el cupón {{ $coupon->code }}?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-percent fs-2 d-block mb-2"></i>
                            Todavía no creaste cupones.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $coupons->links('pagination::bootstrap-5') }}</div>
@endsection
