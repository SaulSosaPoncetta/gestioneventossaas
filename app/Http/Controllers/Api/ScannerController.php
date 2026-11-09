<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * API del escáner de puerta (la usan la PWA y la app Android).
 * Autenticación: token Sanctum con la habilidad "scanner".
 */
class ScannerController extends Controller
{
    public function __construct(private CheckinService $checkins)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'device'   => ['nullable', 'string', 'max:80'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'El email o la contraseña no son correctos.']);
        }

        if (! $user->isPlatformAdmin() && ! $user->organizers()->exists()) {
            throw ValidationException::withMessages(['email' => 'Tu usuario no pertenece a ninguna productora.']);
        }

        $device = $data['device'] ?? 'escaner';
        $token = $user->createToken('escaner:' . $device, ['scanner'], now()->addDays(30));

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'user'       => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    /** Eventos que el usuario puede controlar: de hoy en adelante (y los que terminaron hace menos de 12 h) */
    public function events(Request $request): JsonResponse
    {
        $user = $request->user();

        $events = Event::with('venue:id,name')
            ->when(! $user->isPlatformAdmin(), fn ($q) => $q->whereIn('organizer_id', $user->organizers()->select('organizers.id')))
            ->whereIn('status', [Event::STATUS_PUBLISHED, Event::STATUS_PAUSED, Event::STATUS_FINISHED])
            ->where('starts_at', '>=', now()->subHours(12))
            ->where('starts_at', '<=', now()->addDays(30))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $e) => [
                'id'        => $e->id,
                'title'     => $e->title,
                'starts_at' => $e->starts_at->toIso8601String(),
                'venue'     => $e->venue?->name,
                'stats'     => $this->checkins->stats($e),
            ]);

        return response()->json(['events' => $events]);
    }

    /** Paquete offline (completo, o incremental con ?since=fecha ISO) */
    public function package(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);

        $since = $request->filled('since') ? Carbon::parse($request->query('since'))->subSeconds(5) : null;

        return response()->json($this->checkins->package($event, $since) + ['stats' => $this->checkins->stats($event)]);
    }

    /** Escaneo en línea (la decisión la toma el servidor) */
    public function scan(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);

        $data = $request->validate([
            'code'        => ['required', 'string', 'max:255'],
            'client_uuid' => ['nullable', 'uuid'],
            'gate'        => ['nullable', 'string', 'max:40'],
            'device'      => ['nullable', 'string', 'max:80'],
            'scanned_at'  => ['nullable', 'date'],
        ]);

        $result = $this->checkins->scan($event, $data['code'], $request->user(), $data);

        return response()->json($result + ['stats' => $this->checkins->stats($event)]);
    }

    /** Sube los escaneos hechos sin conexión */
    public function sync(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);

        $data = $request->validate([
            'scans'               => ['required', 'array', 'max:500'],
            'scans.*.client_uuid' => ['required', 'uuid'],
            'scans.*.code'        => ['required', 'string', 'max:255'],
            'scans.*.scanned_at'  => ['nullable', 'date'],
            'scans.*.gate'        => ['nullable', 'string', 'max:40'],
            'scans.*.device'      => ['nullable', 'string', 'max:80'],
        ]);

        $results = [];

        foreach ($data['scans'] as $scan) {
            $results[] = ['client_uuid' => $scan['client_uuid']]
                + $this->checkins->scan($event, $scan['code'], $request->user(), $scan + ['offline' => true]);
        }

        return response()->json([
            'results' => $results,
            'stats'   => $this->checkins->stats($event),
        ]);
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        $user = $request->user();

        abort_unless($user->tokenCan('scanner'), 403, 'Token sin permiso de escáner.');
        abort_unless($user->canScanFor($event->organizer_id), 403, 'No tenés acceso a este evento.');
    }
}
