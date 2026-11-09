<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1e1338">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Escáner · {{ config('geventos.nombre') }}</title>
    <link rel="manifest" href="/escaner.webmanifest">
    <link rel="icon" href="/img/escaner-192.png">
    <link rel="apple-touch-icon" href="/img/escaner-192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --bg: #1e1338; --accent: #f59e0b; --primary: #6d28d9; }
        html, body { height: 100%; }
        body { background: var(--bg); color: #fff; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; overscroll-behavior: none; }
        .screen { min-height: 100%; padding: max(env(safe-area-inset-top), 12px) 14px 90px; }
        .brand { font-weight: 800; letter-spacing: -.02em; }
        .brand i { color: var(--accent); }
        .card-dark { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); border-radius: 1rem; }
        .form-control, .form-select { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.15); color: #fff; }
        .form-control:focus { background: rgba(255,255,255,.12); color: #fff; }
        .form-control::placeholder { color: rgba(255,255,255,.45); }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .text-soft { color: rgba(255,255,255,.6); }
        .event-item { cursor: pointer; }
        .event-item:hover { background: rgba(255,255,255,.1); }

        #reader { width: 100%; max-width: 460px; margin: 0 auto; border-radius: 1rem; overflow: hidden; background: #000; min-height: 260px; }
        #reader video { object-fit: cover; }
        .topbar { display: flex; align-items: center; gap: .5rem; }
        .pill { font-size: .75rem; padding: .25rem .6rem; border-radius: 999px; background: rgba(255,255,255,.1); white-space: nowrap; }
        .pill.online { background: #166534; }
        .pill.offline { background: #9a3412; }
        .counter { font-size: 2rem; font-weight: 800; line-height: 1; }

        #result { position: fixed; inset: 0; display: none; flex-direction: column; align-items: center; justify-content: center;
                  text-align: center; padding: 24px; z-index: 50; }
        #result.show { display: flex; }
        #result.ok { background: #15803d; }
        #result.warn { background: #b45309; }
        #result.bad { background: #b91c1c; }
        #result .icon { font-size: 6rem; line-height: 1; }
        #result .title { font-size: 2rem; font-weight: 800; margin-top: .5rem; }
        #result .detail { font-size: 1.15rem; margin-top: .75rem; opacity: .95; }
        #result .seat { font-size: 1.5rem; font-weight: 700; margin-top: .5rem; background: rgba(0,0,0,.2); padding: .3rem .9rem; border-radius: .6rem; }
        #result .hint { position: absolute; bottom: 28px; opacity: .75; font-size: .9rem; }

        .bottombar { position: fixed; bottom: 0; left: 0; right: 0; background: #140c27; border-top: 1px solid rgba(255,255,255,.08);
                     display: flex; padding: 8px 6px max(env(safe-area-inset-bottom), 8px); z-index: 40; }
        .bottombar button { flex: 1; background: none; border: 0; color: rgba(255,255,255,.7); font-size: .75rem; }
        .bottombar button i { display: block; font-size: 1.35rem; }
        .bottombar button.active { color: var(--accent); }
        .ticket-row { border-bottom: 1px solid rgba(255,255,255,.08); padding: .6rem 0; }
        #toast { position: fixed; left: 50%; transform: translateX(-50%); bottom: 90px; z-index: 60; display: none; max-width: 92%; }
    </style>
</head>
<body>

{{-- ======================= Login ======================= --}}
<section id="screen-login" class="screen d-none">
    <div class="mx-auto" style="max-width: 380px; padding-top: 8vh;">
        <div class="text-center mb-4">
            <img src="/img/escaner-192.png" alt="" width="72" height="72" class="rounded-4 mb-3">
            <div class="brand fs-3"><i class="bi bi-qr-code-scan"></i> Escáner</div>
            <div class="text-soft small">Control de ingreso · {{ config('geventos.nombre') }}</div>
        </div>
        <form id="loginForm" class="card-dark p-4">
            <div class="mb-3">
                <label class="form-label small">Email</label>
                <input type="email" name="email" class="form-control" autocomplete="username" required>
            </div>
            <div class="mb-3">
                <label class="form-label small">Contraseña</label>
                <input type="password" name="password" class="form-control" autocomplete="current-password" required>
            </div>
            <div class="text-danger small mb-2" id="loginError"></div>
            <button class="btn btn-primary w-100 py-2 fw-semibold">Ingresar</button>
        </form>
        <p class="text-soft small text-center mt-3">Usá el mismo usuario del panel de la productora.</p>
    </div>
</section>

{{-- ======================= Eventos ======================= --}}
<section id="screen-events" class="screen d-none">
    <div class="topbar mb-3">
        <div class="brand fs-5 flex-grow-1"><i class="bi bi-qr-code-scan"></i> Eventos</div>
        <span class="pill net-pill">…</span>
        <button class="btn btn-sm btn-outline-light" id="btnLogout" title="Salir"><i class="bi bi-box-arrow-right"></i></button>
    </div>
    <div class="text-soft small mb-2" id="userName"></div>
    <div id="eventsList" class="d-grid gap-2"></div>
    <div id="eventsOffline" class="d-none">
        <div class="alert alert-warning small">Sin conexión. Podés abrir un evento que ya hayas descargado.</div>
    </div>
</section>

{{-- ======================= Escáner ======================= --}}
<section id="screen-scan" class="screen d-none">
    <div class="topbar mb-2">
        <button class="btn btn-sm btn-outline-light" id="btnBack"><i class="bi bi-chevron-left"></i></button>
        <div class="flex-grow-1 text-truncate fw-semibold" id="eventTitle"></div>
        <span class="pill net-pill">…</span>
    </div>

    {{-- Pestaña: escanear --}}
    <div id="tab-scan">
        <div class="d-flex justify-content-between align-items-end mb-2">
            <div>
                <div class="text-soft small">Ingresaron</div>
                <div class="counter"><span id="cUsed">0</span><span class="fs-5 text-soft"> / <span id="cTotal">0</span></span></div>
            </div>
            <div class="text-end small">
                <div class="text-soft">Puerta: <span id="gateLabel" class="text-white fw-semibold">—</span></div>
                <div><span class="pill" id="queuePill">0 sin sincronizar</span></div>
            </div>
        </div>

        <div id="reader"></div>
        <div class="text-center text-soft small mt-2" id="cameraMsg">Apuntá la cámara al QR de la entrada</div>

        <form id="manualForm" class="d-flex gap-2 mt-3">
            <input type="text" name="code" class="form-control text-uppercase" placeholder="Código manual (ej: ABCD2345EF)" autocomplete="off" autocapitalize="characters">
            <button class="btn btn-outline-light"><i class="bi bi-check2"></i></button>
        </form>
    </div>

    {{-- Pestaña: buscar --}}
    <div id="tab-search" class="d-none">
        <input type="search" id="searchInput" class="form-control mb-2" placeholder="Buscar por nombre, DNI o código...">
        <div id="searchResults" class="small"></div>
    </div>

    {{-- Pestaña: ajustes --}}
    <div id="tab-settings" class="d-none">
        <div class="card-dark p-3 mb-3">
            <label class="form-label small">Nombre de la puerta / acceso</label>
            <input type="text" id="gateInput" class="form-control mb-2" maxlength="40" placeholder="Ej: Puerta 1, VIP, Campo">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="forceOffline">
                <label class="form-check-label small" for="forceOffline">Trabajar siempre sin conexión (valida en el celular y sincroniza después)</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="soundOn" checked>
                <label class="form-check-label small" for="soundOn">Sonido y vibración</label>
            </div>
        </div>
        <div class="card-dark p-3 mb-3 small">
            <div class="d-flex justify-content-between"><span class="text-soft">Listado descargado</span><span id="pkgTime">—</span></div>
            <div class="d-flex justify-content-between"><span class="text-soft">Última sincronización</span><span id="syncTime">—</span></div>
            <div class="d-flex justify-content-between"><span class="text-soft">Entradas en el celular</span><span id="pkgCount">0</span></div>
            <button class="btn btn-sm btn-outline-light w-100 mt-3" id="btnSyncNow"><i class="bi bi-arrow-repeat"></i> Sincronizar ahora</button>
            <button class="btn btn-sm btn-outline-light w-100 mt-2" id="btnRedownload"><i class="bi bi-cloud-download"></i> Volver a descargar el listado</button>
        </div>
        <div class="text-soft small">
            Con conexión, cada escaneo se valida en el servidor (así una entrada no puede ingresar por dos puertas).
            Sin conexión, se valida en el celular con la firma del QR y el listado descargado, y se sincroniza al volver la red.
        </div>
    </div>

    <nav class="bottombar">
        <button data-tab="scan" class="active"><i class="bi bi-qr-code-scan"></i>Escanear</button>
        <button data-tab="search"><i class="bi bi-search"></i>Buscar</button>
        <button data-tab="settings"><i class="bi bi-gear"></i>Ajustes</button>
    </nav>
</section>

{{-- Resultado a pantalla completa --}}
<div id="result">
    <div class="icon"><i class="bi"></i></div>
    <div class="title"></div>
    <div class="seat d-none"></div>
    <div class="detail"></div>
    <div class="hint">Tocá para seguir</div>
</div>

<div id="toast" class="alert mb-0 py-2 px-3 small"></div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="/js/escaner.js?v=1"></script>
</body>
</html>
