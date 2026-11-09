@extends('layouts.panel')

@section('title', $event->title)

@php
    [$stLabel, $stColor] = config('geventos.estados_evento.' . $event->status);
    $money = fn ($v) => '$ ' . number_format((float) $v, 2, ',', '.');
    $dt = fn ($d) => $d?->format('Y-m-d\TH:i');

    // Estado visible de un lote
    $lotStatus = function ($lot, $type, $freeSeats) {
        if ($lot->status === 'paused') return ['Pausado', 'secondary'];
        if ($lot->starts_at && $lot->starts_at->isFuture()) return ['Programado', 'info'];
        if ($lot->ends_at && $lot->ends_at->isPast()) return ['Vencido', 'dark'];
        if ($type->isSeated() ? $freeSeats === 0 : ! $lot->hasStock()) return ['Agotado', 'danger'];
        return ['En venta', 'success'];
    };

    $typeModals = collect([null])->concat($event->ticketTypes);
@endphp

@section('content')
<div class="mb-3">
    <a href="{{ route('organizer.events.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Eventos</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- Encabezado --}}
<div class="card p-4 mb-4">
    <div class="row g-4 align-items-center">
        <div class="col-md-3">
            @if ($event->cover_path)
                <img src="{{ asset('storage/' . $event->cover_path) }}" alt="" class="img-fluid rounded" style="aspect-ratio: 16/9; object-fit: cover; width: 100%;">
            @else
                <div class="rounded bg-light d-grid text-muted" style="aspect-ratio: 16/9; place-items: center;">
                    <i class="bi bi-image fs-1"></i>
                </div>
            @endif
        </div>
        <div class="col-md-9">
            <div class="d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <span class="badge text-bg-{{ $stColor }} mb-2">{{ $stLabel }}</span>
                    <h1 class="h4 fw-bold mb-1">{{ $event->title }}</h1>
                    <div class="text-muted">
                        <i class="bi bi-calendar3"></i> {{ $event->starts_at->translatedFormat('l d/m/Y') }} · {{ $event->starts_at->format('H:i') }} hs
                        @if ($event->doors_open_at) <span class="ms-2"><i class="bi bi-door-open"></i> Puertas {{ $event->doors_open_at->format('H:i') }}</span> @endif
                    </div>
                    <div class="text-muted">
                        <i class="bi bi-geo-alt"></i> {{ $event->venue?->name ?? 'Lugar a definir' }}
                        @if ($event->venue?->fullAddress()) <span class="small">— {{ $event->venue->fullAddress() }}</span> @endif
                    </div>
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        @if ($event->category)
                            <span class="badge text-bg-light border">{{ config('geventos.categorias.' . $event->category) }}</span>
                        @endif
                        @if ($event->is_seated) <span class="badge text-bg-light border"><i class="bi bi-grid-3x3-gap"></i> Numerado</span> @endif
                        @if ($event->is_nominative) <span class="badge text-bg-light border"><i class="bi bi-person-badge"></i> Nominativo</span> @endif
                        @if ($event->allow_transfers) <span class="badge text-bg-light border"><i class="bi bi-arrow-left-right"></i> Transferible</span> @endif
                        <span class="badge text-bg-light border">Máx. {{ $event->max_tickets_per_order }} por compra</span>
                    </div>
                </div>

                {{-- Acciones --}}
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <a href="{{ route('events.show', $event) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Ver página</a>
                    <a href="{{ route('organizer.events.edit', $event) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                    @if ($event->is_seated && $event->venue)
                        <a href="{{ route('organizer.events.seats', $event) }}" class="btn btn-outline-success"><i class="bi bi-grid-3x3-gap"></i> Butacas</a>
                    @endif
                    <a href="{{ route('organizer.events.checkins', $event) }}" class="btn btn-outline-dark"><i class="bi bi-qr-code-scan"></i> Ingresos</a>

                    @php
                        $actions = [];
                        if (in_array($event->status, ['draft', 'paused'], true)) $actions['publish'] = ['Publicar', 'btn-success', 'bi-broadcast', null];
                        if ($event->status === 'published') $actions['pause'] = ['Pausar venta', 'btn-warning', 'bi-pause-circle', null];
                        if (in_array($event->status, ['published', 'paused'], true) && $stats['orders'] === 0) $actions['draft'] = ['A borrador', 'btn-outline-secondary', 'bi-pencil-square', null];
                        if (! in_array($event->status, ['cancelled', 'finished'], true)) $actions['cancel'] = ['Cancelar evento', 'btn-outline-danger', 'bi-x-circle', '¿Seguro que querés cancelar el evento? Esta acción no se puede deshacer.'];
                    @endphp

                    @foreach ($actions as $action => [$label, $btn, $icon, $confirm])
                        <form method="POST" action="{{ route('organizer.events.status', $event) }}"
                              @if ($confirm) onsubmit="return confirm(@js($confirm))" @endif>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="action" value="{{ $action }}">
                            <button class="btn {{ $btn }}"><i class="bi {{ $icon }}"></i> {{ $label }}</button>
                        </form>
                    @endforeach

                    @if ($event->orders()->doesntExist())
                        <form method="POST" action="{{ route('organizer.events.destroy', $event) }}"
                              onsubmit="return confirm('¿Eliminar definitivamente este evento?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Indicadores --}}
