<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · {{ $currentOrganizer->name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --ge-primary: #6d28d9;
            --ge-primary-dark: #4c1d95;
            --ge-accent: #f59e0b;
            --bs-primary: var(--ge-primary);
            --bs-primary-rgb: 109, 40, 217;
            --bs-link-color: var(--ge-primary);
        }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f6f5fb; }
        .btn-primary { --bs-btn-bg: var(--ge-primary); --bs-btn-border-color: var(--ge-primary);
            --bs-btn-hover-bg: var(--ge-primary-dark); --bs-btn-hover-border-color: var(--ge-primary-dark); }
        .sidebar { width: 250px; background: #1e1338; min-height: 100vh; }
        .sidebar .brand { font-weight: 800; color: #fff; text-decoration: none; letter-spacing: -.02em; }
        .sidebar .brand i { color: var(--ge-accent); }
        .sidebar .nav-link { color: #c4b5fd; border-radius: .5rem; padding: .55rem .8rem; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,.06); }
        .sidebar .nav-link.active { color: #fff; background: var(--ge-primary); }
        .sidebar .nav-link.disabled { color: #6b5f8a; }
        .sidebar .section-title { color: #8b7fb0; font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; }
        .card { border: 0; border-radius: .9rem; box-shadow: 0 4px 16px rgba(30, 19, 56, .05); }
        .stat-icon { width: 44px; height: 44px; border-radius: .7rem; display: grid; place-items: center; font-size: 1.3rem; }
        @media (max-width: 991.98px) { .sidebar { width: 100%; min-height: auto; } }
    </style>
    @stack('styles')
</head>
<body>
<div class="d-lg-flex">

    {{-- Barra lateral --}}
    <aside class="sidebar p-3 d-flex flex-column">
        <a href="{{ route('organizer.dashboard') }}" class="brand fs-5 mb-3">
            <i class="bi bi-ticket-perforated-fill"></i> {{ config('geventos.nombre') }}
        </a>

        {{-- Selector de productora --}}
        @if ($myOrganizers->count() > 1)
            <form method="POST" action="{{ route('organizer.switch') }}" class="mb-3">
                @csrf
                <select name="organizer_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach ($myOrganizers as $org)
                        <option value="{{ $org->id }}" @selected($org->id === $currentOrganizer->id)>{{ $org->name }}</option>
                    @endforeach
                </select>
            </form>
        @else
            <div class="text-white-50 small mb-3"><i class="bi bi-building"></i> {{ $currentOrganizer->name }}</div>
        @endif

        <div class="section-title mb-2">General</div>
        <nav class="nav flex-column gap-1 mb-3">
            <a class="nav-link @if(request()->routeIs('organizer.dashboard')) active @endif" href="{{ route('organizer.dashboard') }}">
                <i class="bi bi-speedometer2 me-2"></i>Inicio
            </a>
            @if ($currentRole !== 'door')
                <a class="nav-link @if(request()->routeIs('organizer.events.*')) active @endif" href="{{ route('organizer.events.index') }}">
                    <i class="bi bi-calendar-event me-2"></i>Eventos
                </a>
                <a class="nav-link @if(request()->routeIs('organizer.venues.*')) active @endif" href="{{ route('organizer.venues.index') }}">
                    <i class="bi bi-geo-alt me-2"></i>Lugares y butacas
                </a>
                <a class="nav-link @if(request()->routeIs('organizer.coupons.*')) active @endif" href="{{ route('organizer.coupons.index') }}">
                    <i class="bi bi-percent me-2"></i>Cupones
                </a>
            @endif
        </nav>

        <div class="section-title mb-2">Ventas</div>
        <nav class="nav flex-column gap-1 mb-3">
            @if ($currentRole !== 'door')
                <a class="nav-link @if(request()->routeIs('organizer.orders.*')) active @endif" href="{{ route('organizer.orders.index') }}">
                    <i class="bi bi-receipt me-2"></i>Órdenes
                </a>
            @endif
            <a class="nav-link" href="{{ url('/escaner') }}" target="_blank"><i class="bi bi-qr-code-scan me-2"></i>Escáner de ingreso <i class="bi bi-box-arrow-up-right small"></i></a>
            @if ($currentRole !== 'door')
                <a class="nav-link @if(request()->routeIs('organizer.reports.*')) active @endif" href="{{ route('organizer.reports.index') }}">
                    <i class="bi bi-bar-chart me-2"></i>Reportes
                </a>
            @endif
        </nav>

        @if (in_array($currentRole, ['owner', 'admin'], true))
            <div class="section-title mb-2">Configuración</div>
            <nav class="nav flex-column gap-1 mb-3">
                <a class="nav-link @if(request()->routeIs('organizer.team.*')) active @endif" href="{{ route('organizer.team.index') }}">
                    <i class="bi bi-people me-2"></i>Equipo
                </a>
                <a class="nav-link @if(request()->routeIs('organizer.mercadopago.*')) active @endif" href="{{ route('organizer.mercadopago.show') }}">
                    <i class="bi bi-wallet2 me-2"></i>Mercado Pago
                    @unless ($currentOrganizer->isMpConnected())<span class="badge text-bg-warning ms-1">!</span>@endunless
                </a>
            </nav>
        @endif

        @if (auth()->user()->isPlatformAdmin())
            <div class="section-title mb-2">Plataforma</div>
            <nav class="nav flex-column gap-1 mb-3">
                <a class="nav-link" href="{{ route('admin.organizers.index') }}"><i class="bi bi-shield-lock me-2"></i>Administración</a>
            </nav>
        @endif

        <div class="mt-auto pt-3 border-top border-secondary-subtle">
            <div class="text-white small fw-semibold">{{ auth()->user()->name }}</div>
            <div class="text-white-50 small mb-2">{{ ucfirst($currentRole) }}</div>
            <div class="d-flex gap-2">
                <a href="{{ route('home') }}" class="btn btn-sm btn-outline-light"><i class="bi bi-globe"></i> Sitio</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> Salir</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Contenido --}}
    <main class="flex-grow-1 p-3 p-lg-4">
        @if ($currentOrganizer->status === \App\Models\Organizer::STATUS_PENDING)
            <div class="alert alert-warning d-flex align-items-center gap-2">
                <i class="bi bi-hourglass-split fs-5"></i>
                <div>Tu productora está <strong>pendiente de aprobación</strong>. Podés armar tus eventos; la venta se habilita cuando la aprobemos y vincules Mercado Pago.</div>
            </div>
        @elseif ($currentOrganizer->status === \App\Models\Organizer::STATUS_SUSPENDED)
            <div class="alert alert-danger"><i class="bi bi-slash-circle"></i> Tu productora está suspendida. Contactanos para más información.</div>
        @endif

        @foreach (['success' => 'success', 'info' => 'info', 'error' => 'danger'] as $key => $class)
            @if (session($key))
                <div class="alert alert-{{ $class }} alert-dismissible fade show">
                    {{ session($key) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        @endforeach

        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
