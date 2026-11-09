<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TicketQrService;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrada individual para quien la recibió por transferencia.
 * Se accede con un enlace firmado que incluye la versión de la entrada:
 * si se vuelve a transferir, el enlace anterior deja de mostrar el QR.
 */
class TicketPublicController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private TicketQrService $qr,
    ) {
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $ticket->load(['event.venue', 'ticketType', 'eventSeat.seat.section', 'order']);

        $current = (int) $request->query('v') === $ticket->version;
        $qr = ($current && $ticket->isValid()) ? $this->qr->ticketImage($ticket, 300) : null;

        return view('public.ticket', compact('ticket', 'current', 'qr'));
    }

    public function pdf(Request $request, Ticket $ticket): Response
    {
        abort_unless((int) $request->query('v') === $ticket->version && $ticket->isValid(), 404);

        return $this->tickets->ticketPdf($ticket)
            ->download($this->tickets->pdfFilename($ticket->order, $ticket));
    }
}
