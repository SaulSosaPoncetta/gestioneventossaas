<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Entradas · {{ $order->event->title }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { color: #1f2937; font-size: 11px; }
        .ticket { border: 2px solid #6d28d9; border-radius: 14px; overflow: hidden; }
        .page-break { page-break-after: always; }
        .head { background: #4c1d95; color: #fff; padding: 18px 22px; }
        .brand { font-size: 11px; letter-spacing: 1px; color: #fcd34d; font-weight: bold; text-transform: uppercase; }
        .title { font-size: 22px; font-weight: bold; margin: 6px 0 4px; }
        .sub { font-size: 12px; color: #ddd6fe; }
        .body { padding: 20px 22px; }
        table.layout { width: 100%; border-collapse: collapse; }
        table.layout td { vertical-align: top; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .value { font-size: 14px; font-weight: bold; margin-bottom: 12px; }
        .seat { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 10px; padding: 10px 14px; margin-bottom: 12px; }
        .seat .value { font-size: 18px; color: #4c1d95; margin: 0; }
        .qr { text-align: center; }
        .qr img { width: 230px; height: 230px; }
        .code { font-family: 'DejaVu Sans Mono', monospace; font-size: 16px; font-weight: bold; letter-spacing: 3px; text-align: center; margin-top: 4px; }
        .warning { color: #b45309; font-weight: bold; }
        .foot { border-top: 1px dashed #c4b5fd; padding: 12px 22px; font-size: 9.5px; color: #6b7280; }
        .count { float: right; color: #ddd6fe; font-size: 11px; }
    </style>
</head>
<body>
@foreach ($tickets as $i => $ticket)
    <div class="ticket">
        <div class="head">
            <span class="count">Entrada {{ $i + 1 }} de {{ $tickets->count() }}</span>
            <div class="brand">{{ config('geventos.nombre') }}</div>
            <div class="title">{{ $order->event->title }}</div>
            <div class="sub">
                {{ ucfirst($order->event->starts_at->translatedFormat('l d \d\e F \d\e Y')) }} · {{ $order->event->starts_at->format('H:i') }} hs
                @if ($order->event->doors_open_at) · Puertas {{ $order->event->doors_open_at->format('H:i') }} hs @endif
            </div>
        </div>

        <div class="body">
            <table class="layout">
                <tr>
                    <td style="width: 55%; padding-right: 16px;">
                        <div class="label">Lugar</div>
                        <div class="value">
                            {{ $order->event->venue?->name ?? 'A confirmar' }}
                            @if ($order->event->venue?->fullAddress())
                                <div style="font-size: 10px; font-weight: normal; color: #6b7280;">{{ $order->event->venue->fullAddress() }}</div>
                            @endif
                        </div>

                        <div class="label">Tipo de entrada</div>
                        <div class="value">{{ $ticket->ticketType->name }}</div>

                        @if ($ticket->eventSeat)
                            <div class="seat">
                                <div class="label">{{ $ticket->eventSeat->seat->section->name }}</div>
                                <div class="value">Fila {{ $ticket->eventSeat->seat->row_label }} · Butaca {{ $ticket->eventSeat->seat->number }}</div>
                            </div>
                        @endif

                        <div class="label">Asistente</div>
                        @if ($ticket->attendee_name)
                            <div class="value">
                                {{ $ticket->attendee_name }}
                                @if ($ticket->attendee_dni)<div style="font-size: 11px; font-weight: normal;">DNI {{ $ticket->attendee_dni }}</div>@endif
                            </div>
                        @else
                            <div class="value warning">Sin asignar · completá nombre y DNI desde tu compra</div>
                        @endif

                        <div class="label">Compra</div>
                        <div class="value" style="font-size: 11px;">#{{ strtoupper(substr($order->uuid, 0, 8)) }} · {{ $order->buyer_name }}</div>
                    </td>
                    <td class="qr" style="width: 45%;">
                        <img src="{{ $qrs[$ticket->id] }}" alt="QR">
                        <div class="code">{{ $ticket->code }}</div>
                        <div class="label" style="margin-top: 4px;">Presentá este QR en el ingreso</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="foot">
            Esta entrada es personal y válida para un solo ingreso: el QR se invalida al escanearse.
            No compartas ni publiques la imagen.
            @if ($order->event->is_nominative) En el ingreso se puede pedir el DNI del asistente. @endif
            Organiza: {{ $order->organizer->name }}. Ver tu compra: {{ route('checkout.show', $order) }}
        </div>
    </div>
    @if (! $loop->last)<div class="page-break"></div>@endif
@endforeach
</body>
</html>
