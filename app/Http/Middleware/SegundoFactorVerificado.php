<?php

namespace App\Http\Middleware;

use App\Autenticacion\SegundoFactorDeLaSesion;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nadie ve el panel sin haber escrito su código de seis dígitos.
 *
 * El capítulo 10 del documento 4 lo pide obligatorio para todo el equipo, y
 * «obligatorio» aquí significa dos cosas seguidas:
 *
 * 1. Quien todavía no tiene segundo factor **no puede seguir hasta activarlo.**
 *    No es una pantalla de ajustes que se pueda aplazar: es un desvío del que
 *    solo se sale activándolo.
 * 2. Quien ya lo tiene escribe el código **una vez por sesión.**
 *
 * Va detrás de `SoloProveedor`: primero se responde quién es, después qué le
 * falta. Al contrario, un usuario de empresa recibiría un desvío a activar el
 * segundo factor en lugar del 403 que le corresponde.
 */
class SegundoFactorVerificado
{
    public function __construct(private readonly SegundoFactorDeLaSesion $sesion) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if (! $usuario instanceof User) {
            abort(403);
        }

        if (! $usuario->tieneSegundoFactor()) {
            return redirect()->route('proveedor.segundo-factor');
        }

        if (! $this->sesion->verificada($usuario)) {
            return redirect()->route('proveedor.verificacion');
        }

        return $siguiente($peticion);
    }
}
