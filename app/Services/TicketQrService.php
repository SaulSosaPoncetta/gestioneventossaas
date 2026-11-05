<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Contenido firmado de los QR de las entradas.
 *
 * Formato:  GE1.{event_id}.{codigo}.{version}.{firma}
 * La firma es un HMAC-SHA256 con la clave propia del evento (events.checkin_secret),
 * así el escáner de puerta puede validar sin conexión y un QR inventado o de otro
 * evento se rechaza. Al transferir una entrada sube la versión y el QR anterior deja de servir.
 */
class TicketQrService
{
    public const PREFIX = 'GE1';

    private const PATTERN = '/^GE1\.(\d+)\.([A-Z0-9]{6,16})\.(\d+)\.([A-Za-z0-9_-]{22})$/';

    public function payload(Ticket $ticket): string
    {
        $ticket->loadMissing('event');

        $base = implode('.', [self::PREFIX, $ticket->event_id, $ticket->code, $ticket->version]);

        return $base . '.' . $this->sign($base, $this->secret($ticket->event));
    }

    /**
     * Valida un QR leído en la puerta para un evento.
     *
     * @return array{event_id:int, code:string, version:int}|null  null si es inválido o de otro evento
     */
    public function verify(string $payload, Event $event): ?array
    {
        $data = $this->parse($payload);

        if (! $data || $data['event_id'] !== $event->id) {
            return null;
        }

        $base = implode('.', [self::PREFIX, $data['event_id'], $data['code'], $data['version']]);

        return hash_equals($this->sign($base, $this->secret($event)), $data['signature']) ? $data : null;
    }

    /** @return array{event_id:int, code:string, version:int, signature:string}|null */
    public function parse(string $payload): ?array
    {
        if (! preg_match(self::PATTERN, trim($payload), $m)) {
            return null;
        }

        return [
            'event_id'  => (int) $m[1],
            'code'      => $m[2],
            'version'   => (int) $m[3],
            'signature' => $m[4],
        ];
    }

    /** Imagen del QR como data URI (PNG si está la extensión GD, si no SVG) */
    public function dataUri(string $payload, int $size = 320): string
    {
        $qr = new QrCode(
            data: $payload,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 8,
        );

        $writer = extension_loaded('gd') ? new PngWriter() : new SvgWriter();

        return $writer->write($qr)->getDataUri();
    }

    public function ticketImage(Ticket $ticket, int $size = 320): string
    {
        return $this->dataUri($this->payload($ticket), $size);
    }

    /* ---------------------------------------------------------------- */

    private function sign(string $base, string $secret): string
    {
        $raw = hash_hmac('sha256', $base, $secret, true);

        return substr(rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 0, 22);
    }

    private function secret(Event $event): string
    {
        if (empty($event->checkin_secret)) {
            $event->forceFill(['checkin_secret' => bin2hex(random_bytes(32))])->save();
        }

        return $event->checkin_secret;
    }
}
