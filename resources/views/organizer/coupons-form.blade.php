@extends('layouts.panel')

@section('title', $coupon->exists ? 'Editar cupón' : 'Nuevo cupón')

@php
    $dt = fn ($field) => old($field, optional($coupon->{$field})->format('Y-m-d\TH:i'));
@endphp

@section('content')
<div class="mb-4">
    <a href="{{ route('organizer.coupons.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Cupones</a>
    <h1 class="h4 fw-bold mb-0 mt-1">{{ $coupon->exists ? 'Editar cupón ' . $coupon->code : 'Nuevo cupón' }}</h1>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card p-4">
            <form method="POST" novalidate
                  action="{{ $coupon->exists ? route('organizer.coupons.update', $coupon) : route('organizer.coupons.store') }}">
                @csrf
                @if ($coupon->exists) @method('PUT') @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="code">Código</label>
                        <input type="text" id="code" name="code" maxlength="40" required style="text-transform: uppercase;"
                               value="{{ old('code', $coupon->code) }}" placeholder="Ej: PREVENTA20"
                               class="form-control form-control-lg font-monospace @error('code') is-invalid @enderror">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="event_id">Aplica a</label>
                        <select id="event_id" name="event_id" class="form-select form-select-lg @error('event_id') is-invalid @enderror">
                            <option value="">Todos mis eventos</option>
                            @foreach ($events as $ev)
                                <option value="{{ $ev->id }}" @selected((string) old('event_id', $coupon->event_id) === (string) $ev->id)>
                                    {{ $ev->title }} ({{ $ev->starts_at->format('d/m/Y') }})
                                </option>
                            @endforeach
                        </select>
                        @error('event_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tipo de descuento</label>
                        <div class="d-flex gap-3 pt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" value="percent" id="tPercent"
                                       @checked(old('type', $coupon->type) === 'percent')>
                                <label class="form-check-label" for="tPercent">Porcentaje (%)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" value="fixed" id="tFixed"
                                       @checked(old('type', $coupon->type) === 'fixed')>
                                <label class="form-check-label" for="tFixed">Monto fijo ($)</label>
                            </div>
                        </div>
                        @error('type')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="value">Valor</label>
                        <input type="number" id="value" name="value" step="0.01" min="0.01" required
                               value="{{ old('value', $coupon->value) }}"
                               class="form-control @error('value') is-invalid @enderror">
                        <div class="form-text">El monto fijo se descuenta del total de la compra (no por entrada).</div>
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="max_uses">Usos máximos <span class="text-muted small">(vacío = ilimitado)</span></label>
                        <input type="number" id="max_uses" name="max_uses" min="1" value="{{ old('max_uses', $coupon->max_uses) }}"
                               class="form-control @error('max_uses') is-invalid @enderror">
                        @error('max_uses')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="min_tickets">Mínimo de entradas por compra</label>
                        <input type="number" id="min_tickets" name="min_tickets" min="1" max="50" required
                               value="{{ old('min_tickets', $coupon->min_tickets ?? 1) }}"
                               class="form-control @error('min_tickets') is-invalid @enderror">
                        @error('min_tickets')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="starts_at">Válido desde <span class="text-muted small">(opcional)</span></label>
                        <input type="datetime-local" id="starts_at" name="starts_at" value="{{ $dt('starts_at') }}"
                               class="form-control @error('starts_at') is-invalid @enderror">
                        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ends_at">Vence <span class="text-muted small">(opcional)</span></label>
                        <input type="datetime-local" id="ends_at" name="ends_at" value="{{ $dt('ends_at') }}"
                               class="form-control @error('ends_at') is-invalid @enderror">
                        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active"
                                   @checked(old('is_active', $coupon->is_active))>
                            <label class="form-check-label" for="is_active">Cupón activo</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary px-4">Guardar</button>
                    <a href="{{ route('organizer.coupons.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card p-4 small text-muted">
            <div class="fw-semibold text-dark mb-2"><i class="bi bi-lightbulb"></i> Cómo funcionan</div>
            <ul class="ps-3 mb-0">
                <li>El comprador ingresa el código al comprar y el descuento se aplica sobre el valor de las entradas.</li>
                <li>El cargo por servicio se calcula sobre el monto ya descontado.</li>
                <li>Un uso se descuenta recién cuando el pago se aprueba; mientras paga, queda reservado.</li>
                <li>Si un cupón ya se usó no se puede eliminar: se desactiva.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
