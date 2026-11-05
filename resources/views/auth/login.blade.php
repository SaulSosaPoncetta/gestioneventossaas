@extends('layouts.app')

@section('title', 'Ingresar')

@section('content')
<div class="container">
    <div class="card auth-card mx-auto p-4 p-md-5">
        <h1 class="h4 fw-bold mb-1">Ingresar</h1>
        <p class="text-muted mb-4">Accedé a tus entradas o al panel de tu productora.</p>

        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autofocus required
                       class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Contraseña</label>
                <input type="password" id="password" name="password" required
                       class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember">Recordarme</label>
                </div>
                <a href="{{ route('password.request') }}" class="small">¿Olvidaste tu contraseña?</a>
            </div>

            <button class="btn btn-primary w-100 py-2 fw-semibold">Ingresar</button>
        </form>

        <hr class="my-4">
        <p class="text-center mb-1">¿No tenés cuenta? <a href="{{ route('register') }}">Creala gratis</a></p>
        <p class="text-center small text-muted mb-0">¿Organizás eventos? <a href="{{ route('organizer.register') }}">Registrá tu productora</a></p>
    </div>
</div>
@endsection
