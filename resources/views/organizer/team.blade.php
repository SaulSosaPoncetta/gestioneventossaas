@extends('layouts.panel')

@section('title', 'Equipo')

@section('content')
<div class="mb-4">
    <h1 class="h4 fw-bold mb-0">Equipo</h1>
    <span class="text-muted">Quiénes pueden usar el panel y el escáner de {{ $currentOrganizer->name }}</span>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light small"><tr><th>Persona</th><th style="width: 220px;">Rol</th><th class="text-end"></th></tr></thead>
                    <tbody>
                        @foreach ($members as $m)
                            @php $isMe = $m->id === auth()->id(); @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $m->name }} @if ($isMe)<span class="badge text-bg-light border">Vos</span>@endif</div>
                                    <div class="small text-muted">{{ $m->email }}</div>
                                </td>
                                <td>
                                    @if ($isMe)
                                        <span class="small">{{ $roles[$m->pivot->role][0] ?? $m->pivot->role }}</span>
                                    @else
                                        <form method="POST" action="{{ route('organizer.team.update', $m) }}">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                                                @foreach ($roles as $key => [$label])
                                                    <option value="{{ $key }}" @selected($m->pivot->role === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @unless ($isMe)
                                        <form method="POST" action="{{ route('organizer.team.destroy', $m) }}"
                                              onsubmit="return confirm('¿Quitar a {{ $m->name }} del equipo? Se cierran sus sesiones del escáner.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-person-dash"></i></button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card p-4 mb-4">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-person-plus"></i> Sumar a alguien</h2>
            <form method="POST" action="{{ route('organizer.team.store') }}" novalidate>
                @csrf
                <div class="mb-2">
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Nombre y apellido"
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="Email"
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <select name="role" class="form-select">
                        @foreach ($roles as $key => [$label])
                            @continue($key === 'owner' && $currentRole !== 'owner')
                            <option value="{{ $key }}" @selected(old('role', 'door') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary w-100">Sumar al equipo</button>
            </form>
            <p class="small text-muted mt-2 mb-0">Si no tiene usuario, se lo creamos y le mandamos un mail para que elija su contraseña.</p>
        </div>

        <div class="card p-4 small">
            <h2 class="h6 fw-bold mb-2">Roles</h2>
            @foreach ($roles as [$label, $desc])
                <div class="mb-2"><strong>{{ $label }}:</strong> <span class="text-muted">{{ $desc }}</span></div>
            @endforeach
        </div>
    </div>
</div>
@endsection
