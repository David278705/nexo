<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\AccesoAlEntorno;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la empresa de la petición, una sola vez, desde el usuario
 * autenticado.
 *
 * Jamás desde la URL, el formulario o una cabecera: si el cliente pudiera
 * decir de qué empresa es, el aislamiento entero sería decorativo.
 *
 * **Hay una sola excepción y es la del documento 4, 4.4**: el `owner` dentro de
 * una visita al entorno de un cliente. Tampoco ahí la empresa la dice la
 * petición: la dice la fila de `visitas_al_entorno` que el servidor escribió al
 * entrar, y de la sesión sale únicamente su identificador. Es el mismo principio
 * —la empresa la decide el servidor— aplicado al único rol que atraviesa
 * empresas.
 */
class ResolverTenant
{
    public function __construct(
        private readonly ContextoTenant $contexto,
        private readonly AccesoAlEntorno $acceso,
    ) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        // Se empieza por limpiar. La empresa vive también en una variable de
        // sesión de PostgreSQL, y una conexión reutilizada —la cola, Octane, un
        // pool— llega con la de la petición anterior puesta. Fijarla sin
        // limpiarla antes funcionaría siempre menos justo cuando no hay usuario,
        // que es cuando peor está fallar.
        $this->contexto->olvidar();

        // Por el mismo motivo se olvida la visita al entorno de un cliente: es
        // un singleton, y lo que resolvió en la petición anterior no vale para
        // esta.
        $this->acceso->olvidar();

        // Sin usuario, o con un usuario sin empresa —el `owner` del panel del
        // proveedor—, no se fija contexto. El global scope falla cerrado, así
        // que eso significa no ver nada, no verlo todo.
        $identificador = $usuario?->getAttribute('tenant_id');

        if ($identificador !== null) {
            $tenant = Tenant::find($identificador);

            if ($tenant instanceof Tenant) {
                $this->contexto->fijar($tenant);
            }

            return $siguiente($peticion);
        }

        // El `owner` dentro de una visita al entorno de un cliente (4.4). Se
        // pregunta después de lo anterior y no antes: un usuario de empresa
        // nunca puede quedar dentro de otra por este camino, porque para él ya
        // se resolvió arriba.
        $visitada = $this->acceso->empresaVisitada();

        if ($visitada instanceof Tenant) {
            $this->contexto->fijar($visitada);
        }

        return $siguiente($peticion);
    }
}
