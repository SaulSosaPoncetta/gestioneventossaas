@extends('layouts.app')

@section('title', 'Registrá tu productora')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm p-4 p-md-5">
                <h1 class="h4 fw-bold mb-1">Registrá tu productora</h1>
                <p class="text-muted mb-4">
                    Vendé entradas online y cobrá directo en tu cuenta de Mercado Pago. Controlá el ingreso con QR desde cualquier celular.
                </p>

                <form method="POST" action="{{ route('organizer.register.store') }}" novalidate>
                    @csrf

                    <h2 class="h6 text-uppercase text-muted fw-bold mb-3">Datos de la productora</h2>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="organizer_name">Nombre comercial</label>
                            <input type="text" id="organizer_name" name="organizer_name" value="{{ old('organizer_name') }}" required
                                   class="form-control @error('organizer_name') is-invalid @enderror">
                            @error('organizer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="organizer_email">Email de contacto</label>
                            <input type="email" id="organizer_email" name="organizer_email" value="{{ old('organizer_email') }}" required
                                   class="form-control @error('organizer_email') is-invalid @enderror">
                            @error('organizer_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="organizer_phone">Teléfono</label>
                            <input type="tel" id="organizer_phone" name="organizer_phone" value="{{ old('organizer_phone') }}"
                                   class="form-control @error('organizer_phone') is-invalid @enderror">
                            @error('organizer_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cuit">CUIT <span class="text-muted small">(opcional)</span></label>
                            <input type="text" id="cuit" name="cuit" value="{{ old('cuit') }}" placeholder="20-12345678-9"
                                   class="form-control @error('cuit') is-invalid @enderror">
                            @error('cuit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="legal_name">Razón social <span class="text-muted small">(opcional)</span></label>
                            <input type="text" id="legal_name" name="legal_name" value="{{ old('legal_name') }}"
                                   class="form-control @error('legal_name') is-invalid @enderror">
                            @error('legal_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    @guest
                        <h2 class="h6 text-uppercase text-muted fw-bold mb-3">Tu usuario (responsable)</h2>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Nombre y apellido</label>
                                <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                       class="form-control @error('name') is-invalid @enderror">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="dni">DNI <span class="text-muted small">(opcional)</span></label>
                                <input type="text" id="dni" name="dni" value="{{ old('dni') }}" inputmode="numeric"
                                       class="form-control @error('dni') is-invalid @enderror">
                                @error('dni')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label" for="email">Email para ingresar</label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                       class="form-control @error('email') is-invalid @enderror">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password">Contraseña</label>
                                <input type="password" id="password" name="password" required
                                       class="form-control @error('password') is-invalid @enderror">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirmation">Repetir contraseña</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" required class="form-control">
                            </div>
                        </div>
                    @else
                        <div class="alert alert-light border mb-4">
                            <i class="bi bi-person-check"></i>
                            La productora quedará a nombre de <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).
                        </div>
                    @endguest

                    <button class="btn btn-primary px-4 py-2 fw-semibold">Registrar productora</button>
                    @guest
                        <span class="ms-2 small text-muted">¿Ya tenés usuario? <a href="{{ route('login') }}">Ingresá primero</a></span>
                    @endguest
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
