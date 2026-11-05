<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\TicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tus entradas para ' . $this->order->event->title,
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['event.venue', 'organizer', 'tickets']);

        return new Content(view: 'emails.tickets-email', with: [
            'order' => $this->order,
            'event' => $this->order->event,
            'url'   => route('checkout.show', $this->order),
        ]);
    }

    public function attachments(): array
    {
        $service = app(TicketService::class);

        return [
            Attachment::fromData(fn () => $service->pdf($this->order)->output(), $service->pdfFilename($this->order))
                ->withMime('application/pdf'),
        ];
    }
}
