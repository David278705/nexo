<?php

namespace App\Http\Controllers\Acceso;

use App\Autenticacion\LimitadorDeIntentos;
use App\Autenticacion\RegistroDeAccesos;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entrar y salir del sistema.
 *
 * Las tres cosas que esta pantalla tiene que hacer bien, según la sección 5.1
 * del documento de diseño y la 7.1 del de arquitectura:
 *
 * 1. **Un solo mensaje de error, que no diga si el correo existe.** Contestar
 *    «ese correo no está registrado» le regala a cualquiera la lista de quién
 *    trabaja en la empresa.
 * 2. **Espera creciente tras varios fallos**, que la resuelve
 *    `LimitadorDeIntentos`.
 * 3. **Todo intento queda registrado**, el bueno y los malos.
 */
class SesionController extends Controller
{
    public function __construct(
        private readonly LimitadorDeIntentos $limitador,
        private readonly RegistroDeAccesos $registro,
    ) {}

    public function formulario(): Response
    {
        return Inertia::render('Acceso/IniciarSesion');
    }

    public function entrar(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'correo' => ['required', 'string', 'email'],
            'contrasena' => ['required', 'string'],
            'recordarme' => ['boolean'],
        ]);

        $correo = mb_strtolower(trim($datos['correo']));
        $ip = $peticion->ip();

        // El usuario se busca antes de comprobar nada para poder registrar el
        // intento con su empresa. Que exista no cambia ni una palabra de lo que
        // se le responde a quien está al otro lado.
        $usuario = User::query()->where('email', $correo)->first();

        $espera = $this->limitador->segundosDeEspera($correo, $ip);

        if ($espera > 0) {
            $this->registro->bloqueado($peticion, $correo, $usuario);

            throw ValidationException::withMessages([
                'correo' => $this->mensajeDeEspera($espera),
            ]);
        }

        $credenciales = ['email' => $correo, 'password' => $datos['contrasena']];

        if (! Auth::attempt($credenciales, (bool) ($datos['recordarme'] ?? false))) {
            $this->registro->fallido($peticion, $correo, $usuario);
            $espera = $this->limitador->registrarFallo($correo, $ip);

            throw ValidationException::withMessages([
                'correo' => $espera > 0
                    ? 'Correo o contraseña incorrectos. '.$this->mensajeDeEspera($espera)
                    : 'Correo o contraseña incorrectos.',
            ]);
        }

        /** @var User $autenticado */
        $autenticado = Auth::user();

        $peticion->session()->regenerate();
        $this->limitador->limpiar($correo, $ip);
        $this->registro->exitoso($peticion, $autenticado);

        return redirect()->intended(route('inicio'));
    }

    public function salir(Request $peticion): RedirectResponse
    {
        Auth::guard('web')->logout();

        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return redirect()->route('acceso');
    }

    /**
     * La espera dicha en la unidad en que se entiende.
     *
     * «Espera 180 segundos» obliga a dividir mentalmente; «espera 3 minutos»,
     * no. Por debajo del minuto sí se dicen segundos, que es la única forma de
     * no redondear a «1 minuto» algo que dura veinte segundos.
     */
    private function mensajeDeEspera(int $segundos): string
    {
        if ($segundos < 60) {
            return "Demasiados intentos fallidos. Espera {$segundos} segundos e inténtalo de nuevo.";
        }

        $minutos = (int) ceil($segundos / 60);
        $unidad = $minutos === 1 ? 'minuto' : 'minutos';

        return "Demasiados intentos fallidos. Espera {$minutos} {$unidad} e inténtalo de nuevo.";
    }
}
