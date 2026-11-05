@extends('layouts.app')

@section('title', 'Recuperar contraseña')

@section('content')
<div class="container">
    <div class="card auth-card mx-auto p-4 p-md-5">
        <h1 class="h4 fw-bold mb-1">Recuperar contraseña</h1>
        <p class="text-muted mb-4">Ingresá tu email y te enviamos un enlace para crear una nueva.</p>

        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf

            <div class="mb-4">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button class="btn btn-primary w-100 py-2 fw-semibold">Enviar enlace</button>
        </form>

        <p class="text-center mt-4 mb-0"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Volver a ingresar</a></p>
    </div>
</div>
@endsection
