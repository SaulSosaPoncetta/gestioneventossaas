<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TicketTransferredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** Versión de la entrada al momento de transferir (si cambia antes de enviarse, no se adjunta) */
    public int $version;

    public function __construct(public Ticket $ticket)
    {
        $this->version = $ticket->version;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Te enviaron una entrada para ' . $this->ticket->event->title);
    }

    public function content(): Content
    {
        $this->ticket->loadMissing(['event.venue', 'ticketType', 'eventSeat.seat.section', 'order.organizer']);

        return new Content(view: 'emails.ticket-transferred-email', with: [
            'ticket' => $this->ticket,
            'event'  => $this->ticket->event,
            // Con la versión del momento: si se volvió a transferir, este enlace ya no muestra el QR
            'url'    => URL::signedRoute('tickets.show', ['ticket' => $this->ticket->code, 'v' => $this->version]),
        ]);
    }

    public function attachments(): array
    {
        // Si la entrada se volvió a transferir antes de enviar este mail, no se adjunta
        if ($this->ticket->version !== $this->version || ! $this->ticket->isValid()) {
            return [];
        }

        $service = app(TicketService::class);

        return [
            Attachment::fromData(
                fn () => $service->ticketPdf($this->ticket)->output(),
                $service->pdfFilename($this->ticket->order, $this->ticket)
            )->withMime('application/pdf'),
        ];
    }
}
