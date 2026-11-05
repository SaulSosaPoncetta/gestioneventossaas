@extends('layouts.app')

@section('title', 'Crear cuenta')

@section('content')
<div class="container">
    <div class="card auth-card mx-auto p-4 p-md-5">
        <h1 class="h4 fw-bold mb-1">Crear cuenta</h1>
        <p class="text-muted mb-4">Comprá entradas y tenelas siempre a mano en tu celular.</p>

        <form method="POST" action="{{ route('register.store') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label class="form-label" for="name">Nombre y apellido</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                       class="form-control @error('name') is-invalid @enderror">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                       class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="dni">DNI <span class="text-muted small">(opcional)</span></label>
                    <input type="text" id="dni" name="dni" value="{{ old('dni') }}" inputmode="numeric"
                           class="form-control @error('dni') is-invalid @enderror">
                    @error('dni')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="phone">Teléfono <span class="text-muted small">(opcional)</span></label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                           class="form-control @error('phone') is-invalid @enderror">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <label class="form-label" for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="password_confirmation">Repetir contraseña</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="form-control">
                </div>
            </div>

            <button class="btn btn-primary w-100 py-2 fw-semibold">Crear cuenta</button>
        </form>

        <hr class="my-4">
        <p class="text-center mb-0">¿Ya tenés cuenta? <a href="{{ route('login') }}">Ingresá</a></p>
    </div>
</div>
@endsection
