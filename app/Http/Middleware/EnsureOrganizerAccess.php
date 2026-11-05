<?php

namespace App\Http\Middleware;

use App\Models\Organizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la productora activa del usuario (guardada en sesión) y valida su rol.
 *
 * Uso:  ->middleware('organizer')               cualquier miembro del equipo
 *       ->middleware('organizer:owner,admin')   solo esos roles
 */
class EnsureOrganizerAccess
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $organizers = $user->organizers()->orderBy('name')->get();

        if ($organizers->isEmpty()) {
            return redirect()->route('organizer.register')
                ->with('info', 'Primero registrá tu productora.');
        }

        /** @var Organizer $current */
        $current = $organizers->firstWhere('id', (int) $request->session()->get('current_organizer_id'))
            ?? $organizers->first();

        $request->session()->put('current_organizer_id', $current->id);

        $role = $current->pivot->role;

        if ($roles !== [] && ! in_array($role, $roles, true) && ! $user->isPlatformAdmin()) {
            abort(403, 'No tenés permiso para esta sección.');
        }

        // Disponible en controladores (app('currentOrganizer')) y en todas las vistas
        app()->instance('currentOrganizer', $current);
        $request->attributes->set('organizer', $current);

        View::share([
            'currentOrganizer' => $current,
            'currentRole'      => $role,
            'myOrganizers'     => $organizers,
        ]);

        return $next($request);
    }
}
