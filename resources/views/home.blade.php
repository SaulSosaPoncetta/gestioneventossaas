@extends('layouts.app')

@section('title', 'Entradas para eventos')

@push('styles')
<style>
    .event-card { border: 0; border-radius: 1rem; overflow: hidden; transition: transform .15s, box-shadow .15s; }
    .event-card:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(76, 29, 149, .14); }
    .event-cover { aspect-ratio: 16/9; object-fit: cover; width: 100%; background: linear-gradient(135deg, #4c1d95, #a855f7); }
    .date-chip { position: absolute; top: .75rem; left: .75rem; background: #fff; border-radius: .6rem; padding: .3rem .6rem; text-align: center; line-height: 1.1; box-shadow: 0 4px 10px rgba(0,0,0,.12); }
    .date-chip .d { font-size: 1.25rem; font-weight: 800; color: #4c1d95; }
    .date-chip .m { font-size: .7rem; text-transform: uppercase; font-weight: 700; color: #6b7280; }
</style>
@endpush

@section('content')
<div class="container">
    <section class="rounded-4 p-4 p-md-5 mb-4 text-white"
             style="background: linear-gradient(135deg, #4c1d95, #6d28d9 60%, #a855f7);">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-warning text-dark mb-3">Entradas 100% online</span>
                <h1 class="display-6 fw-bold mb-3">Comprá tus entradas en minutos y entrá con tu QR</h1>
                <p class="lead opacity-75 mb-4">Pagás con Mercado Pago, recibís tus entradas por mail y las tenés siempre en tu celular.</p>

                <form method="GET" action="{{ route('home') }}" class="d-flex gap-2" style="max-width: 520px;">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-lg" placeholder="Buscar evento, artista...">
                    <button class="btn btn-warning btn-lg px-4"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <i class="bi bi-qr-code" style="font-size: 9rem; opacity: .9;"></i>
            </div>
        </div>
    </section>

    {{-- Categorías --}}
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('home', request()->only('q')) }}"
           class="btn btn-sm {{ request('categoria') ? 'btn-outline-primary' : 'btn-primary' }}">Todos</a>
        @foreach (config('geventos.categorias') as $key => $label)
            <a href="{{ route('home', array_filter(['q' => request('q'), 'categoria' => $key])) }}"
               class="btn btn-sm {{ request('categoria') === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
        @endforeach
    </div>

    <h2 class="h5 fw-bold mb-3">Próximos eventos</h2>

    <div class="row g-4">
        @forelse ($events as $event)
            @php $from = $event->priceFrom(); @endphp
            <div class="col-sm-6 col-lg-4">
                <a href="{{ route('events.show', $event) }}" class="text-decoration-none text-reset">
                    <div class="card event-card h-100 shadow-sm">
                        <div class="position-relative">
                            @if ($event->cover_path)
                                <img src="{{ asset('storage/' . $event->cover_path) }}" alt="{{ $event->title }}" class="event-cover">
                            @else
                                <div class="event-cover d-grid text-white" style="place-items:center;"><i class="bi bi-music-note-beamed fs-1"></i></div>
                            @endif
                            <div class="date-chip">
                                <div class="d">{{ $event->starts_at->format('d') }}</div>
                                <div class="m">{{ $event->starts_at->translatedFormat('M') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            @if ($event->category)
                                <div class="small text-primary fw-semibold mb-1">{{ config('geventos.categorias.' . $event->category) }}</div>
                            @endif
                            <h3 class="h6 fw-bold mb-1">{{ $event->title }}</h3>
                            <div class="small text-muted mb-2">
                                <i class="bi bi-clock"></i> {{ $event->starts_at->translatedFormat('l d/m') }} · {{ $event->starts_at->format('H:i') }} hs
                                <br><i class="bi bi-geo-alt"></i> {{ $event->venue?->name ?? 'Lugar a confirmar' }}{{ $event->venue?->city ? ', ' . $event->venue->city : '' }}
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0 pb-3 d-flex justify-content-between align-items-center">
                            <span class="small text-muted">{{ $event->organizer->name }}</span>
                            @if ($from !== null)
                                <span class="fw-bold">{{ $from > 0 ? 'Desde $ ' . number_format($from, 0, ',', '.') : 'Gratis' }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="card p-5 text-center text-muted border-0 shadow-sm">
                    <i class="bi bi-calendar2-event fs-1 mb-2"></i>
                    @if (request()->hasAny(['q', 'categoria']))
                        No encontramos eventos con ese filtro.
                    @else
                        Muy pronto vas a ver acá la cartelera de eventos.
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $events->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
