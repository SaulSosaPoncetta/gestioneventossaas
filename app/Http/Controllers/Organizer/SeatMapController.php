<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\SeatGenerationRequest;
use App\Http\Requests\VenueSectionRequest;
use App\Models\Organizer;
use App\Models\Seat;
use App\Models\Venue;
use App\Models\VenueSection;
use App\Services\SeatMapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeatMapController extends Controller
{
    public function __construct(private SeatMapService $map)
    {
    }

    private function organizer(): Organizer
    {
        return app('currentOrganizer');
    }

    private function ensureOwned(Venue $venue, ?VenueSection $section = null): void
    {
        abort_unless($venue->organizer_id === $this->organizer()->id, 404);

        if ($section) {
            abort_unless($section->venue_id === $venue->id, 404);
        }
    }

    /* ---------------------------------------------------------------- */

    public function show(Venue $venue): View
    {
        $this->ensureOwned($venue);

        $venue->load('sections.seats');

        $mapData = $this->map->mapData($venue, withLock: true);
        $locked = collect($mapData['sections'])->pluck('locked', 'id');
        $nextPosition = $this->map->nextPosition($venue);
        $nextColor = $this->map->nextColor($venue);

        return view('organizer.seatmap', compact('venue', 'mapData', 'locked', 'nextPosition', 'nextColor'));
    }

    public function storeSection(VenueSectionRequest $request, Venue $venue): RedirectResponse
    {
        $this->ensureOwned($venue);

        $data = $request->validated();
        $venue->load('sections.seats');
        $pos = $this->map->nextPosition($venue);

        $section = $venue->sections()->create([
            'name'     => $data['name'],
            'type'     => $data['type'],
            'color'    => $data['color'] ?? $this->map->nextColor($venue),
            'capacity' => $data['type'] === VenueSection::TYPE_GENERAL ? $data['capacity'] : 0,
            'sort'     => $data['sort'] ?? $venue->sections->count(),
            'layout'   => [
                'x' => $data['pos_x'] ?? $pos['x'],
                'y' => $data['pos_y'] ?? $pos['y'],
                'w' => $data['width'] ?? 20,
                'h' => $data['height'] ?? 6,
            ],
        ]);

        $msg = $section->isSeated()
            ? "Sector «{$section->name}» creado. Ahora generá sus butacas."
            : "Sector «{$section->name}» creado.";

        return back()->with('success', $msg);
    }

    public function updateSection(VenueSectionRequest $request, Venue $venue, VenueSection $section): RedirectResponse
    {
        $this->ensureOwned($venue, $section);

        $data = $request->validated();

        if ($data['type'] !== $section->type && $section->seats()->exists()) {
            return back()->with('error', 'No se puede cambiar el tipo de un sector que ya tiene butacas. Eliminalo y crealo de nuevo.');
        }

        $layout = $section->layout ?? [];

        $section->update([
            'name'     => $data['name'],
            'type'     => $data['type'],
            'color'    => $data['color'] ?? $section->color,
            'capacity' => $data['type'] === VenueSection::TYPE_GENERAL ? $data['capacity'] : $section->capacity,
            'sort'     => $data['sort'] ?? $section->sort,
            'layout'   => [
                'x' => $data['pos_x'] ?? ($layout['x'] ?? 0),
                'y' => $data['pos_y'] ?? ($layout['y'] ?? 0),
                'w' => $data['width'] ?? ($layout['w'] ?? 20),
                'h' => $data['height'] ?? ($layout['h'] ?? 6),
            ],
        ]);

        $this->map->refreshSection($section);

        return back()->with('success', "Sector «{$section->name}» actualizado.");
    }

    public function destroySection(Venue $venue, VenueSection $section): RedirectResponse
    {
        $this->ensureOwned($venue, $section);

        if ($this->map->isLocked($section)) {
            return back()->with('error', 'No se puede eliminar: el sector ya se usa en un evento.');
        }

        $name = $section->name;
        $section->delete();
        $this->map->refreshVenue($venue);

        return back()->with('success', "Sector «{$name}» eliminado.");
    }

    public function generateSeats(SeatGenerationRequest $request, Venue $venue, VenueSection $section): RedirectResponse
    {
        $this->ensureOwned($venue, $section);

        if (! $section->isSeated()) {
            return back()->with('error', 'Solo los sectores numerados llevan butacas.');
        }

        if ($this->map->isLocked($section)) {
            return back()->with('error', 'No se pueden regenerar las butacas: el sector ya se usa en un evento.');
        }

        $count = $this->map->generate($section, $request->validated());

        return back()->with('success', "Se generaron {$count} butacas en «{$section->name}».");
    }

    /** Acción masiva sobre las butacas seleccionadas en el mapa */
    public function updateSeats(Request $request, Venue $venue): RedirectResponse
    {
        $this->ensureOwned($venue);

        $data = $request->validate([
            'action'     => ['required', Rule::in(['activate', 'deactivate', 'standard', 'accessible', 'companion'])],
            'seat_ids'   => ['required', 'array', 'min:1'],
            'seat_ids.*' => ['integer'],
        ], [
            'seat_ids.required' => 'Seleccioná al menos una butaca en el mapa.',
        ]);

        $seats = Seat::whereIn('id', $data['seat_ids'])
            ->whereHas('section', fn ($q) => $q->where('venue_id', $venue->id));

        $changes = match ($data['action']) {
            'activate'   => ['is_active' => true],
            'deactivate' => ['is_active' => false],
            'standard'   => ['kind' => Seat::KIND_STANDARD],
            'accessible' => ['kind' => Seat::KIND_ACCESSIBLE],
            'companion'  => ['kind' => Seat::KIND_COMPANION],
        };

        $sectionIds = (clone $seats)->distinct()->pluck('venue_section_id');
        $affected = $seats->update($changes);

        VenueSection::whereIn('id', $sectionIds)->get()->each(fn ($s) => $this->map->refreshSection($s));

        return back()->with('success', "Se actualizaron {$affected} butacas.");
    }
}
