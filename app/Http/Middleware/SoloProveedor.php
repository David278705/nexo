<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La puerta del panel del proveedor: solo el rol `owner` pasa.
 *
 * Ningún usuario de una empresa cliente puede alcanzar el panel, y eso incluye
 * al superadministrador, que dentro de su empresa lo puede todo. Aquí recibe 403
 * igual que cualquiera, porque el panel no es una sección más del sistema del
 * cliente: es el otro lado del negocio, con los datos de todas las empresas a la
 * vez.
 *
 * No es un módulo de `PoliticaDeModulos` a propósito. Esa política responde qué
 * ve un usuario **dentro de su empresa**, y su primera línea es que el `owner`
 * no ve nada de eso. El panel es la pregunta simétrica y se contesta aquí.
 */
class SoloProveedor
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if (! $usuario instanceof User || ! $usuario->esOwner()) {
            abort(403);
        }

        return $siguiente($peticion);
    }
}
