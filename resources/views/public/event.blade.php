@extends('layouts.app')

@section('title', $event->title)

@php
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
    $user = auth()->user();
    $hasGeneral = $types->contains(fn ($t) => ! $t['seated']);
    $hasSeated = $mapData !== null;
    $oldSeats = array_values(array_map('intval', (array) old('seats', [])));
@endphp

@push('styles')
<style>
    .event-hero { border-radius: 1rem; overflow: hidden; position: relative; min-height: 260px; background: linear-gradient(135deg, #4c1d95, #a855f7); }
    .event-hero img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; }
    .event-hero .overlay { position: relative; background: linear-gradient(0deg, rgba(17, 8, 38, .9), rgba(17, 8, 38, .15)); min-height: 260px; }
    .buy-card { position: sticky; top: 1rem; border: 0; border-radius: 1rem; box-shadow: 0 10px 30px rgba(76, 29, 149, .1); }
    .map-wrap { background: #fff; border: 1px solid #eee; border-radius: .75rem; overflow: auto; max-height: 70vh; min-height: 280px; padding: .5rem; }
    .legend-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; vertical-align: -1px; margin-right: 4px; }
    .type-row + .type-row { border-top: 1px solid #f1f1f4; }
    .description { white-space: pre-line; }
</style>
@endpush

@section('content')
<div class="container">

    {{-- Encabezado --}}
    <div class="event-hero mb-4 text-white">
        @if ($event->cover_path)
            <img src="{{ asset('storage/' . $event->cover_path) }}" alt="{{ $event->title }}">
        @endif
        <div class="overlay d-flex align-items-end p-4 p-md-5">
            <div>
                @if ($event->category)
                    <span class="badge bg-warning text-dark mb-2">{{ config('geventos.categorias.' . $event->category) }}</span>
                @endif
                <h1 class="display-6 fw-bold mb-2">{{ $event->title }}</h1>
                <div class="fs-6 opacity-90">
                    <i class="bi bi-calendar3"></i> {{ ucfirst($event->starts_at->translatedFormat('l d \d\e F \d\e Y')) }}
                    · {{ $event->starts_at->format('H:i') }} hs
                    @if ($event->doors_open_at) <span class="ms-2"><i class="bi bi-door-open"></i> Puertas {{ $event->doors_open_at->format('H:i') }} hs</span> @endif
                </div>
                <div class="fs-6 opacity-90">
                    <i class="bi bi-geo-alt"></i> {{ $event->venue?->name ?? 'Lugar a confirmar' }}
                    @if ($event->venue?->fullAddress()) — {{ $event->venue->fullAddress() }} @endif
                </div>
            </div>
        </div>
    </div>

    @if ($isTeam && $event->status === 'draft')
        <div class="alert alert-warning"><i class="bi bi-eye"></i> Estás viendo la vista previa de un evento en borrador. Solo tu equipo puede verlo.</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @if ($errors->has('checkout'))
                <i class="bi bi-exclamation-triangle"></i> {{ $errors->first('checkout') }}
            @else
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('checkout.store', $event) }}" id="buyForm" novalidate>
        @csrf
        <div id="seatInputs"></div>

        <div class="row g-4">
            {{-- Columna izquierda: mapa y descripción --}}
            <div class="col-lg-7">
                @if ($hasSeated)
                    <div class="card border-0 shadow-sm p-3 mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h2 class="h6 fw-bold mb-0"><i class="bi bi-grid-3x3-gap"></i> Elegí tus butacas</h2>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="zoomOut"><i class="bi bi-zoom-out"></i></button>
                                <button type="button" class="btn btn-outline-secondary" id="zoomFit"><i class="bi bi-arrows-angle-contract"></i></button>
                                <button type="button" class="btn btn-outline-secondary" id="zoomIn"><i class="bi bi-zoom-in"></i></button>
                            </div>
                        </div>
                        <div class="map-wrap" id="seatMap"></div>
                        <div class="d-flex flex-wrap gap-3 small text-muted mt-2">
                            @foreach (collect($mapData['sections'])->whereNotNull('type_id') as $sec)
                                <span><span class="legend-dot" style="background: {{ $sec['color'] }}"></span>{{ $sec['type_name'] }} · {{ $sec['price'] !== null ? $money($sec['price']) : '—' }}</span>
                            @endforeach
                            <span><span class="legend-dot" style="background:#2563eb"></span>Accesible</span>
                            <span><span class="legend-dot" style="background:#d1d5db"></span>Ocupada</span>
                            <span><span class="legend-dot" style="background:#f59e0b"></span>Tu elección</span>
                        </div>
                        <div class="small text-danger mt-1" id="mapMsg"></div>
                    </div>
                @endif

                <div class="card border-0 shadow-sm p-4 mb-4">
                    <h2 class="h6 fw-bold mb-3">Sobre el evento</h2>
                    @if ($event->summary)<p class="lead fs-6">{{ $event->summary }}</p>@endif
                    @if ($event->description)
                        <div class="description text-body-secondary">{{ $event->description }}</div>
                    @elseif (! $event->summary)
                        <p class="text-muted mb-0">Sin descripción.</p>
                    @endif

                    <hr>
                    <div class="row small g-3">
                        <div class="col-sm-6">
                            <div class="text-muted">Organiza</div>
                            <div class="fw-semibold">{{ $event->organizer->name }}</div>
                        </div>
                        @if ($event->venue)
                            <div class="col-sm-6">
                                <div class="text-muted">Lugar</div>
                                <div class="fw-semibold">{{ $event->venue->name }}</div>
                                @if ($event->venue->latitude && $event->venue->longitude)
                                    <a href="https://www.google.com/maps?q={{ $event->venue->latitude }},{{ $event->venue->longitude }}" target="_blank" rel="noopener">
                                        <i class="bi bi-map"></i> Ver en el mapa
                                    </a>
                                @endif
                            </div>
                        @endif
                        @if ($event->is_nominative)
                            <div class="col-12 text-muted"><i class="bi bi-person-badge"></i> Entradas nominativas: cada una se asigna a nombre y DNI del asistente y se controla en el ingreso.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Columna derecha: compra --}}
            <div class="col-lg-5">
                <div class="card buy-card p-4">
                    <h2 class="h5 fw-bold mb-3">Entradas</h2>

                    @if ($saleMessage)
                        <div class="alert alert-info mb-3"><i class="bi bi-info-circle"></i> {{ $saleMessage }}</div>
                    @endif

                    {{-- Tipos de entrada --}}
                    @foreach ($types as $t)
                        <div class="type-row py-3 d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <div class="fw-semibold">
                                    @if ($t['section_color'])<span class="legend-dot" style="background: {{ $t['section_color'] }}"></span>@endif
                                    {{ $t['name'] }}
                                </div>
                                @if ($t['description'])<div class="small text-muted">{{ $t['description'] }}</div>@endif
                                <div class="small">
                                    @if ($t['price'] !== null)
                                        <span class="fw-bold">{{ $t['price'] > 0 ? $money($t['price']) : 'Sin cargo' }}</span>
                                        <span class="text-muted">· {{ $t['lot_name'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-end" style="min-width: 90px;">
                                @if ($t['sold_out'])
                                    <span class="badge text-bg-danger">Agotado</span>
                                @elseif ($t['seated'])
                                    <span class="small text-muted">Elegí en el mapa</span>
                                @elseif ($canBuy)
                                    <select name="quantities[{{ $t['id'] }}]" class="form-select qty-select"
                                            data-price="{{ $t['price'] }}" data-name="{{ $t['name'] }}">
                                        @for ($i = 0; $i <= $t['max']; $i++)
                                            <option value="{{ $i }}" @selected((int) old('quantities.' . $t['id'], 0) === $i)>{{ $i }}</option>
                                        @endfor
                                    </select>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @if ($canBuy)
                        {{-- Resumen --}}
                        <div class="bg-light rounded p-3 my-3">
                            <div id="lines" class="small mb-2"><span class="text-muted">Todavía no elegiste entradas.</span></div>

                            <div class="input-group input-group-sm mb-1">
                                <input type="text" name="coupon" id="couponInput" value="{{ old('coupon') }}" class="form-control text-uppercase" placeholder="Código de descuento">
                                <button type="button" class="btn btn-outline-primary" id="couponBtn">Aplicar</button>
                            </div>
                            <div class="small mb-2" id="couponMsg"></div>

                            <table class="table table-sm table-borderless small mb-0">
                                <tr><td>Subtotal</td><td class="text-end" id="tSubtotal">$ 0,00</td></tr>
                                <tr class="text-success d-none" id="rowDiscount"><td>Descuento</td><td class="text-end" id="tDiscount"></td></tr>
                                <tr><td>Cargo por servicio</td><td class="text-end" id="tFee">$ 0,00</td></tr>
                                <tr class="fs-6 fw-bold border-top"><td>Total</td><td class="text-end" id="tTotal">$ 0,00</td></tr>
                            </table>
                        </div>

                        {{-- Datos del comprador --}}
                        <h3 class="h6 fw-bold">Tus datos</h3>
                        <div class="row g-2 mb-3">
                            <div class="col-12">
                                <input type="text" name="buyer_name" class="form-control @error('buyer_name') is-invalid @enderror"
                                       placeholder="Nombre y apellido" value="{{ old('buyer_name', $user?->name) }}" required>
                            </div>
                            <div class="col-sm-6">
                                <input type="email" name="buyer_email" class="form-control @error('buyer_email') is-invalid @enderror"
                                       placeholder="Email" value="{{ old('buyer_email', $user?->email) }}" required>
                            </div>
                            <div class="col-sm-6">
                                <input type="email" name="buyer_email_confirmation" class="form-control"
                                       placeholder="Repetí el email" value="{{ old('buyer_email_confirmation', $user?->email) }}" required>
                            </div>
                            <div class="col-sm-6">
                                <input type="text" name="buyer_dni" inputmode="numeric" class="form-control @error('buyer_dni') is-invalid @enderror"
                                       placeholder="DNI" value="{{ old('buyer_dni', $user?->dni) }}" required>
                            </div>
                            <div class="col-sm-6">
                                <input type="tel" name="buyer_phone" class="form-control" placeholder="Teléfono (opcional)" value="{{ old('buyer_phone', $user?->phone) }}">
                            </div>
                            <div class="col-12 form-text mt-1">Te enviamos las entradas a este email.</div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" value="1" id="terms" @checked(old('terms'))>
                            <label class="form-check-label small" for="terms">
                                Acepto los términos y condiciones de compra. Las entradas no tienen cambio ni devolución, salvo cancelación del evento.
                            </label>
                        </div>

                        <button class="btn btn-primary btn-lg w-100 fw-semibold" id="buyBtn" disabled>
                            <i class="bi bi-lock-fill"></i> Ir a pagar
                        </button>
                        <div class="text-center small text-muted mt-2">
                            <i class="bi bi-shield-check"></i> Pago seguro con Mercado Pago. Reservamos tus entradas por {{ config('geventos.reserva_minutos') }} minutos.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@if ($canBuy)
@push('scripts')
@if ($hasSeated)
    <script src="{{ asset('js/seatmap.js') }}?v=2"></script>
@endif
<script>
    (function () {
        const cfg = @json($jsConfig);
        const mapData = @json($mapData);
        const fmt = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' });
        const money = (v) => fmt.format(v);
        const round2 = (v) => Math.round(v * 100) / 100;

        const form = document.getElementById('buyForm');
        const selects = Array.from(document.querySelectorAll('.qty-select'));
        const buyBtn = document.getElementById('buyBtn');
        const couponInput = document.getElementById('couponInput');
        const couponMsg = document.getElementById('couponMsg');
        let coupon = null;
        let map = null;

        const sections = {};
        if (mapData) mapData.sections.forEach((s) => { sections[s.id] = s; });

        const generalQty = () => selects.reduce((n, s) => n + Number(s.value), 0);

        if (mapData) {
            const oldSeats = @json($oldSeats);
            map = new SeatMap('#seatMap', mapData, {
                mode: 'select',
                maxSelected: cfg.maxOrder,
                seatState: (seat, section) => (seat.st === 'available' && section.type_id && section.price !== null) ? 'available' : 'unavailable',
                onChange: update,
                onLimit: (n) => {
                    document.getElementById('mapMsg').textContent = `Podés elegir hasta ${n} butacas en esta compra.`;
                },
            });
            if (oldSeats.length) map.setSelected(oldSeats.filter((id) => map.items.has(id)));
            document.getElementById('zoomIn').addEventListener('click', () => map.zoomIn());
            document.getElementById('zoomOut').addEventListener('click', () => map.zoomOut());
            document.getElementById('zoomFit').addEventListener('click', () => map.fit());
        }

        selects.forEach((s) => s.addEventListener('change', () => {
            const seats = map ? map.getSelected().length : 0;
            if (generalQty() + seats > cfg.maxOrder) {
                alert(`Podés comprar hasta ${cfg.maxOrder} entradas por compra.`);
                s.value = Math.max(0, cfg.maxOrder - seats - (generalQty() - Number(s.value)));
            }
            update();
        }));

        function update() {
            const lines = [];

            selects.forEach((s) => {
                const qty = Number(s.value);
                if (qty > 0) lines.push({ label: `${s.dataset.name} x${qty}`, amount: qty * Number(s.dataset.price) });
            });

            const seats = map ? map.getSelectedSeats() : [];
            seats.forEach((seat) => {
                const sec = sections[seat.sectionId];
                lines.push({ label: `${sec.type_name} · Fila ${seat.r} Butaca ${seat.n}`, amount: Number(sec.price) });
            });

            if (map) {
                map.opts.maxSelected = Math.max(0, cfg.maxOrder - generalQty());
                document.getElementById('mapMsg').textContent = '';
            }

            // Butacas elegidas como inputs ocultos
            const box = document.getElementById('seatInputs');
            box.innerHTML = '';
            seats.forEach((seat) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'seats[]';
                input.value = seat.i;
                box.appendChild(input);
            });

            const count = generalQty() + seats.length;
            const subtotal = round2(lines.reduce((n, l) => n + l.amount, 0));
            let discount = 0;
            if (coupon && count >= (coupon.min_tickets || 1)) {
                discount = coupon.type === 'percent' ? round2(subtotal * coupon.value / 100) : Math.min(coupon.value, subtotal);
            }
            const base = round2(subtotal - discount);
            const fee = round2(base * cfg.fee / 100);

            document.getElementById('lines').innerHTML = lines.length
                ? lines.map((l) => `<div class="d-flex justify-content-between"><span>${l.label}</span><span>${money(l.amount)}</span></div>`).join('')
                : '<span class="text-muted">Todavía no elegiste entradas.</span>';
            document.getElementById('tSubtotal').textContent = money(subtotal);
            document.getElementById('rowDiscount').classList.toggle('d-none', discount <= 0);
            document.getElementById('tDiscount').textContent = '- ' + money(discount);
            document.getElementById('tFee').textContent = money(fee);
            document.getElementById('tTotal').textContent = money(base + fee);

            buyBtn.disabled = count === 0;
        }

        // Cupón
        async function applyCoupon() {
            const code = couponInput.value.trim();
            coupon = null;
            couponMsg.textContent = '';
            if (!code) { update(); return; }

            try {
                const res = await fetch(cfg.couponUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ code, quantity: generalQty() + (map ? map.getSelected().length : 0) }),
                });
                const data = await res.json();
                couponMsg.className = 'small mb-2 ' + (data.ok ? 'text-success' : 'text-danger');
                couponMsg.textContent = data.message || 'No se pudo validar el cupón.';
                if (data.ok) { coupon = data; couponInput.value = data.code; }
            } catch (e) {
                couponMsg.className = 'small mb-2 text-danger';
                couponMsg.textContent = 'No se pudo validar el cupón. Igual se verifica al pagar.';
            }
            update();
        }

        document.getElementById('couponBtn').addEventListener('click', applyCoupon);
        couponInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); } });

        form.addEventListener('submit', () => {
            buyBtn.disabled = true;
            buyBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Reservando...';
        });

        if (couponInput.value.trim()) applyCoupon(); else update();
    })();
</script>
@endpush
@endif
