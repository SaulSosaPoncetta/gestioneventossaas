@extends('layouts.app')

@section('title', 'Gestionar entradas')

@php
    $event = $order->event;
    $closing = $transfers->closingAt($event);
@endphp

@section('content')
<div class="container" style="max-width: 920px;">
    <div class="mb-3">
        <a href="{{ route('checkout.show', $order) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Volver a la compra</a>
    </div>

    <div class="card border-0 shadow-sm p-4 mb-4">
        <h1 class="h4 fw-bold mb-1">Asistentes y transferencias</h1>
        <div class="text-muted small">
            {{ $event->title }} · {{ $event->starts_at->format('d/m/Y H:i') }} hs · Compra #{{ strtoupper(substr($order->uuid, 0, 8)) }}
        </div>
        <ul class="small text-muted mt-3 mb-0 ps-3">
            @if ($event->is_nominative)
                <li>Las entradas son <strong>nominativas</strong>: en el ingreso se puede pedir el DNI del asistente.</li>
            @endif
            @if ($event->allow_transfers)
                <li>Al <strong>transferir</strong> una entrada se genera un QR nuevo, se lo enviamos por mail al nuevo titular y el QR anterior deja de servir.</li>
                <li>Cada entrada se puede transferir hasta {{ $transfers->maxTransfers() }} veces.</li>
            @else
                <li>El organizador no permite transferir las entradas de este evento.</li>
            @endif
            <li>Podés hacer cambios hasta el {{ $closing->format('d/m/Y') }} a las {{ $closing->format('H:i') }} hs.</li>
        </ul>
    </div>

    @if ($errors->has('ticket'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> {{ $errors->first('ticket') }}</div>
    @endif

    @unless ($hasAccess)
        {{-- Sin acceso de gestión: se pide el enlace por mail --}}
        <div class="card border-0 shadow-sm p-4 text-center">
            <i class="bi bi-shield-lock fs-1 text-primary mb-2"></i>
            <h2 class="h5 fw-bold">Confirmá que sos quien hizo la compra</h2>
            <p class="text-muted mb-3">
                Para modificar asistentes o transferir entradas te enviamos un enlace al email de la compra
                (<strong>{{ $maskedEmail }}</strong>). Vence en 1 hora.
            </p>
            <form method="POST" action="{{ route('checkout.access.request', $order) }}">
                @csrf
                <button class="btn btn-primary"><i class="bi bi-envelope"></i> Enviarme el enlace</button>
            </form>
        </div>
    @else
        @foreach ($tickets as $ticket)
            @php
                $lock = $transfers->lockReason($ticket);
                $transferLock = $transfers->transferLockReason($ticket);
                $bagA = $errors->getBag('ticket' . $ticket->id);
                $bagT = $errors->getBag('transfer' . $ticket->id);
                $openTransfer = $bagT->isNotEmpty();
            @endphp
            <div class="card border-0 shadow-sm p-4 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <div class="fw-bold">{{ $ticket->ticketType->name }} <span class="text-muted small font-monospace ms-1">{{ $ticket->code }}</span></div>
                        @if ($ticket->eventSeat)
                            <div class="small text-primary fw-semibold">{{ $ticket->eventSeat->seat->section->name }} · Fila {{ $ticket->eventSeat->seat->row_label }} · Butaca {{ $ticket->eventSeat->seat->number }}</div>
                        @endif
                        <div class="small mt-1">
                            @if ($ticket->attendee_name)
                                <i class="bi bi-person"></i> {{ $ticket->attendee_name }}
                                @if ($ticket->attendee_dni) · DNI {{ $ticket->attendee_dni }} @endif
                                @if ($ticket->attendee_email) · {{ $ticket->attendee_email }} @endif
                            @else
                                <span class="text-warning"><i class="bi bi-exclamation-circle"></i> Sin asistente asignado</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        @if ($ticket->isUsed())
                            <span class="badge text-bg-secondary">Usada</span>
                        @elseif ($ticket->isTransferredAway())
                            <span class="badge text-bg-info">Transferida</span>
                        @else
                            <span class="badge text-bg-success">Válida</span>
                        @endif
                        @if ($ticket->transfersCount() > 0)
                            <div class="small text-muted mt-1">{{ $ticket->transfersCount() }} transferencia(s)</div>
                        @endif
                    </div>
                </div>

                @if ($lock)
                    <div class="small text-muted"><i class="bi bi-lock"></i> {{ $lock }}</div>
                @else
                    {{-- Datos del asistente --}}
                    @if ($event->is_nominative)
                        <form method="POST" action="{{ route('checkout.tickets.assign', [$order, $ticket]) }}" class="row g-2 align-items-end mb-2" novalidate>
                            @csrf
                            @method('PATCH')
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Nombre y apellido</label>
                                <input type="text" name="attendee_name" class="form-control form-control-sm {{ $bagA->has('attendee_name') ? 'is-invalid' : '' }}"
                                       value="{{ $bagA->isNotEmpty() ? old('attendee_name') : $ticket->attendee_name }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">DNI</label>
                                <input type="text" name="attendee_dni" inputmode="numeric" class="form-control form-control-sm {{ $bagA->has('attendee_dni') ? 'is-invalid' : '' }}"
                                       value="{{ $bagA->isNotEmpty() ? old('attendee_dni') : $ticket->attendee_dni }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Email <span class="text-muted">(opcional)</span></label>
                                <input type="email" name="attendee_email" class="form-control form-control-sm {{ $bagA->has('attendee_email') ? 'is-invalid' : '' }}"
                                       value="{{ $bagA->isNotEmpty() ? old('attendee_email') : $ticket->attendee_email }}">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button class="btn btn-sm btn-primary"
                                        @if ($ticket->attendee_dni) onclick="const d=this.form.attendee_dni.value.replace(/\D/g,''); return d === '{{ $ticket->attendee_dni }}' || confirm('Cambiar el DNI cuenta como transferencia: se genera un QR nuevo y el anterior deja de servir. ¿Continuar?')" @endif>
                                    Guardar
                                </button>
                            </div>
                            @if ($bagA->isNotEmpty())
                                <div class="col-12 text-danger small">{{ $bagA->first() }}</div>
                            @endif
                        </form>
                    @endif

                    {{-- Transferir --}}
                    @if ($event->allow_transfers)
                        @if ($transferLock)
                            <div class="small text-muted"><i class="bi bi-lock"></i> {{ $transferLock }}</div>
                        @else
                            <a class="small" data-bs-toggle="collapse" href="#transfer-{{ $ticket->id }}" role="button">
                                <i class="bi bi-send"></i> Transferir esta entrada a otra persona
                            </a>
                            <div class="collapse {{ $openTransfer ? 'show' : '' }}" id="transfer-{{ $ticket->id }}">
                                <form method="POST" action="{{ route('checkout.tickets.transfer', [$order, $ticket]) }}" class="row g-2 align-items-end mt-1 p-3 bg-light rounded"
                                      onsubmit="return confirm('Se le enviará la entrada al nuevo titular y tu QR actual dejará de servir. ¿Transferir?')" novalidate>
                                    @csrf
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Nombre y apellido</label>
                                        <input type="text" name="to_name" value="{{ $openTransfer ? old('to_name') : '' }}"
                                               class="form-control form-control-sm {{ $bagT->has('to_name') ? 'is-invalid' : '' }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">DNI @unless ($event->is_nominative)<span class="text-muted">(opcional)</span>@endunless</label>
                                        <input type="text" name="to_dni" inputmode="numeric" value="{{ $openTransfer ? old('to_dni') : '' }}"
                                               class="form-control form-control-sm {{ $bagT->has('to_dni') ? 'is-invalid' : '' }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Email</label>
                                        <input type="email" name="to_email" value="{{ $openTransfer ? old('to_email') : '' }}"
                                               class="form-control form-control-sm {{ $bagT->has('to_email') ? 'is-invalid' : '' }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Repetí el email</label>
                                        <input type="email" name="to_email_confirmation" value="{{ $openTransfer ? old('to_email_confirmation') : '' }}" class="form-control form-control-sm">
                                    </div>
                                    @if ($openTransfer)
                                        <div class="col-12 text-danger small">{{ $bagT->first() }}</div>
                                    @endif
                                    <div class="col-12">
                                        <button class="btn btn-sm btn-warning"><i class="bi bi-send"></i> Transferir</button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    @endif
                @endif
            </div>
        @endforeach
    @endunless
</div>
@endsection
