<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la empresa de la petición, una sola vez, desde el usuario
 * autenticado.
 *
 * Jamás desde la URL, el formulario o una cabecera: si el cliente pudiera
 * decir de qué empresa es, el aislamiento entero sería decorativo.
 */
class ResolverTenant
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        // Se empieza por limpiar. La empresa vive también en una variable de
        // sesión de PostgreSQL, y una conexión reutilizada —la cola, Octane, un
        // pool— llega con la de la petición anterior puesta. Fijarla sin
        // limpiarla antes funcionaría siempre menos justo cuando no hay usuario,
        // que es cuando peor está fallar.
        $this->contexto->olvidar();

        // Sin usuario, o con un usuario sin empresa —el `owner` del panel del
        // proveedor—, no se fija contexto. El global scope falla cerrado, así
        // que eso significa no ver nada, no verlo todo.
        $identificador = $usuario?->getAttribute('tenant_id');

        if ($identificador !== null) {
            $tenant = Tenant::find($identificador);

            if ($tenant instanceof Tenant) {
                $this->contexto->fijar($tenant);
            }
        }

        return $siguiente($peticion);
    }
}
