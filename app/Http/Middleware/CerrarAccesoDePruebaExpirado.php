<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de quien entró con un acceso de prueba y ya venció
 * (documento 4, capítulo 10).
 *
 * `AccesoDePruebaController` comprueba la vigencia una sola vez, al entrar.
 * Una visita puede durar más que el acceso mismo si el prospecto la dejó
 * abierta varios días, así que hace falta comprobarlo en cada petición y no
 * solo esa vez: es lo que hace este middleware, con la fecha que se guardó en
 * la sesión al entrar.
 *
 * Una sesión de un usuario normal nunca trae esa clave, así que para el resto
 * del sistema este middleware no hace nada.
 */
class CerrarAccesoDePruebaExpirado
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $expiraEn = $peticion->session()->get('acceso_de_prueba_expira_en');

        if (is_string($expiraEn) && Carbon::parse($expiraEn)->isPast()) {
            Auth::guard('web')->logout();
            $peticion->session()->invalidate();
            $peticion->session()->regenerateToken();

            return redirect()->route('acceso')->with(
                'estado',
                'El acceso de demostración venció. Pide uno nuevo si quieres seguir explorando.',
            );
        }

        return $siguiente($peticion);
    }
}
