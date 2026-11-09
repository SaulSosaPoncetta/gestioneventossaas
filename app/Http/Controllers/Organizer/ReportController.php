<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Jobs\RefundEventOrders;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    /** Liquidación por evento */
    public function index(Request $request): View
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $from = $request->filled('desde') ? Carbon::parse($request->input('desde')) : null;
        $to = $request->filled('hasta') ? Carbon::parse($request->input('hasta')) : null;

        $rows = $this->reports->eventsSummary(app('currentOrganizer'), $from, $to);
        $totals = $this->reports->totals($rows);

        return view('organizer.reports-index', compact('rows', 'totals'));
    }

    /** Detalle de un evento */
    public function show(Event $event): View
    {
        abort_unless($event->organizer_id === app('currentOrganizer')->id, 404);

        $summary = $this->reports->eventsSummary(app('currentOrganizer'))->firstWhere('event.id', $event->id);
        $detail = $this->reports->eventDetail($event);
        $refundProgress = Cache::get(RefundEventOrders::progressKey($event->id));

        return view('organizer.reports-show', compact('event', 'summary', 'detail', 'refundProgress'));
    }

    /** Listado de asistentes de un evento en CSV */
    public function attendees(Event $event): StreamedResponse
    {
        abort_unless($event->organizer_id === app('currentOrganizer')->id, 404);

        $statuses = ['valid' => 'Válida', 'used' => 'Ingresó', 'cancelled' => 'Anulada'];

        return response()->streamDownload(function () use ($event, $statuses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Código', 'Tipo', 'Ubicación', 'Asistente', 'DNI', 'Email', 'Estado', 'Ingreso', 'Puerta', 'Compra', 'Comprador'], ';');

            Ticket::with(['ticketType', 'eventSeat.seat.section', 'order'])
                ->where('event_id', $event->id)
                ->orderBy('id')
                ->chunk(500, function ($tickets) use ($out, $statuses) {
                    foreach ($tickets as $t) {
                        $seat = $t->eventSeat?->seat;
                        fputcsv($out, [
                            $t->code,
                            $t->ticketType->name,
                            $seat ? $seat->section->name . ' F' . $seat->row_label . ' B' . $seat->number : '',
                            $t->attendee_name,
                            $t->attendee_dni,
                            $t->attendee_email,
                            $statuses[$t->status] ?? $t->status,
                            $t->checked_in_at?->format('d/m/Y H:i'),
                            $t->gate,
                            strtoupper(substr($t->order->uuid, 0, 8)),
                            $t->order->buyer_name,
                        ], ';');
                    }
                });

            fclose($out);
        }, 'asistentes-' . $event->slug . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
