<?php

namespace App\Http\Controllers\Acceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Olvidé mi contraseña»: se pide el correo y se manda el enlace.
 *
 * La respuesta es **siempre la misma**, exista el correo o no, con el texto
 * literal de la sección 5.1. Si dijera «no encontramos ese correo», esta
 * pantalla sería un comprobador público de quién tiene cuenta.
 */
class RecuperacionController extends Controller
{
    /**
     * El texto neutro. Es el mismo en los dos casos y por eso vive aquí una
     * sola vez.
     */
    public const MENSAJE_NEUTRO = 'Si el correo está registrado, recibirás instrucciones.';

    public function formulario(): Response
    {
        return Inertia::render('Acceso/RecuperarContrasena');
    }

    public function enviar(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'correo' => ['required', 'string', 'email'],
        ]);

        // El resultado del broker se ignora a propósito: distinguirlo aquí es
        // justo lo que no se puede contar. El propio broker limita la
        // frecuencia de envío con `passwords.users.throttle`.
        Password::sendResetLink(['email' => mb_strtolower(trim($datos['correo']))]);

        return back()->with('estado', self::MENSAJE_NEUTRO);
    }
}
