<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderRefundedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public ?string $reason = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu compra para ' . $this->order->event->title . ' fue anulada',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-refunded-email', with: [
            'order'  => $this->order,
            'event'  => $this->order->event,
            'reason' => $this->reason ?? 'Tu compra fue anulada y las entradas quedaron sin validez.',
            'url'    => route('checkout.show', $this->order),
        ]);
    }
}
