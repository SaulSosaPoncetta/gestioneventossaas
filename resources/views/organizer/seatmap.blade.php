@extends('layouts.panel')

@section('title', 'Mapa de butacas · ' . $venue->name)

@php
    $totalSeats = $venue->sections->sum(fn ($s) => $s->isSeated() ? $s->seats->where('is_active', true)->count() : 0);
    $totalGeneral = $venue->sections->where('type', 'general')->sum('capacity');
    // null = modal de alta; un sector = modal de edición
    $sectionModals = collect([null])->concat($venue->sections);
@endphp

@push('styles')
<style>
    .map-wrap { background: #fff; border: 1px solid #eee; border-radius: .75rem; overflow: auto; max-height: 72vh; min-height: 320px; padding: .5rem; }
    .legend-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; vertical-align: -1px; margin-right: 4px; }
    .section-dot { width: 14px; height: 14px; border-radius: 4px; flex: 0 0 14px; }
</style>
@endpush

@section('content')
<div class="mb-3">
    <a href="{{ route('organizer.venues.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Lugares</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Mapa de butacas · {{ $venue->name }}</h1>
        <span class="text-muted">
            {{ number_format($totalSeats, 0, ',', '.') }} butacas numeradas
            @if ($totalGeneral) · {{ number_format($totalGeneral, 0, ',', '.') }} lugares generales @endif
        </span>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sectionModal-new">
        <i class="bi bi-plus-lg"></i> Nuevo sector
    </button>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    {{-- Sectores --}}
    <div class="col-xl-4">
        <div class="card p-3">
            <h2 class="h6 fw-bold mb-3">Sectores</h2>

            @forelse ($venue->sections as $section)
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="section-dot" style="background: {{ $section->color }}"></span>
                        <span class="fw-semibold flex-grow-1">{{ $section->name }}</span>
                        @if ($locked[$section->id] ?? false)
                            <i class="bi bi-lock-fill text-muted" title="En uso en un evento: no se puede borrar ni regenerar"></i>
                        @endif
                    </div>
                    <div class="small text-muted mb-2">
                        @if ($section->isSeated())
                            Numerado · {{ number_format($section->capacity ?? 0, 0, ',', '.') }} butacas
                            · {{ $section->seats->pluck('row_label')->unique()->count() }} filas
                        @else
                            General · {{ number_format($section->capacity ?? 0, 0, ',', '.') }} lugares
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sectionModal-{{ $section->id }}">
                            <i class="bi bi-pencil"></i> Editar
                        </button>
                        @if ($section->isSeated() && ! ($locked[$section->id] ?? false))
                            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#generateModal-{{ $section->id }}">
                                <i class="bi bi-grid-3x3-gap"></i> {{ $section->seats->isEmpty() ? 'Generar butacas' : 'Regenerar' }}
                            </button>
                        @endif
                        @unless ($locked[$section->id] ?? false)
                            <form method="POST" action="{{ route('organizer.venues.map.sections.destroy', [$venue, $section]) }}"
                                  onsubmit="return confirm('¿Eliminar el sector «{{ $section->name }}» y sus butacas?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        @endunless
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-4 small">
                    <i class="bi bi-diagram-3 fs-2 d-block mb-2"></i>
                    Creá el primer sector: un <strong>campo general</strong> (sin numerar) o una <strong>platea numerada</strong>.
                </div>
            @endforelse
        </div>

        <div class="card p-3 mt-3 small text-muted">
            <div class="fw-semibold text-dark mb-1"><i class="bi bi-lightbulb"></i> Cómo editar</div>
            Hacé clic en una butaca para seleccionarla, en la letra de una fila para elegir la fila entera o en el nombre del sector para elegirlo completo.
            Con el mouse también podés arrastrar un rectángulo (con <kbd>Shift</kbd> se suma a lo ya seleccionado).
            Después aplicá la acción: desactivar (pasillos, columnas, butacas rotas), accesible o acompañante.
        </div>
    </div>

    {{-- Mapa --}}
    <div class="col-xl-8">
        <div class="card p-3">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="zoomOut" title="Alejar"><i class="bi bi-zoom-out"></i></button>
                    <button type="button" class="btn btn-outline-secondary" id="zoomFit" title="Ajustar"><i class="bi bi-arrows-angle-contract"></i></button>
                    <button type="button" class="btn btn-outline-secondary" id="zoomIn" title="Acercar"><i class="bi bi-zoom-in"></i></button>
                </div>

                <span class="small text-muted ms-2"><span id="selCount">0</span> seleccionadas</span>
                <button type="button" class="btn btn-sm btn-link" id="selClear">Limpiar</button>

                <form method="POST" action="{{ route('organizer.venues.map.seats.update', $venue) }}" id="bulkForm" class="ms-auto d-flex flex-wrap gap-1">
                    @csrf
                    @method('PATCH')
                    <div id="bulkIds"></div>
                    <button name="action" value="deactivate" class="btn btn-sm btn-outline-secondary bulk-btn" disabled><i class="bi bi-eye-slash"></i> Desactivar</button>
                    <button name="action" value="activate" class="btn btn-sm btn-outline-secondary bulk-btn" disabled><i class="bi bi-eye"></i> Activar</button>
                    <button name="action" value="accessible" class="btn btn-sm btn-outline-primary bulk-btn" disabled><i class="bi bi-person-wheelchair"></i> Accesible</button>
                    <button name="action" value="companion" class="btn btn-sm btn-outline-info bulk-btn" disabled><i class="bi bi-people"></i> Acompañante</button>
                    <button name="action" value="standard" class="btn btn-sm btn-outline-dark bulk-btn" disabled>Común</button>
                </form>
            </div>

            <div class="map-wrap" id="seatMap"></div>

            <div class="d-flex flex-wrap gap-3 small text-muted mt-2">
                <span><span class="legend-dot" style="background:#6d28d9"></span>Común (color del sector)</span>
                <span><span class="legend-dot" style="background:#2563eb"></span>Accesible</span>
                <span><span class="legend-dot" style="background:#0d9488"></span>Acompañante</span>
                <span><span class="legend-dot" style="border:1px dashed #94a3b8"></span>Desactivada</span>
                <span><span class="legend-dot" style="background:#fff;border:2px solid #f59e0b"></span>Seleccionada</span>
            </div>
        </div>
    </div>
