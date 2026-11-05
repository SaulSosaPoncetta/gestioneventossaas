<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\VenueRequest;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueController extends Controller
{
    private function organizer(): Organizer
    {
        return app('currentOrganizer');
    }

    /** El lugar tiene que ser de la productora activa */
    private function ensureOwned(Venue $venue): void
    {
        abort_unless($venue->organizer_id === $this->organizer()->id, 404);
    }

    public function index(Request $request): View
    {
        $venues = $this->organizer()->venues()
            ->withCount(['events', 'sections'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->input('q') . '%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('organizer.venues-index', compact('venues'));
    }

    public function create(): View
    {
        return view('organizer.venues-form', ['venue' => new Venue()]);
    }

    public function store(VenueRequest $request): RedirectResponse
    {
        $this->organizer()->venues()->create($request->validated());

        return redirect()->route('organizer.venues.index')->with('success', 'Lugar creado.');
    }

    public function edit(Venue $venue): View
    {
        $this->ensureOwned($venue);

        return view('organizer.venues-form', compact('venue'));
    }

    public function update(VenueRequest $request, Venue $venue): RedirectResponse
    {
        $this->ensureOwned($venue);

        $venue->update($request->validated());

        return redirect()->route('organizer.venues.index')->with('success', 'Lugar actualizado.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        $this->ensureOwned($venue);

        $hasActiveEvents = $venue->events()
            ->whereIn('status', [Event::STATUS_DRAFT, Event::STATUS_PUBLISHED, Event::STATUS_PAUSED])
            ->where('starts_at', '>=', now())
            ->exists();

        if ($hasActiveEvents) {
            return back()->with('error', 'No se puede eliminar: el lugar tiene eventos próximos.');
        }

        $venue->delete();

        return redirect()->route('organizer.venues.index')->with('success', 'Lugar eliminado.');
    }
}
