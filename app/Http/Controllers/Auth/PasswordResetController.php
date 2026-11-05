<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    private const MESSAGES = [
        Password::RESET_LINK_SENT => 'Si el email está registrado, te enviamos un enlace para restablecer la contraseña.',
        Password::PASSWORD_RESET  => 'Tu contraseña fue restablecida. Ya podés ingresar.',
        Password::INVALID_TOKEN   => 'El enlace no es válido o ya venció. Pedí uno nuevo.',
        Password::INVALID_USER    => 'El enlace no es válido o ya venció. Pedí uno nuevo.',
        Password::RESET_THROTTLED => 'Esperá unos minutos antes de volver a intentar.',
    ];

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        // No revelamos si el email existe o no
        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => self::MESSAGES[$status]]);
        }

        return back()->with('success', self::MESSAGES[Password::RESET_LINK_SENT]);
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [], ['password' => 'contraseña']);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', self::MESSAGES[$status]);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => self::MESSAGES[$status] ?? 'No se pudo restablecer la contraseña.']);
    }
}