</div>

{{-- ============ Modales de sector (alta y edición) ============ --}}
@foreach ($sectionModals as $s)
    @php
        $key = $s ? $s->id : 'new';
        $layout = $s?->layout ?? [];
    @endphp
    <div class="modal fade" id="sectionModal-{{ $key }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content"
                  action="{{ $s ? route('organizer.venues.map.sections.update', [$venue, $s]) : route('organizer.venues.map.sections.store', $venue) }}">
                @csrf
                @if ($s) @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ $s ? 'Editar sector' : 'Nuevo sector' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" class="form-control" required maxlength="80"
                                   value="{{ $s?->name }}" placeholder="Ej: Platea baja, Campo, Pullman">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="{{ $s?->color ?? $nextColor }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Tipo</label>
                            @php $hasSeats = $s && $s->seats->isNotEmpty(); @endphp
                            <select name="type" class="form-select section-type" @disabled($hasSeats)>
                                <option value="general" @selected(($s?->type ?? 'general') === 'general')>General (sin numerar, por cupo)</option>
                                <option value="seated" @selected($s?->type === 'seated')>Numerado (butacas con fila y número)</option>
                            </select>
                            @if ($hasSeats)
                                <input type="hidden" name="type" value="{{ $s->type }}">
                                <div class="form-text">No se puede cambiar el tipo porque el sector ya tiene butacas.</div>
                            @endif
                        </div>

                        <div class="col-12 only-general">
                            <label class="form-label">Capacidad</label>
                            <input type="number" name="capacity" min="1" class="form-control" value="{{ $s?->isSeated() ? '' : $s?->capacity }}">
                        </div>

                        <div class="col-12">
                            <div class="small fw-semibold text-muted mb-1">Ubicación en el mapa <span class="fw-normal">(en casilleros; vacío = automática)</span></div>
                        </div>
                        <div class="col-3">
                            <label class="form-label small">Desde izq.</label>
                            <input type="number" name="pos_x" min="0" max="500" class="form-control form-control-sm" value="{{ $layout['x'] ?? '' }}">
                        </div>
                        <div class="col-3">
                            <label class="form-label small">Desde arriba</label>
                            <input type="number" name="pos_y" min="0" max="500" class="form-control form-control-sm" value="{{ $layout['y'] ?? '' }}">
                        </div>
                        <div class="col-3 only-general">
                            <label class="form-label small">Ancho</label>
                            <input type="number" name="width" min="2" max="200" class="form-control form-control-sm" value="{{ $layout['w'] ?? 20 }}">
                        </div>
                        <div class="col-3 only-general">
                            <label class="form-label small">Alto</label>
                            <input type="number" name="height" min="2" max="200" class="form-control form-control-sm" value="{{ $layout['h'] ?? 6 }}">
                        </div>
                        <div class="col-3">
                            <label class="form-label small">Orden</label>
                            <input type="number" name="sort" min="0" max="999" class="form-control form-control-sm" value="{{ $s?->sort }}">
                        </div>
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

