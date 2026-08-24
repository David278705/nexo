<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso de una empresa cuyo servicio está suspendido (documento 4,
 * capítulo 6).
 *
 * Es la mitad que hace verdad la frase del capítulo: «suspender bloquea el
 * acceso al sistema pero no elimina nada y no detiene los cálculos internos. Al
 * reactivar, todo sigue donde estaba». Aquí no se borra ni se esconde ningún
 * dato: **lo único que se impide es entrar**, y se impide en el servidor y en
 * cada petición, no ocultando el menú.
 *
 * Va después de `ResolverTenant` porque pregunta por la empresa en contexto, que
 * es la del usuario autenticado. Tres caminos quedan fuera a propósito:
 *
 * - **El `owner`**, que no tiene empresa: el panel del proveedor es justamente
 *   desde donde se reactiva, y bloquearlo dejaría la suspensión sin marcha atrás.
 * - **Cerrar sesión**, que un usuario suspendido tiene que poder hacer.
 * - **La propia pantalla de aviso**, o el redirigido sería infinito.
 *
 * No cierra la sesión: quien está dentro cuando le suspenden el servicio ve la
 * explicación y a quién llamar, no una pantalla de acceso sin motivo. Y cuando
 * se reactiva, la petición siguiente ya entra: el estado se lee de la empresa en
 * cada petición y no se guarda en la sesión.
 */
class BloquearEmpresaSuspendida
{
    /**
     * Las rutas que un usuario de una empresa suspendida sí puede pedir.
     *
     * @var list<string>
     */
    private const PERMITIDAS = ['suspendido', 'acceso.salir'];

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $empresa = tenant_actual();

        if (! $empresa instanceof Tenant || ! $empresa->estaSuspendida()) {
            return $siguiente($peticion);
        }

        // El `owner` dentro de una visita al entorno de un cliente (documento 4,
        // 4.4). Desde F5-07 sí tiene empresa en contexto, así que sin esta línea
        // el proveedor sería el único que no podría entrar a mirar los datos de
        // un cliente suspendido —que es justo cuando hay que mirarlos, porque el
        // cliente llama para reclamar y no puede enseñar su pantalla—. Lo que la
        // suspensión bloquea es el acceso **del cliente**.
        if ($peticion->user()?->esOwner() === true) {
            return $siguiente($peticion);
        }

        if (in_array($peticion->route()?->getName(), self::PERMITIDAS, true)) {
            return $siguiente($peticion);
        }

        return redirect()->route('suspendido');
    }
}
