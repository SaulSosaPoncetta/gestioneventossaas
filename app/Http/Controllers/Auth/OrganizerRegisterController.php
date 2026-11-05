<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class OrganizerRegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register-organizer');
    }

    public function store(Request $request): RedirectResponse
    {
        $guest = ! Auth::check();

        $rules = [
            'organizer_name'  => ['required', 'string', 'max:255'],
            'organizer_email' => ['required', 'email', 'max:255'],
            'organizer_phone' => ['nullable', 'string', 'max:30'],
            'cuit'            => ['nullable', 'regex:/^\d{2}-?\d{8}-?\d$/'],
            'legal_name'      => ['nullable', 'string', 'max:255'],
        ];

        if ($guest) {
            $rules += [
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
                'dni'      => ['nullable', 'digits_between:7,9'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ];
        }

        $data = $request->validate($rules, [
            'cuit.regex' => 'El CUIT debe tener 11 dígitos (ej: 20-12345678-9).',
        ], [
            'organizer_name'  => 'nombre de la productora',
            'organizer_email' => 'email de la productora',
            'organizer_phone' => 'teléfono',
            'legal_name'      => 'razón social',
            'name'            => 'tu nombre',
            'dni'             => 'DNI',
            'password'        => 'contraseña',
        ]);

        $organizer = DB::transaction(function () use ($data, $guest) {
            $user = $guest
                ? User::create([
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'dni'      => $data['dni'] ?? null,
                    'password' => $data['password'],
                ])
                : Auth::user();

            $organizer = Organizer::create([
                'name'       => $data['organizer_name'],
                'email'      => $data['organizer_email'],
                'phone'      => $data['organizer_phone'] ?? null,
                'cuit'       => isset($data['cuit']) ? preg_replace('/\D/', '', $data['cuit']) : null,
                'legal_name' => $data['legal_name'] ?? null,
                'status'     => Organizer::STATUS_PENDING,
            ]);

            $organizer->users()->attach($user->id, ['role' => Organizer::ROLE_OWNER]);

            if ($guest) {
                Auth::login($user);
            }

            return $organizer;
        });

        $request->session()->regenerate();
        $request->session()->put('current_organizer_id', $organizer->id);

        return redirect()->route('organizer.dashboard')
            ->with('success', 'Tu productora fue registrada. La revisaremos para habilitar la venta de entradas.');
    }
}
