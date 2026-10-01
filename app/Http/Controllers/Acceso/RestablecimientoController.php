<?php

namespace App\Http\Controllers\Acceso;

use App\Autenticacion\RegistroDeAccesos;
use App\Autenticacion\ReglasDeContrasena;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pantalla a la que lleva el enlace del correo: contraseña nueva.
 *
 * Al guardar se entra directamente al sistema, como pide la sección 5.1. Quien
 * acaba de demostrar que tiene acceso al buzón y ha fijado una contraseña no
 * gana nada teniendo que escribirla otra vez en la pantalla de acceso.
 *
 * La caducidad de una hora del enlace no se comprueba aquí: la impone el broker
 * de Laravel con `config('auth.passwords.users.expire')`, que está en 60.
 */
class RestablecimientoController extends Controller
{
    public function __construct(private readonly RegistroDeAccesos $registro) {}

    public function formulario(Request $peticion, string $token): Response
    {
        return Inertia::render('Acceso/RestablecerContrasena', [
            'token' => $token,
            'correo' => (string) $peticion->query('correo', ''),
        ]);
    }

    public function guardar(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'token' => ['required', 'string'],
            'correo' => ['required', 'string', 'email'],
            'contrasena' => ReglasDeContrasena::reglas(),
        ]);

        $estado = Password::reset([
            'email' => mb_strtolower(trim($datos['correo'])),
            'password' => $datos['contrasena'],
            'password_confirmation' => (string) $peticion->input('contrasena_confirmation'),
            'token' => $datos['token'],
        ], function (User $usuario, string $contrasena) use ($peticion) {
            $usuario->forceFill([
                'password' => $contrasena,
                // Se invalida el «recordarme» de todos los dispositivos: si la
                // contraseña se cambió porque alguien más la tenía, dejar viva
                // una sesión recordada en otro navegador anula el cambio.
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($usuario));

            Auth::login($usuario);
            $peticion->session()->regenerate();
            $this->registro->exitoso($peticion, $usuario);
        });

        if ($estado !== Password::PasswordReset) {
            // El texto sale de `lang/es/passwords.php`. Cubre el enlace vencido
            // y el ya usado, que son el mismo caso para quien lo lee.
            throw ValidationException::withMessages(['correo' => __($estado)]);
        }

        // La sesión ya quedó iniciada dentro del `reset`; desde aquí cada quien
        // va a su propia página de inicio, que para el `owner` es el panel.
        return redirect()->to($peticion->user()?->paginaDeInicio() ?? '/');
    }
}
