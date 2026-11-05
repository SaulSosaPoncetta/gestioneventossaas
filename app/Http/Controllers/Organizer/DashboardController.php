<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $organizer = app('currentOrganizer');

        $stats = [
            'events_total'    => $organizer->events()->count(),
            'events_upcoming' => $organizer->events()->published()->upcoming()->count(),
            'tickets_sold'    => Ticket::whereIn('event_id', $organizer->events()->select('id'))
                ->where('status', '!=', Ticket::STATUS_CANCELLED)
                ->count(),
            'revenue'         => (float) $organizer->orders()->paid()->sum('subtotal')
                - (float) $organizer->orders()->paid()->sum('discount'),
        ];

        $nextEvents = $organizer->events()
            ->whereIn('status', [Event::STATUS_DRAFT, Event::STATUS_PUBLISHED, Event::STATUS_PAUSED])
            ->upcoming()
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        $lastOrders = $organizer->orders()
            ->with('event:id,title')
            ->where('status', Order::STATUS_PAID)
            ->latest('paid_at')
            ->limit(5)
            ->get();

        return view('organizer.dashboard', compact('organizer', 'stats', 'nextEvents', 'lastOrders'));
    }

    /** Cambia la productora activa (para usuarios que pertenecen a varias) */
    public function switch(Request $request): RedirectResponse
    {
        $id = (int) $request->input('organizer_id');

        if (! $request->user()->belongsToOrganizer($id)) {
            abort(403);
        }

        $request->session()->put('current_organizer_id', $id);

        return redirect()->route('organizer.dashboard');
    }
}
