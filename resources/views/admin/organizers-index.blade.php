@extends('layouts.app')

@section('title', 'Productoras · Administración')

@section('content')
<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0"><i class="bi bi-shield-lock"></i> Productoras</h1>
            <span class="text-muted">Aprobación, suspensión y comisión de cada productora</span>
        </div>
        @if ($pendingCount)
            <a href="{{ route('admin.organizers.index', ['status' => 'pending']) }}" class="btn btn-warning">
                <i class="bi bi-hourglass-split"></i> {{ $pendingCount }} pendiente(s) de aprobación
            </a>
        @endif
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nombre, email o CUIT...">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                @foreach (config('geventos.estados_productora') as $key => [$label])
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Buscar</button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Productora</th>
                        <th>Responsable</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Mercado Pago</th>
                        <th class="text-center">Eventos</th>
                        <th style="width: 190px;">Comisión %</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($organizers as $org)
                        @php
                            [$stLabel, $stColor] = config('geventos.estados_productora.' . $org->status);
                            $owner = $org->users->first();
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $org->name }}</div>
                                <div class="small text-muted">
                                    {{ $org->email }}
                                    @if ($org->cuit) · CUIT {{ $org->cuit }} @endif
                                </div>
                                <div class="small text-muted">Alta: {{ $org->created_at->format('d/m/Y') }}</div>
                            </td>
                            <td class="small">
                                @if ($owner)
                                    {{ $owner->name }}<br><span class="text-muted">{{ $owner->email }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center"><span class="badge text-bg-{{ $stColor }}">{{ $stLabel }}</span></td>
                            <td class="text-center">
                                @if ($org->isMpConnected())
                                    <i class="bi bi-check-circle-fill text-success" title="Vinculada"></i>
                                @else
                                    <i class="bi bi-dash-circle text-muted" title="Sin vincular"></i>
                                @endif
                            </td>
                            <td class="text-center">{{ $org->events_count }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.organizers.update', $org) }}" class="input-group input-group-sm">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="commission">
                                    <input type="number" name="commission_percent" step="0.01" min="0" max="50"
                                           value="{{ $org->commission_percent }}" class="form-control"
                                           placeholder="General ({{ config('geventos.comision_porcentaje') }})">
                                    <button class="btn btn-outline-secondary" title="Guardar"><i class="bi bi-check-lg"></i></button>
                                </form>
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($org->status !== 'active')
                                    <form method="POST" action="{{ route('admin.organizers.update', $org) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-sm btn-success">
                                            <i class="bi bi-check2-circle"></i> {{ $org->status === 'pending' ? 'Aprobar' : 'Reactivar' }}
                                        </button>
                                    </form>
                                @endif
                                @if ($org->status !== 'suspended')
                                    <form method="POST" action="{{ route('admin.organizers.update', $org) }}" class="d-inline"
                                          onsubmit="return confirm('¿Suspender a «{{ $org->name }}»? No podrá publicar ni vender.')">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="suspend">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-slash-circle"></i> Suspender</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">No hay productoras para mostrar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $organizers->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
