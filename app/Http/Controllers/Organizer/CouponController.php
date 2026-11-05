<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequest;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponController extends Controller
{
    private function organizer(): Organizer
    {
        return app('currentOrganizer');
    }

    private function ensureOwned(Coupon $coupon): void
    {
        abort_unless($coupon->organizer_id === $this->organizer()->id, 404);
    }

    private function events()
    {
        return $this->organizer()->events()
            ->whereIn('status', [Event::STATUS_DRAFT, Event::STATUS_PUBLISHED, Event::STATUS_PAUSED])
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at']);
    }

    public function index(Request $request): View
    {
        $coupons = $this->organizer()->coupons()
            ->with('event:id,title')
            ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%' . strtoupper($request->input('q')) . '%'))
            ->when($request->filled('event'), fn ($q) => $q->where('event_id', $request->input('event')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('organizer.coupons-index', [
            'coupons' => $coupons,
            'events'  => $this->events(),
        ]);
    }

    public function create(Request $request): View
    {
        $coupon = new Coupon([
            'type'        => Coupon::TYPE_PERCENT,
            'min_tickets' => 1,
            'is_active'   => true,
            'event_id'    => $request->integer('event') ?: null,
        ]);

        return view('organizer.coupons-form', ['coupon' => $coupon, 'events' => $this->events()]);
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        $this->organizer()->coupons()->create($request->validated());

        return redirect()->route('organizer.coupons.index')->with('success', 'Cupón creado.');
    }

    public function edit(Coupon $coupon): View
    {
        $this->ensureOwned($coupon);

        return view('organizer.coupons-form', ['coupon' => $coupon, 'events' => $this->events()]);
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->ensureOwned($coupon);

        $data = $request->validated();

        if (isset($data['max_uses']) && $data['max_uses'] < $coupon->used_count + $coupon->reserved_count) {
            return back()->withInput()->withErrors([
                'max_uses' => 'Los usos máximos no pueden ser menos que los ya utilizados (' . ($coupon->used_count + $coupon->reserved_count) . ').',
            ]);
        }

        $coupon->update($data);

        return redirect()->route('organizer.coupons.index')->with('success', 'Cupón actualizado.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $this->ensureOwned($coupon);

        if ($coupon->used_count > 0 || $coupon->reserved_count > 0 || $coupon->orders()->exists()) {
            $coupon->update(['is_active' => false]);

            return back()->with('success', 'El cupón ya se usó, así que se desactivó en lugar de eliminarse.');
        }

        $coupon->delete();

        return back()->with('success', 'Cupón eliminado.');
    }
}
