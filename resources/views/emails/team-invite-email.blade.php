<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Te sumaron al equipo</title>
</head>
<body style="margin:0; padding:0; background:#f6f5fb; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f5fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:14px; overflow:hidden;">
                <tr>
                    <td style="background:#4c1d95; padding:22px 26px; color:#ffffff;">
                        <div style="font-size:12px; letter-spacing:1px; color:#fcd34d; font-weight:bold; text-transform:uppercase;">{{ config('geventos.nombre') }}</div>
                        <div style="font-size:20px; font-weight:bold; margin-top:6px;">Te sumaron a {{ $organizer->name }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 26px; font-size:15px; line-height:1.5;">
                        <p style="margin:0 0 14px;">Hola {{ $user->name }},</p>
                        <p style="margin:0 0 14px;">Te agregaron al equipo de <strong>{{ $organizer->name }}</strong> con el rol <strong>{{ $roleLabel }}</strong>.</p>
                        @if ($isNew)
                            <p style="margin:0 0 20px;">Para empezar, elegí tu contraseña (el enlace vence en 60 minutos; si vence, usá "¿Olvidaste tu contraseña?" en la pantalla de ingreso):</p>
                        @else
                            <p style="margin:0 0 20px;">Ingresá con tu usuario de siempre:</p>
                        @endif
                        <p style="margin:0 0 22px;" align="center">
                            <a href="{{ $url }}" style="display:inline-block; background:#6d28d9; color:#ffffff; text-decoration:none; padding:12px 26px; border-radius:8px; font-weight:bold;">
                                {{ $isNew ? 'Elegir mi contraseña' : 'Ingresar' }}
                            </a>
                        </p>
                        <p style="margin:0; font-size:13px; color:#6b7280;">
                            Para controlar el ingreso en la puerta usá el escáner: {{ url('/escaner') }} (desde el celular podés instalarlo como app).
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
