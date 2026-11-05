@extends('layouts.panel')

@section('title', $venue->exists ? 'Editar lugar' : 'Nuevo lugar')

@section('content')
<div class="mb-4">
    <a href="{{ route('organizer.venues.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Lugares</a>
    <h1 class="h4 fw-bold mb-0 mt-1">{{ $venue->exists ? 'Editar lugar' : 'Nuevo lugar' }}</h1>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card p-4">
            <form method="POST" novalidate
                  action="{{ $venue->exists ? route('organizer.venues.update', $venue) : route('organizer.venues.store') }}">
                @csrf
                @if ($venue->exists) @method('PUT') @endif

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="name">Nombre del lugar</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $venue->name) }}" required
                               class="form-control @error('name') is-invalid @enderror" placeholder="Ej: Teatro Municipal">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="capacity">Capacidad total</label>
                        <input type="number" id="capacity" name="capacity" min="1" value="{{ old('capacity', $venue->capacity) }}"
                               class="form-control @error('capacity') is-invalid @enderror">
                        @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="address">Dirección</label>
                        <input type="text" id="address" name="address" value="{{ old('address', $venue->address) }}"
                               class="form-control @error('address') is-invalid @enderror" placeholder="Calle y número">
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="city">Ciudad</label>
                        <input type="text" id="city" name="city" value="{{ old('city', $venue->city) }}"
                               class="form-control @error('city') is-invalid @enderror">
                        @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="province">Provincia</label>
                        <select id="province" name="province" class="form-select @error('province') is-invalid @enderror">
                            <option value="">Seleccionar...</option>
                            @foreach (config('geventos.provincias') as $prov)
                                <option value="{{ $prov }}" @selected(old('province', $venue->province) === $prov)>{{ $prov }}</option>
                            @endforeach
                        </select>
                        @error('province')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="latitude">Latitud <span class="text-muted small">(opcional)</span></label>
                        <input type="text" id="latitude" name="latitude" value="{{ old('latitude', $venue->latitude) }}"
                               class="form-control @error('latitude') is-invalid @enderror" placeholder="-34.603722">
                        @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="longitude">Longitud <span class="text-muted small">(opcional)</span></label>
                        <input type="text" id="longitude" name="longitude" value="{{ old('longitude', $venue->longitude) }}"
                               class="form-control @error('longitude') is-invalid @enderror" placeholder="-58.381592">
                        @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary px-4">Guardar</button>
                    <a href="{{ route('organizer.venues.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card p-4">
            <h2 class="h6 fw-bold"><i class="bi bi-grid-3x3-gap"></i> Mapa de butacas</h2>
            <p class="text-muted small mb-0">
                Después de guardar el lugar vas a poder dibujar sus sectores (campo, plateas, palcos) y las butacas numeradas.
                Esa función llega en el próximo paso.
            </p>
        </div>
    </div>
</div>
@endsection
