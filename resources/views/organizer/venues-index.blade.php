@extends('layouts.panel')

@section('title', 'Lugares')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Lugares</h1>
        <span class="text-muted">Teatros, salones, estadios y espacios donde hacés tus eventos</span>
    </div>
    <a href="{{ route('organizer.venues.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo lugar</a>
</div>

<form method="GET" class="mb-3" style="max-width: 360px;">
    <div class="input-group">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar por nombre...">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Ubicación</th>
                    <th class="text-center">Capacidad</th>
                    <th class="text-center">Mapa de butacas</th>
                    <th class="text-center">Eventos</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($venues as $venue)
                    <tr>
                        <td class="fw-semibold">{{ $venue->name }}</td>
                        <td class="text-muted small">{{ $venue->fullAddress() ?: '—' }}</td>
                        <td class="text-center">{{ $venue->capacity ? number_format($venue->capacity, 0, ',', '.') : '—' }}</td>
                        <td class="text-center">
                            @if ($venue->has_seat_map)
                                <span class="badge text-bg-success">Sí · {{ $venue->sections_count }} sectores</span>
                            @else
                                <span class="badge text-bg-light border">No</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $venue->events_count }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('organizer.venues.edit', $venue) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('organizer.venues.destroy', $venue) }}" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar el lugar «{{ $venue->name }}»?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-geo-alt fs-2 d-block mb-2"></i>
                            Todavía no cargaste lugares.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $venues->links('pagination::bootstrap-5') }}</div>
@endsection
