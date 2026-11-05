@extends('layouts.panel')

@section('title', $event->exists ? 'Editar evento' : 'Nuevo evento')

@php
    $dt = fn ($field) => old($field, optional($event->{$field})->format('Y-m-d\TH:i'));
@endphp

@section('content')
<div class="mb-4">
    <a href="{{ $event->exists ? route('organizer.events.show', $event) : route('organizer.events.index') }}" class="small text-decoration-none">
        <i class="bi bi-arrow-left"></i> {{ $event->exists ? $event->title : 'Eventos' }}
    </a>
    <h1 class="h4 fw-bold mb-0 mt-1">{{ $event->exists ? 'Editar evento' : 'Nuevo evento' }}</h1>
</div>

<form method="POST" enctype="multipart/form-data" novalidate
      action="{{ $event->exists ? route('organizer.events.update', $event) : route('organizer.events.store') }}">
    @csrf
    @if ($event->exists) @method('PUT') @endif

    <div class="row g-4">
        {{-- Columna principal --}}
        <div class="col-lg-8">
            <div class="card p-4 mb-4">
                <h2 class="h6 fw-bold mb-3">Información general</h2>

                <div class="mb-3">
                    <label class="form-label" for="title">Título</label>
                    <input type="text" id="title" name="title" value="{{ old('title', $event->title) }}" required
                           class="form-control form-control-lg @error('title') is-invalid @enderror" placeholder="Ej: La Banda en vivo — Gira 2027">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="category">Categoría</label>
                        <select id="category" name="category" class="form-select @error('category') is-invalid @enderror">
                            <option value="">Seleccionar...</option>
                            @foreach (config('geventos.categorias') as $key => $label)
                                <option value="{{ $key }}" @selected(old('category', $event->category) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="venue_id">Lugar</label>
                        <select id="venue_id" name="venue_id" @disabled($hasSales)
                                class="form-select @error('venue_id') is-invalid @enderror">
                            <option value="" data-seatmap="0">A definir</option>
                            @foreach ($venues as $venue)
                                <option value="{{ $venue->id }}" data-seatmap="{{ $venue->has_seat_map ? 1 : 0 }}"
                                        @selected((string) old('venue_id', $event->venue_id) === (string) $venue->id)>
                                    {{ $venue->name }}{{ $venue->city ? ' — ' . $venue->city : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($hasSales)
                            <input type="hidden" name="venue_id" value="{{ $event->venue_id }}">
                            <div class="form-text">No se puede cambiar: el evento ya tiene ventas.</div>
                        @elseif ($venues->isEmpty())
                            <div class="form-text"><a href="{{ route('organizer.venues.create') }}">Cargá un lugar</a> para asociarlo.</div>
                        @endif
                        @error('venue_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="summary">Resumen <span class="text-muted small">(se muestra en la cartelera, hasta 300 caracteres)</span></label>
                    <input type="text" id="summary" name="summary" maxlength="300" value="{{ old('summary', $event->summary) }}"
                           class="form-control @error('summary') is-invalid @enderror">
                    @error('summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="form-label" for="description">Descripción</label>
                    <textarea id="description" name="description" rows="8"
                              class="form-control @error('description') is-invalid @enderror"
                              placeholder="Line-up, horarios, edad mínima, qué se puede llevar...">{{ old('description', $event->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="card p-4 mb-4">
                <h2 class="h6 fw-bold mb-3">Fechas y horarios</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="starts_at">Inicio</label>
                        <input type="datetime-local" id="starts_at" name="starts_at" value="{{ $dt('starts_at') }}" required
                               class="form-control @error('starts_at') is-invalid @enderror">
                        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ends_at">Fin <span class="text-muted small">(opcional)</span></label>
                        <input type="datetime-local" id="ends_at" name="ends_at" value="{{ $dt('ends_at') }}"
                               class="form-control @error('ends_at') is-invalid @enderror">
                        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="doors_open_at">Apertura de puertas</label>
                        <input type="datetime-local" id="doors_open_at" name="doors_open_at" value="{{ $dt('doors_open_at') }}"
                               class="form-control @error('doors_open_at') is-invalid @enderror">
                        @error('doors_open_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sales_start_at">Inicio de venta <span class="text-muted small">(vacío = al publicar)</span></label>
                        <input type="datetime-local" id="sales_start_at" name="sales_start_at" value="{{ $dt('sales_start_at') }}"
                               class="form-control @error('sales_start_at') is-invalid @enderror">
                        @error('sales_start_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sales_end_at">Fin de venta <span class="text-muted small">(vacío = hasta el inicio)</span></label>
                        <input type="datetime-local" id="sales_end_at" name="sales_end_at" value="{{ $dt('sales_end_at') }}"
                               class="form-control @error('sales_end_at') is-invalid @enderror">
                        @error('sales_end_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Columna lateral --}}
        <div class="col-lg-4">
            <div class="card p-4 mb-4">
                <h2 class="h6 fw-bold mb-3">Imagen de portada</h2>
                @if ($event->cover_path)
                    <img src="{{ asset('storage/' . $event->cover_path) }}" alt="" class="img-fluid rounded mb-2">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="remove_cover">
                        <label class="form-check-label small" for="remove_cover">Quitar imagen</label>
                    </div>
                @endif
                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp"
                       class="form-control @error('cover') is-invalid @enderror">
                <div class="form-text">JPG, PNG o WEBP de hasta 4 MB. Recomendado 1600×900.</div>
                @error('cover')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="card p-4 mb-4">
                <h2 class="h6 fw-bold mb-3">Modalidad de venta</h2>

                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_seated" value="1" id="is_seated"
                           @checked(old('is_seated', $event->is_seated)) @disabled($hasSales)>
                    <label class="form-check-label" for="is_seated">Butacas numeradas</label>
                </div>
                @if ($hasSales && $event->is_seated)
                    <input type="hidden" name="is_seated" value="1">
                @endif
                <div class="form-text mb-3">El comprador elige su butaca en el mapa. Requiere un lugar con mapa de butacas.</div>
                @error('is_seated')<div class="text-danger small mb-3">{{ $message }}</div>@enderror

                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_nominative" value="1" id="is_nominative"
                           @checked(old('is_nominative', $event->is_nominative))>
                    <label class="form-check-label" for="is_nominative">Entradas nominativas</label>
                </div>
                <div class="form-text mb-3">Cada entrada lleva nombre y DNI del asistente, que se valida en la puerta.</div>

                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" name="allow_transfers" value="1" id="allow_transfers"
                           @checked(old('allow_transfers', $event->allow_transfers))>
                    <label class="form-check-label" for="allow_transfers">Permitir transferencias</label>
                </div>
                <div class="form-text mb-3">El comprador puede cambiar el titular de una entrada; el QR anterior queda anulado.</div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small" for="max_tickets_per_order">Máx. por compra</label>
                        <input type="number" id="max_tickets_per_order" name="max_tickets_per_order" min="1"
                               max="{{ config('geventos.max_entradas_por_orden') }}"
                               value="{{ old('max_tickets_per_order', $event->max_tickets_per_order) }}"
                               class="form-control @error('max_tickets_per_order') is-invalid @enderror">
                        @error('max_tickets_per_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label small" for="capacity">Capacidad</label>
                        <input type="number" id="capacity" name="capacity" min="1" value="{{ old('capacity', $event->capacity) }}"
                               class="form-control @error('capacity') is-invalid @enderror" placeholder="Opcional">
                        @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button class="btn btn-primary btn-lg">{{ $event->exists ? 'Guardar cambios' : 'Crear evento' }}</button>
                <a href="{{ $event->exists ? route('organizer.events.show', $event) : route('organizer.events.index') }}" class="btn btn-light">Cancelar</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    // Avisa si se activa "numeradas" con un lugar sin mapa de butacas
    (function () {
        const venue = document.getElementById('venue_id');
        const seated = document.getElementById('is_seated');
        if (!venue || !seated || seated.disabled) return;

        function check() {
            const hasMap = venue.selectedOptions[0]?.dataset.seatmap === '1';
            seated.closest('.form-check').classList.toggle('opacity-50', !hasMap);
            if (!hasMap && seated.checked) seated.checked = false;
            seated.disabled = !hasMap;
        }
        venue.addEventListener('change', check);
        check();
    })();
</script>
@endpush
