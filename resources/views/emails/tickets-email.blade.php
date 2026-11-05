<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tus entradas</title>
</head>
<body style="margin:0; padding:0; background:#f6f5fb; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f5fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:14px; overflow:hidden;">
                <tr>
                    <td style="background:#4c1d95; padding:22px 26px; color:#ffffff;">
                        <div style="font-size:12px; letter-spacing:1px; color:#fcd34d; font-weight:bold; text-transform:uppercase;">{{ config('geventos.nombre') }}</div>
                        <div style="font-size:22px; font-weight:bold; margin-top:6px;">¡Tus entradas están listas!</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 26px; font-size:15px; line-height:1.5;">
                        <p style="margin:0 0 14px;">Hola {{ $order->buyer_name }},</p>
                        <p style="margin:0 0 18px;">Tu compra fue confirmada. Te adjuntamos en PDF {{ $order->tickets->count() === 1 ? 'tu entrada' : 'tus ' . $order->tickets->count() . ' entradas' }} para:</p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3ff; border-radius:10px; margin-bottom:20px;">
                            <tr>
                                <td style="padding:16px 18px;">
                                    <div style="font-size:18px; font-weight:bold; color:#4c1d95;">{{ $event->title }}</div>
                                    <div style="font-size:14px; color:#4b5563; margin-top:4px;">
                                        {{ ucfirst($event->starts_at->translatedFormat('l d \d\e F')) }} · {{ $event->starts_at->format('H:i') }} hs<br>
                                        {{ $event->venue?->name ?? 'Lugar a confirmar' }}{{ $event->venue?->fullAddress() ? ' — ' . $event->venue->fullAddress() : '' }}
                                    </div>
                                </td>
                            </tr>
                        </table>

                        @if ($event->is_nominative && $order->tickets->contains(fn ($t) => empty($t->attendee_name)))
                            <p style="margin:0 0 18px; color:#b45309;">
                                <strong>Importante:</strong> las entradas son nominativas. Completá el nombre y DNI de cada asistente desde tu compra antes del evento.
                            </p>
                        @endif

                        <p style="margin:0 0 22px;" align="center">
                            <a href="{{ $url }}" style="display:inline-block; background:#6d28d9; color:#ffffff; text-decoration:none; padding:12px 26px; border-radius:8px; font-weight:bold;">
                                Ver mis entradas
                            </a>
                        </p>

                        <p style="margin:0 0 6px; font-size:13px; color:#6b7280;">
                            En el ingreso mostrá el QR desde el celular o impreso. Cada QR sirve para un solo ingreso: no lo compartas.
                        </p>
                        <p style="margin:0; font-size:13px; color:#6b7280;">
                            Total pagado: $ {{ number_format((float) $order->total, 2, ',', '.') }} · Compra #{{ strtoupper(substr($order->uuid, 0, 8)) }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 26px; background:#faf9fd; font-size:12px; color:#9ca3af;">
                        Organiza {{ $order->organizer->name }} · Venta online por {{ config('geventos.nombre') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
