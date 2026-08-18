<?php

namespace App\Http\Middleware;

use App\Autorizacion\Modulo;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta la petición si el usuario no tiene acceso al módulo de la ruta.
 *
 * Se declara en la ruta con el nombre del módulo:
 *
 *     Route::get('/vacaciones', ...)->middleware('modulo:vacaciones');
 *
 * Está aquí y no en la interfaz porque esconder una opción del menú no impide
 * escribir la dirección a mano. Quien no tiene el módulo recibe 403 del
 * servidor, venga por donde venga.
 */
class VerificarModulo
{
    public function handle(Request $peticion, Closure $siguiente, string $modulo): Response
    {
        $usuario = $peticion->user();

        if (! $usuario instanceof User) {
            abort(403);
        }

        if (Gate::forUser($usuario)->denies('modulo', $this->modulo($modulo))) {
            abort(403);
        }

        return $siguiente($peticion);
    }

    /**
     * Un nombre de módulo que no existe es un error de programación en la
     * declaración de la ruta, no una petición inválida del usuario. Se rompe
     * ruidosamente en lugar de responder 403, que escondería la errata.
     */
    private function modulo(string $modulo): Modulo
    {
        return Modulo::tryFrom($modulo) ?? throw new InvalidArgumentException(
            "La ruta declara el módulo «{$modulo}», que no existe en App\\Autorizacion\\Modulo."
        );
    }
}
