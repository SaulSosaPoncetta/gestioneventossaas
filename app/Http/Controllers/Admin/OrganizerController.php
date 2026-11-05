<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizerController extends Controller
{
    public function index(Request $request): View
    {
        $organizers = Organizer::query()
            ->with(['users' => fn ($q) => $q->wherePivot('role', Organizer::ROLE_OWNER)])
            ->withCount('events')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('cuit', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByRaw("FIELD(status, 'pending', 'active', 'suspended')")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $pendingCount = Organizer::where('status', Organizer::STATUS_PENDING)->count();

        return view('admin.organizers-index', compact('organizers', 'pendingCount'));
    }

    public function update(Request $request, Organizer $organizer): RedirectResponse
    {
        $data = $request->validate([
            'action'             => ['required', Rule::in(['approve', 'suspend', 'commission'])],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ], [], ['commission_percent' => 'comisión']);

        switch ($data['action']) {
            case 'approve':
                $organizer->update(['status' => Organizer::STATUS_ACTIVE]);
                $msg = "«{$organizer->name}» quedó aprobada.";
                break;

            case 'suspend':
                $organizer->update(['status' => Organizer::STATUS_SUSPENDED]);
                $msg = "«{$organizer->name}» quedó suspendida.";
                break;

            case 'commission':
                $organizer->update([
                    'commission_percent' => $request->filled('commission_percent') ? $data['commission_percent'] : null,
                ]);
                $msg = "Comisión de «{$organizer->name}» actualizada.";
                break;
        }

        return back()->with('success', $msg);
    }
}
