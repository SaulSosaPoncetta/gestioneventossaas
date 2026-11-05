@extends('layouts.app')

@section('title', 'Entradas para eventos')

@section('content')
<div class="container">
    <section class="rounded-4 p-4 p-md-5 mb-4 text-white"
             style="background: linear-gradient(135deg, #4c1d95, #6d28d9 60%, #a855f7);">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-warning text-dark mb-3">Entradas 100% online</span>
                <h1 class="display-6 fw-bold mb-3">Comprá tus entradas en minutos y entrá con tu QR</h1>
                <p class="lead opacity-75 mb-4">Pagás con Mercado Pago, recibís tus entradas por mail y las tenés siempre en tu celular.</p>
                @guest
                    <a href="{{ route('register') }}" class="btn btn-warning fw-semibold px-4 me-2">Crear cuenta</a>
                    <a href="{{ route('organizer.register') }}" class="btn btn-outline-light px-4">Soy productora</a>
                @endguest
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <i class="bi bi-qr-code" style="font-size: 9rem; opacity: .9;"></i>
            </div>
        </div>
    </section>

    <h2 class="h5 fw-bold mb-3">Próximos eventos</h2>
    <div class="card p-5 text-center text-muted">
        <i class="bi bi-calendar2-event fs-1 mb-2"></i>
        Muy pronto vas a ver acá la cartelera de eventos.
    </div>
</div>
@endsection
