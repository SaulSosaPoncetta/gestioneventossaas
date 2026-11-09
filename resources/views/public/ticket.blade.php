@extends('layouts.app')

@section('title', 'Tu entrada · ' . $ticket->event->title)

@section('content')
<div class="container" style="max-width: 560px;">
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="p-4 text-white" style="background: linear-gradient(135deg, #4c1d95, #6d28d9);">
            <div class="small text-warning fw-bold text-uppercase">{{ config('geventos.nombre') }}</div>
            <h1 class="h4 fw-bold mb-1">{{ $ticket->event->title }}</h1>
            <div class="small opacity-75">
                <i class="bi bi-calendar3"></i> {{ $ticket->event->starts_at->format('d/m/Y H:i') }} hs
                · <i class="bi bi-geo-alt"></i> {{ $ticket->event->venue?->name ?? 'Lugar a confirmar' }}
            </div>
        </div>

        <div class="p-4 text-center">
            @if (! $current)
                <i class="bi bi-arrow-left-right fs-1 text-muted"></i>
                <h2 class="h5 fw-bold mt-2">Este enlace ya no es válido</h2>
                <p class="text-muted mb-0">La entrada fue transferida a otra persona y este QR quedó anulado.</p>
            @elseif ($ticket->isUsed())
                <i class="bi bi-check2-circle fs-1 text-secondary"></i>
                <h2 class="h5 fw-bold mt-2">Entrada usada</h2>
                <p class="text-muted mb-0">Ingresó el {{ $ticket->checked_in_at?->format('d/m/Y H:i') }}.</p>
            @elseif ($ticket->status === 'cancelled')
                <i class="bi bi-x-circle fs-1 text-danger"></i>
                <h2 class="h5 fw-bold mt-2">Entrada anulada</h2>
                <p class="text-muted mb-0">Esta entrada ya no es válida.</p>
            @else
                <img src="{{ $qr }}" alt="QR de la entrada" class="img-fluid mb-2" style="max-width: 280px;">
                <div class="font-monospace fw-bold fs-5" style="letter-spacing: .2em;">{{ $ticket->code }}</div>

                <div class="mt-3">
                    <div class="fw-bold">{{ $ticket->ticketType->name }}</div>
                    @if ($ticket->eventSeat)
                        <div class="text-primary fw-semibold">{{ $ticket->eventSeat->seat->section->name }} · Fila {{ $ticket->eventSeat->seat->row_label }} · Butaca {{ $ticket->eventSeat->seat->number }}</div>
                    @endif
                    @if ($ticket->attendee_name)
                        <div class="small text-muted mt-1">
                            <i class="bi bi-person"></i> {{ $ticket->attendee_name }} @if ($ticket->attendee_dni) · DNI {{ $ticket->attendee_dni }} @endif
                        </div>
                    @endif
                </div>

                <a href="{{ $ticket->publicPdfUrl() }}" class="btn btn-primary mt-4"><i class="bi bi-file-earmark-pdf"></i> Descargar PDF</a>
                <p class="small text-muted mt-3 mb-0">
                    Presentá este QR en el ingreso. Sirve para un solo ingreso: no lo compartas.
                    @if ($ticket->event->is_nominative) Llevá tu DNI. @endif
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