{{-- ============ Modales de generación de butacas ============ --}}
@foreach ($venue->sections->where('type', 'seated') as $s)
    <div class="modal fade" id="generateModal-{{ $s->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content" action="{{ route('organizer.venues.map.sections.generate', [$venue, $s]) }}"
                  @if ($s->seats->isNotEmpty()) onsubmit="return confirm('Se van a reemplazar las {{ $s->seats->count() }} butacas actuales del sector. ¿Continuar?')" @endif>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Butacas de «{{ $s->name }}»</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($s->seats->isNotEmpty())
                        <div class="alert alert-warning small py-2">Las butacas actuales se reemplazan por completo (incluidas las desactivadas o marcadas como accesibles).</div>
                    @endif
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Cantidad de filas</label>
                            <input type="number" name="rows" min="1" max="100" class="form-control" value="10" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Butacas por fila</label>
                            <input type="number" name="seats_per_row" min="1" max="150" class="form-control" value="20" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Filas con</label>
                            <select name="row_label_type" class="form-select row-type">
                                <option value="letters">Letras (A, B, C...)</option>
                                <option value="numbers">Números (1, 2, 3...)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Primera fila</label>
                            <input type="text" name="row_start" maxlength="3" class="form-control row-start" value="A" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Numeración de butacas</label>
                            <select name="numbering" class="form-select numbering">
                                <option value="asc">Correlativa de izquierda a derecha (1, 2, 3...)</option>
                                <option value="desc">Correlativa de derecha a izquierda (...3, 2, 1)</option>
                                <option value="odd_even">Impares a la izquierda y pares a la derecha (...5, 3, 1 | 2, 4, 6...)</option>
                            </select>
                        </div>
                        <div class="col-6 start-number-col">
                            <label class="form-label">Número inicial</label>
                            <input type="number" name="start_number" min="1" max="999" class="form-control" value="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Pasillos después de la butaca</label>
                            <input type="text" name="aisles" class="form-control" placeholder="Ej: 6,14">
                        </div>
                        <div class="col-12 form-text mt-1">
                            Las filas se ordenan desde el escenario hacia atrás. Los pasillos se cuentan por posición de izquierda a derecha.
                            Después podés desactivar butacas sueltas para dar la forma real de la sala.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-success"><i class="bi bi-grid-3x3-gap"></i> Generar</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection

@push('scripts')
<script src="{{ asset('js/seatmap.js') }}?v=1"></script>
<script>
    (function () {
        const map = new SeatMap('#seatMap', @json($mapData), {
            mode: 'edit',
            onChange: (seats) => {
                document.getElementById('selCount').textContent = seats.length;
                document.querySelectorAll('.bulk-btn').forEach((b) => b.disabled = seats.length === 0);
            },
        });

        document.getElementById('zoomIn').addEventListener('click', () => map.zoomIn());
        document.getElementById('zoomOut').addEventListener('click', () => map.zoomOut());
        document.getElementById('zoomFit').addEventListener('click', () => map.fit());
        document.getElementById('selClear').addEventListener('click', () => map.clear());

        // Antes de enviar la acción masiva, se cargan los ids seleccionados
        document.getElementById('bulkForm').addEventListener('submit', (e) => {
            const ids = map.getSelected();
            if (!ids.length) { e.preventDefault(); return; }
            const box = document.getElementById('bulkIds');
            box.innerHTML = '';
            ids.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'seat_ids[]';
                input.value = id;
                box.appendChild(input);
            });
        });

        // Campos que dependen del tipo de sector
        document.querySelectorAll('.section-type').forEach((select) => {
            const modal = select.closest('.modal');
            const sync = () => modal.querySelectorAll('.only-general').forEach((el) => {
                el.classList.toggle('d-none', select.value !== 'general');
            });
            select.addEventListener('change', sync);
            sync();
        });

        // Generador: letra/número de la fila inicial y número inicial
        document.querySelectorAll('[id^="generateModal-"]').forEach((modal) => {
            const rowType = modal.querySelector('.row-type');
            const rowStart = modal.querySelector('.row-start');
            const numbering = modal.querySelector('.numbering');
            const startCol = modal.querySelector('.start-number-col');
            rowType.addEventListener('change', () => { rowStart.value = rowType.value === 'letters' ? 'A' : '1'; });
            const syncStart = () => startCol.classList.toggle('d-none', numbering.value === 'odd_even');
            numbering.addEventListener('change', syncStart);
            syncStart();
        });
    })();
</script>
@endpush
