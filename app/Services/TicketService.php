<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TicketService
{
    public function __construct(private TicketQrService $qr)
    {
    }

    /**
     * Emite una entrada por cada unidad comprada. Es idempotente: si la orden ya
     * tiene entradas no crea nuevas. Se llama dentro de la transacción del pago.
     */
    public function issue(Order $order): Collection
    {
        if ($order->tickets()->exists()) {
            return $order->tickets()->get();
        }

        $order->loadMissing(['items', 'event']);
        $nominative = $order->event->is_nominative;
        $first = true;

        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                // Nominativas: la primera queda a nombre del comprador y el resto se completa después
                $assign = ! $nominative || $first;

                Ticket::create([
                    'order_id'       => $order->id,
                    'order_item_id'  => $item->id,
                    'event_id'       => $order->event_id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'event_seat_id'  => $item->event_seat_id,
                    'holder_user_id' => $order->user_id,
                    'attendee_name'  => $assign ? $order->buyer_name : null,
                    'attendee_dni'   => $assign ? $order->buyer_dni : null,
                    'attendee_email' => $assign ? $order->buyer_email : null,
                    'status'         => Ticket::STATUS_VALID,
                ]);

                $first = false;
            }
        }

        return $order->tickets()->get();
    }

    /** Anula las entradas de una orden (devolución o contracargo) */
    public function cancelForOrder(Order $order): int
    {
        return $order->tickets()
            ->where('status', Ticket::STATUS_VALID)
            ->update(['status' => Ticket::STATUS_CANCELLED]);
    }

    /**
     * Entradas vigentes de la orden, con lo necesario para mostrarlas.
     * Por defecto no incluye las transferidas a otra persona (el comprador ya no tiene su QR).
     */
    public function printable(Order $order, bool $includeTransferred = false): Collection
    {
        $tickets = $order->tickets()
            ->where('status', '!=', Ticket::STATUS_CANCELLED)
            ->with(['ticketType', 'eventSeat.seat.section', 'event', 'order'])
            ->withCount('transfers')
            ->orderBy('id')
            ->get();

        return $includeTransferred
            ? $tickets
            : $tickets->reject(fn (Ticket $t) => $t->isTransferredAway())->values();
    }

    /** Imagen QR de cada entrada: [ticket_id => data URI] */
    public function qrImages(Collection $tickets, int $size = 320): array
    {
        return $tickets->mapWithKeys(fn (Ticket $t) => [$t->id => $this->qr->ticketImage($t, $size)])->all();
    }

    /** PDF con una entrada por página (todas las de la orden o solo las indicadas) */
    public function pdf(Order $order, ?Collection $tickets = null): DomPdf
    {
        $order->loadMissing(['event.venue', 'organizer']);
        $tickets ??= $this->printable($order);

        return Pdf::loadView('pdf.tickets-pdf', [
            'order'   => $order,
            'tickets' => $tickets,
            'qrs'     => $this->qrImages($tickets, 400),
        ])->setPaper('a4');
    }

    /** PDF de una sola entrada (para quien la recibió por transferencia) */
    public function ticketPdf(Ticket $ticket): DomPdf
    {
        $ticket->load(['ticketType', 'eventSeat.seat.section', 'event', 'order']);

        return $this->pdf($ticket->order, collect([$ticket]));
    }

    public function pdfFilename(Order $order, ?Ticket $ticket = null): string
    {
        $suffix = $ticket ? $ticket->code : strtoupper(substr($order->uuid, 0, 8));

        return 'entradas-' . Str::slug($order->event->title) . '-' . $suffix . '.pdf';
    }
}
