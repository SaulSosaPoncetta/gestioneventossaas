@extends('layouts.panel')

@section('title', 'Eventos')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Eventos</h1>
        <span class="text-muted">Creá, publicá y seguí las ventas de tus eventos</span>
    </div>
    <a href="{{ route('organizer.events.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo evento</a>
</div>

{{-- Filtros --}}
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar por título...">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            @foreach (config('geventos.estados_evento') as $key => [$label])
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="when" class="form-select" onchange="this.form.submit()">
            <option value="">Próximos</option>
            <option value="past" @selected(request('when') === 'past')>Pasados</option>
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
                    <th>Evento</th>
                    <th>Fecha</th>
                    <th>Lugar</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Vendidas</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    @php
                        [$stLabel, $stColor] = config('geventos.estados_evento.' . $event->status);
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                @if ($event->cover_path)
                                    <img src="{{ asset('storage/' . $event->cover_path) }}" alt="" class="rounded"
                                         style="width: 56px; height: 40px; object-fit: cover;">
                                @else
                                    <div class="rounded bg-light d-grid text-muted" style="width: 56px; height: 40px; place-items: center;">
                                        <i class="bi bi-image"></i>
                                    </div>
                                @endif
                                <div>
                                    <a href="{{ route('organizer.events.show', $event) }}" class="fw-semibold text-decoration-none">{{ $event->title }}</a>
                                    <div class="small text-muted">
                                        {{ config('geventos.categorias.' . $event->category, 'Sin categoría') }}
                                        @if ($event->is_seated) · <i class="bi bi-grid-3x3-gap"></i> Numerado @endif
                                        @if ($event->is_nominative) · <i class="bi bi-person-badge"></i> Nominativo @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-nowrap">{{ $event->starts_at->format('d/m/Y H:i') }}</td>
                        <td class="small">{{ $event->venue?->name ?? 'A definir' }}</td>
                        <td class="text-center"><span class="badge text-bg-{{ $stColor }}">{{ $stLabel }}</span></td>
                        <td class="text-center">{{ number_format($event->sold_count, 0, ',', '.') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('organizer.events.show', $event) }}" class="btn btn-sm btn-outline-secondary" title="Ver">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('organizer.events.edit', $event) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                            No hay eventos para mostrar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $events->links('pagination::bootstrap-5') }}</div>
@endsection
