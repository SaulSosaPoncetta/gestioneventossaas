<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Entradas para eventos') · {{ config('geventos.nombre') }}</title>

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
            --bs-link-hover-color: var(--ge-primary-dark);
        }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f6f5fb; }
        .btn-primary { --bs-btn-bg: var(--ge-primary); --bs-btn-border-color: var(--ge-primary);
            --bs-btn-hover-bg: var(--ge-primary-dark); --bs-btn-hover-border-color: var(--ge-primary-dark);
            --bs-btn-active-bg: var(--ge-primary-dark); }
        .btn-outline-primary { --bs-btn-color: var(--ge-primary); --bs-btn-border-color: var(--ge-primary);
            --bs-btn-hover-bg: var(--ge-primary); --bs-btn-hover-border-color: var(--ge-primary); }
        .navbar-ge { background: linear-gradient(90deg, var(--ge-primary-dark), var(--ge-primary)); }
        .brand-mark { font-weight: 800; letter-spacing: -.02em; }
        .brand-mark i { color: var(--ge-accent); }
        .auth-card { max-width: 460px; border: 0; border-radius: 1rem; box-shadow: 0 10px 30px rgba(76, 29, 149, .08); }
        .card { border-radius: .9rem; }
        footer { color: #6b7280; }
    </style>
    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark navbar-ge">
    <div class="container">
        <a class="navbar-brand brand-mark" href="{{ route('home') }}">
            <i class="bi bi-ticket-perforated-fill"></i> {{ config('geventos.nombre') }}
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Eventos</a></li>
            </ul>

            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                @auth
                    @if(auth()->user()->isPlatformAdmin())
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.organizers.index') }}">
                                <i class="bi bi-shield-lock"></i> Administración
                            </a>
                        </li>
                    @endif
                    @if(auth()->user()->organizers()->exists())
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('organizer.dashboard') }}">
                                <i class="bi bi-speedometer2"></i> Panel productora
                            </a>
                        </li>
                    @endif
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('orders.mine') }}"><i class="bi bi-ticket-perforated"></i> Mis compras</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Salir</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('organizer.register') }}">Soy productora</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Ingresar</a></li>
                    <li class="nav-item">
                        <a class="btn btn-warning btn-sm fw-semibold px-3" href="{{ route('register') }}">Crear cuenta</a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<main class="flex-grow-1 py-4">
    <div class="container">
        @foreach (['success' => 'success', 'info' => 'info', 'error' => 'danger'] as $key => $class)
            @if (session($key))
                <div class="alert alert-{{ $class }} alert-dismissible fade show">
                    {{ session($key) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        @endforeach
    </div>

    @yield('content')
</main>

<footer class="py-4 border-top bg-white small">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
        <span>© {{ date('Y') }} {{ config('geventos.nombre') }} · un sistema de MiGestión</span>
        <span>Pagos procesados por Mercado Pago</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
