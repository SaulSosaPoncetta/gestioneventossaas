<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Mail\TeamInviteMail;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Equipo de la productora: dueños, administradores, staff y operadores de puerta */
class TeamController extends Controller
{
    public const ROLES = [
        Organizer::ROLE_OWNER => ['Dueño', 'Todo, incluido Mercado Pago y el equipo'],
        Organizer::ROLE_ADMIN => ['Administrador', 'Todo, incluido Mercado Pago y el equipo (no puede nombrar dueños)'],
        Organizer::ROLE_STAFF => ['Staff', 'Eventos, entradas, cupones, órdenes y reportes'],
        Organizer::ROLE_DOOR  => ['Puerta', 'Solo el escáner de ingreso'],
    ];

    private function organizer(): Organizer
    {
        return app('currentOrganizer');
    }

    private function myRole(Request $request): ?string
    {
        return $request->user()->roleIn($this->organizer());
    }

    public function index(): View
    {
        $members = $this->organizer()->users()->orderBy('name')->get();

        return view('organizer.team', ['members' => $members, 'roles' => self::ROLES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organizer = $this->organizer();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'role'  => ['required', Rule::in(array_keys(self::ROLES))],
        ], [], ['name' => 'nombre', 'role' => 'rol']);

        if ($data['role'] === Organizer::ROLE_OWNER && $this->myRole($request) !== Organizer::ROLE_OWNER) {
            return back()->with('error', 'Solo un dueño puede agregar otro dueño.');
        }

        $user = User::where('email', $data['email'])->first();

        if ($user && $organizer->users()->whereKey($user->id)->exists()) {
            return back()->with('error', 'Esa persona ya es parte del equipo.');
        }

        $isNew = ! $user;
        if ($isNew) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => strtolower($data['email']),
                'password' => Str::random(40), // la define la persona con el enlace del mail
            ]);
        }

        $organizer->users()->attach($user->id, ['role' => $data['role']]);

        // A los nuevos se les envía un enlace para crear su contraseña
        $url = $isNew
            ? route('password.reset', ['token' => Password::createToken($user), 'email' => $user->email])
            : route('login');

        Mail::to($user->email, $user->name)->queue(new TeamInviteMail($organizer, $user, self::ROLES[$data['role']][0], $url, $isNew));

        return back()->with('success', "{$user->name} se sumó al equipo como " . self::ROLES[$data['role']][0] . '. Le enviamos un mail.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $organizer = $this->organizer();
        abort_unless($organizer->users()->whereKey($user->id)->exists(), 404);

        $role = $request->validate(['role' => ['required', Rule::in(array_keys(self::ROLES))]])['role'];
        $current = $organizer->users()->whereKey($user->id)->first()->pivot->role;

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No podés cambiar tu propio rol.');
        }
        if (($role === Organizer::ROLE_OWNER || $current === Organizer::ROLE_OWNER) && $this->myRole($request) !== Organizer::ROLE_OWNER) {
            return back()->with('error', 'Solo un dueño puede nombrar o modificar dueños.');
        }
        if ($current === Organizer::ROLE_OWNER && $role !== Organizer::ROLE_OWNER && $this->ownersCount() <= 1) {
            return back()->with('error', 'La productora tiene que tener al menos un dueño.');
        }

        $organizer->users()->updateExistingPivot($user->id, ['role' => $role]);

        return back()->with('success', "Rol de {$user->name} actualizado.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $organizer = $this->organizer();
        $member = $organizer->users()->whereKey($user->id)->first();
        abort_unless($member, 404);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No podés quitarte a vos mismo del equipo.');
        }
        if ($member->pivot->role === Organizer::ROLE_OWNER) {
            if ($this->myRole($request) !== Organizer::ROLE_OWNER) {
                return back()->with('error', 'Solo un dueño puede quitar a otro dueño.');
            }
            if ($this->ownersCount() <= 1) {
                return back()->with('error', 'La productora tiene que tener al menos un dueño.');
            }
        }

        $organizer->users()->detach($user->id);

        // Se cierran sus sesiones del escáner
        $user->tokens()->delete();

        return back()->with('success', "{$user->name} ya no forma parte del equipo.");
    }

    private function ownersCount(): int
    {
        return $this->organizer()->users()->wherePivot('role', Organizer::ROLE_OWNER)->count();
    }
}
