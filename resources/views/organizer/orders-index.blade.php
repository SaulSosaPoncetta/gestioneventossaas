@extends('layouts.panel')

@section('title', 'Órdenes')

@php
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
    $statuses = [
        'paid'      => ['Pagada', 'success'],
        'pending'   => ['Pendiente', 'warning'],
        'refunded'  => ['Devuelta', 'danger'],
        'expired'   => ['Vencida', 'secondary'],
        'cancelled' => ['Cancelada', 'secondary'],
    ];
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Órdenes</h1>
        <span class="text-muted">Compras de tus eventos</span>
    </div>
    <a href="{{ route('organizer.orders.export', request()->query()) }}" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-spreadsheet"></i> Exportar a Excel
    </a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nombre, email, DNI, N° de compra o de pago...">
    </div>
    <div class="col-md-4">
        <select name="event" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los eventos</option>
            @foreach ($events as $ev)
                <option value="{{ $ev->id }}" @selected((string) request('event') === (string) $ev->id)>{{ $ev->title }} ({{ $ev->starts_at->format('d/m/Y') }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="">Pagadas, pendientes y devueltas</option>
            @foreach ($statuses as $key => [$label])
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
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
            <thead class="table-light small">
                <tr>
                    <th>Compra</th>
                    <th>Fecha</th>
                    <th>Comprador</th>
                    <th>Evento</th>
                    <th class="text-center">Entradas</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Neto</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php [$label, $color] = $statuses[$order->status] ?? [$order->status, 'secondary']; @endphp
                    <tr style="cursor: pointer;" onclick="location.href='{{ route('organizer.orders.show', $order) }}'">
                        <td class="font-monospace small">{{ strtoupper(substr($order->uuid, 0, 8)) }}</td>
                        <td class="small text-nowrap">{{ ($order->paid_at ?? $order->created_at)->format('d/m/Y H:i') }}</td>
                        <td>
                            <div class="fw-semibold">{{ $order->buyer_name }}</div>
                            <div class="small text-muted">{{ $order->buyer_email }}</div>
                        </td>
                        <td class="small">{{ $order->event->title }}</td>
                        <td class="text-center">{{ $order->quantity }}</td>
                        <td class="text-end">{{ $money($order->total) }}</td>
                        <td class="text-end small text-muted">{{ $order->isPaid() ? $money($order->organizerAmount()) : '—' }}</td>
                        <td class="text-center"><span class="badge text-bg-{{ $color }}">{{ $label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-receipt fs-2 d-block mb-2"></i>No hay órdenes para mostrar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $orders->links('pagination::bootstrap-5') }}</div>
@endsection
