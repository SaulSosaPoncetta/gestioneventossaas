@extends('layouts.app')

@section('title', 'Nueva contraseña')

@section('content')
<div class="container">
    <div class="card auth-card mx-auto p-4 p-md-5">
        <h1 class="h4 fw-bold mb-4">Elegí una nueva contraseña</h1>

        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required
                       class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Nueva contraseña</label>
                <input type="password" id="password" name="password" required autofocus
                       class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Repetir contraseña</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required class="form-control">
            </div>

            <button class="btn btn-primary w-100 py-2 fw-semibold">Guardar contraseña</button>
        </form>
    </div>
</div>
@endsection