<div class="row g-3 mb-4">
    @foreach ([
        ['Entradas vendidas', number_format($stats['sold'], 0, ',', '.'), 'bi-ticket-perforated'],
        ['Órdenes pagas', number_format($stats['orders'], 0, ',', '.'), 'bi-receipt'],
        ['Recaudación', $money($stats['revenue']), 'bi-cash-coin'],
        ['Ingresaron', number_format($stats['checked_in'], 0, ',', '.') . ($stats['sold'] ? ' (' . round($stats['checked_in'] * 100 / $stats['sold']) . '%)' : ''), 'bi-qr-code-scan'],
    ] as [$label, $value, $icon])
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 h-100">
                <div class="text-muted small"><i class="bi {{ $icon }}"></i> {{ $label }}</div>
                <div class="fs-5 fw-bold">{{ $value }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    {{-- Tipos de entrada y lotes --}}
    <div class="col-xl-8">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">Tipos de entrada y lotes</h2>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#typeModal-new">
                    <i class="bi bi-plus-lg"></i> Tipo de entrada
                </button>
            </div>

            @forelse ($event->ticketTypes as $type)
                @php
                    $sold = (int) ($soldByType[$type->id] ?? 0);
                    $seatInfo = $seatCounts[$type->id] ?? [];
                    $freeSeats = $type->isSeated() ? ($seatInfo['available'] ?? 0) : null;
                    $current = $type->currentLot();
                @endphp
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <div>
                            <span class="fw-bold">{{ $type->name }}</span>
                            @unless ($type->is_visible) <span class="badge text-bg-light border ms-1"><i class="bi bi-eye-slash"></i> Oculto</span> @endunless
                            <div class="small text-muted">
                                @if ($type->section)
                                    <span class="d-inline-block rounded me-1" style="width:10px;height:10px;background:{{ $type->section->color }}"></span>
                                    {{ $type->section->name }} · {{ $type->isSeated() ? 'numerado' : 'general' }}
                                @else
                                    Sin sector asignado
                                @endif
                                · {{ $sold }} vendidas
                                @if ($type->isSeated())
                                    · {{ $freeSeats }} butacas libres
                                    @if (($seatInfo['blocked'] ?? 0) > 0) · {{ $seatInfo['blocked'] }} bloqueadas @endif
                                @endif
                                @if ($type->max_per_order) · máx. {{ $type->max_per_order }} por compra @endif
                            </div>
                            @if ($type->description)<div class="small">{{ $type->description }}</div>@endif
                        </div>
                        <div class="d-flex gap-1 align-items-start">
                            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#lotModal-new-{{ $type->id }}">
                                <i class="bi bi-plus-lg"></i> Lote
                            </button>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#typeModal-{{ $type->id }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('organizer.events.types.destroy', [$event, $type]) }}"
                                  onsubmit="return confirm('¿Eliminar el tipo «{{ $type->name }}» y sus lotes?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="small text-muted">
                                <tr>
                                    <th>Lote</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-center">Vendidas / cupo</th>
                                    <th>Vigencia</th>
                                    <th class="text-center">Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($type->lots as $lot)
                                    @php [$lsLabel, $lsColor] = $lotStatus($lot, $type, $freeSeats); @endphp
                                    <tr @class(['table-success' => $current && $current->id === $lot->id])>
                                        <td>
                                            {{ $lot->name }}
                                            @if ($current && $current->id === $lot->id)
                                                <span class="badge text-bg-success ms-1">Vigente</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">{{ (float) $lot->price > 0 ? $money($lot->price) : 'Gratis' }}</td>
                                        <td class="text-center small">
                                            {{ $lot->sold }}
                                            @if ($lot->reserved) <span class="text-warning">(+{{ $lot->reserved }} en pago)</span> @endif
                                            / {{ $lot->quantity ?? ($type->isSeated() ? 'butacas' : '∞') }}
                                        </td>
                                        <td class="small text-muted">
                                            {{ $lot->starts_at?->format('d/m H:i') ?? 'Desde ya' }} → {{ $lot->ends_at?->format('d/m H:i') ?? 'sin fin' }}
                                        </td>
                                        <td class="text-center"><span class="badge text-bg-{{ $lsColor }}">{{ $lsLabel }}</span></td>
                                        <td class="text-end text-nowrap">
                                            <button class="btn btn-sm btn-link p-0 me-2" data-bs-toggle="modal" data-bs-target="#lotModal-{{ $lot->id }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('organizer.events.lots.destroy', [$event, $lot]) }}" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar el lote «{{ $lot->name }}»?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-4">
                    <i class="bi bi-ticket-perforated fs-2 d-block mb-2"></i>
                    Todavía no hay tipos de entrada. Creá el primero (General, VIP, Platea...) con su lote de precio.
                </div>
            @endforelse

            <div class="small text-muted">
                <i class="bi bi-info-circle"></i> Se vende el primer lote <strong>vigente</strong> de cada tipo (activo, dentro de sus fechas y con cupo).
                Así armás preventas: cuando un lote se agota o vence, pasa solo al siguiente.
            </div>
        </div>
    </div>

    {{-- Datos de venta --}}
    <div class="col-xl-4">
        <div class="card p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Venta</h2>
            <dl class="row small mb-0">
                <dt class="col-6 text-muted fw-normal">Inicio de venta</dt>
                <dd class="col-6 text-end">{{ $event->sales_start_at?->format('d/m/Y H:i') ?? 'Al publicar' }}</dd>
                <dt class="col-6 text-muted fw-normal">Fin de venta</dt>
                <dd class="col-6 text-end">{{ $event->sales_end_at?->format('d/m/Y H:i') ?? 'Hasta el inicio' }}</dd>
                <dt class="col-6 text-muted fw-normal">Cargo por servicio</dt>
                <dd class="col-6 text-end">{{ rtrim(rtrim(number_format($event->effectiveServiceFee(), 2, ',', ''), '0'), ',') }}%</dd>
                <dt class="col-6 text-muted fw-normal">Capacidad</dt>
                <dd class="col-6 text-end">{{ $event->capacity ? number_format($event->capacity, 0, ',', '.') : '—' }}</dd>
                <dt class="col-6 text-muted fw-normal">A la venta ahora</dt>
                <dd class="col-6 text-end mb-0">
                    @if ($event->isOnSale())
                        <span class="text-success fw-semibold">Sí</span>
                    @else
                        <span class="text-muted">No</span>
                    @endif
                </dd>
            </dl>
        </div>

        <div class="card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Cupones</h2>
                <a href="{{ route('organizer.coupons.create', ['event' => $event->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i></a>
            </div>
            @php $coupons = $event->organizer->coupons()->where(fn ($q) => $q->whereNull('event_id')->orWhere('event_id', $event->id))->where('is_active', true)->get(); @endphp
            @forelse ($coupons as $coupon)
                <div class="d-flex justify-content-between small py-1 border-bottom">
                    <code>{{ $coupon->code }}</code>
                    <span>
                        {{ $coupon->type === 'percent' ? rtrim(rtrim(number_format($coupon->value, 2, ',', ''), '0'), ',') . '%' : $money($coupon->value) }}
                        @unless ($coupon->event_id) <span class="text-muted">(todos)</span> @endunless
                    </span>
                </div>
            @empty
                <p class="small text-muted mb-0">Sin cupones activos para este evento.</p>
            @endforelse
        </div>

        @if ($event->summary)
            <div class="card p-4">
                <h2 class="h6 fw-bold mb-2">Resumen</h2>
                <p class="small text-muted mb-0">{{ $event->summary }}</p>
            </div>
        @endif
    </div>
</div>

{{-- ============ Modales de tipo de entrada ============ --}}
@foreach ($typeModals as $t)
    @php $key = $t ? $t->id : 'new'; @endphp
    <div class="modal fade" id="typeModal-{{ $key }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content type-form"
                  action="{{ $t ? route('organizer.events.types.update', [$event, $t]) : route('organizer.events.types.store', $event) }}">
                @csrf
                @if ($t) @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ $t ? 'Editar tipo de entrada' : 'Nuevo tipo de entrada' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" class="form-control" maxlength="80" required value="{{ $t?->name }}" placeholder="Ej: General, VIP, Platea A">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción <span class="text-muted small">(opcional)</span></label>
                            <input type="text" name="description" class="form-control" maxlength="255" value="{{ $t?->description }}" placeholder="Ej: Incluye consumición">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Sector del lugar</label>
                            <select name="venue_section_id" class="form-select section-select">
                                <option value="" data-seated="0">Sin sector</option>
                                @foreach ($sections as $sec)
                                    <option value="{{ $sec->id }}" data-seated="{{ $sec->isSeated() ? 1 : 0 }}"
                                            @selected($t && $t->venue_section_id === $sec->id)
                                            @disabled($sec->isSeated() && ! $event->is_seated)>
                                        {{ $sec->name }} — {{ $sec->isSeated() ? 'numerado (' . $sec->capacity . ' butacas)' : 'general (' . $sec->capacity . ' lugares)' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Con un sector numerado el comprador elige su butaca y el cupo lo dan las butacas.
                                @if (! $event->is_seated && $sections->contains(fn ($s) => $s->isSeated()))
                                    Para usarlos activá "Butacas numeradas" en el evento.
                                @endif
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Máx. por compra</label>
                            <input type="number" name="max_per_order" min="1" max="{{ config('geventos.max_entradas_por_orden') }}" class="form-control"
                                   value="{{ $t?->max_per_order }}" placeholder="Del evento">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Orden</label>
                            <input type="number" name="sort" min="0" max="999" class="form-control" value="{{ $t?->sort }}">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_visible" value="1" id="vis-{{ $key }}"
                                       @checked($t ? $t->is_visible : true)>
                                <label class="form-check-label" for="vis-{{ $key }}">Visible en la página del evento</label>
                            </div>
                        </div>

                        @unless ($t)
                            <div class="col-12"><hr class="my-1"><div class="fw-semibold small">Primer lote</div></div>
                            <div class="col-12">
                                <label class="form-label">Nombre del lote</label>
                                <input type="text" name="lot_name" class="form-control" maxlength="80" required value="Preventa 1">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Precio ($)</label>
                                <input type="number" name="lot_price" min="0" step="0.01" class="form-control" required placeholder="0 = gratis">
                            </div>
                            <div class="col-6 lot-qty">
                                <label class="form-label">Cupo</label>
                                <input type="number" name="lot_quantity" min="1" class="form-control">
                            </div>
                        @endunless
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

{{-- ============ Modales de lotes (alta por tipo y edición) ============ --}}
@foreach ($event->ticketTypes as $type)
    @foreach (collect([null])->concat($type->lots) as $l)
        @php $key = $l ? $l->id : 'new-' . $type->id; @endphp
        <div class="modal fade" id="lotModal-{{ $key }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" class="modal-content"
                      action="{{ $l ? route('organizer.events.lots.update', [$event, $l]) : route('organizer.events.lots.store', [$event, $type]) }}">
                    @csrf
                    @if ($l) @method('PUT') @endif
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $l ? 'Editar lote' : 'Nuevo lote' }} · {{ $type->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="name" class="form-control" maxlength="80" required
                                       value="{{ $l?->name ?? 'Preventa ' . ($type->lots->count() + 1) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Precio ($)</label>
                                <input type="number" name="price" min="0" step="0.01" class="form-control" required value="{{ $l?->price }}">
                            </div>
                            @unless ($type->isSeated())
                                <div class="col-6">
                                    <label class="form-label">Cupo</label>
                                    <input type="number" name="quantity" min="1" class="form-control" required value="{{ $l?->quantity }}">
                                    @if ($l && ($l->sold + $l->reserved) > 0)
                                        <div class="form-text">Mínimo {{ $l->sold + $l->reserved }} (ya vendidas o en pago).</div>
                                    @endif
                                </div>
                            @endunless
                            <div class="col-6">
                                <label class="form-label">Desde <span class="text-muted small">(opcional)</span></label>
                                <input type="datetime-local" name="starts_at" class="form-control" value="{{ $dt($l?->starts_at) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Hasta <span class="text-muted small">(opcional)</span></label>
                                <input type="datetime-local" name="ends_at" class="form-control" value="{{ $dt($l?->ends_at) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-select">
                                    <option value="active" @selected(! $l || $l->status !== 'paused')>Activo</option>
                                    <option value="paused" @selected($l?->status === 'paused')>Pausado</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Orden</label>
                                <input type="number" name="sort" min="0" max="999" class="form-control" value="{{ $l?->sort }}">
                            </div>
                            @if ($type->isSeated())
                                <div class="col-12 form-text">Tipo numerado: el cupo lo dan las butacas del sector «{{ $type->section->name }}».</div>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endforeach
@endsection

@push('scripts')
<script>
    // En el alta de tipo: el cupo del primer lote solo aplica a sectores no numerados
    document.querySelectorAll('.type-form').forEach((form) => {
        const select = form.querySelector('.section-select');
        const qty = form.querySelector('.lot-qty');
        if (!select || !qty) return;
        const sync = () => {
            const seated = select.selectedOptions[0]?.dataset.seated === '1';
            qty.classList.toggle('d-none', seated);
            qty.querySelector('input').required = !seated;
        };
        select.addEventListener('change', sync);
        sync();
    });
</script>
@endpush
