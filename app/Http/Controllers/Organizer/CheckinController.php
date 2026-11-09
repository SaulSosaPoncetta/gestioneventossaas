<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\CheckinService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Tablero de control de ingreso de un evento (panel de productora) */
class CheckinController extends Controller
{
    public function __construct(private CheckinService $checkins)
    {
    }

    public function show(Event $event): View
    {
        abort_unless($event->organizer_id === app('currentOrganizer')->id, 404);

        $stats = $this->checkins->stats($event);

        $byType = Ticket::where('tickets.event_id', $event->id)
            ->where('tickets.status', '!=', Ticket::STATUS_CANCELLED)
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->groupBy('ticket_types.id', 'ticket_types.name')
            ->orderBy('ticket_types.name')
            ->get([
                'ticket_types.name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(tickets.status = 'used') as used"),
            ]);

        $byGate = Ticket::where('event_id', $event->id)
            ->where('status', Ticket::STATUS_USED)
            ->selectRaw("COALESCE(NULLIF(gate, ''), 'Sin nombre') as gate_name, COUNT(*) as total")
            ->groupBy('gate_name')
            ->orderByDesc('total')
            ->get();

        $rejected = Checkin::where('event_id', $event->id)
            ->where('result', '!=', Checkin::RESULT_OK)
            ->selectRaw('result, COUNT(*) as total')
            ->groupBy('result')
            ->pluck('total', 'result');

        $lastScans = Checkin::with(['ticket.ticketType', 'operator:id,name'])
            ->where('event_id', $event->id)
            ->latest('scanned_at')
            ->limit(40)
            ->get();

        // Ingresos cada 15 minutos (últimos 24 tramos con actividad)
        $timeline = Ticket::where('event_id', $event->id)
            ->where('status', Ticket::STATUS_USED)
            ->whereNotNull('checked_in_at')
            ->selectRaw("DATE_FORMAT(checked_in_at, '%H:%i') as hhmm, FLOOR(UNIX_TIMESTAMP(checked_in_at) / 900) as slot, COUNT(*) as total")
            ->groupBy('slot', 'hhmm')
            ->get()
            ->groupBy('slot')
            ->map(fn ($rows) => ['label' => $rows->min('hhmm'), 'total' => $rows->sum('total')])
            ->sortKeys()
            ->take(-24)
            ->values();

        return view('organizer.checkins', compact('event', 'stats', 'byType', 'byGate', 'rejected', 'lastScans', 'timeline'));
    }
}
