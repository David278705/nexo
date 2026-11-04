<?php

namespace App\Http\Middleware;

use App\Legal\AceptacionDeLaEmpresa;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nadie de una empresa usa el sistema hasta que ella acepta los documentos
 * legales vigentes: los términos del servicio y el acuerdo de tratamiento de
 * datos.
 *
 * Es la misma forma que `BloquearEmpresaSuspendida`, y va justo detrás: una
 * empresa suspendida ve primero su suspensión. La razón de bloquear y no solo
 * avisar es la del documento 6, B.4: sin acuerdo de tratamiento, la empresa
 * estaría cargando datos de salud de sus empleados en un sistema de terceros
 * sin que conste que lo autorizó en esas condiciones.
 *
 * Quedan fuera:
 *
 * - **El `owner`**, dentro de una visita: mira, no acepta a nombre de nadie.
 * - **La demostración y las cuentas de prueba**: datos ficticios, que nadie trata.
 */
class ExigirAceptacionLegal
{
    /**
     * Lo que se puede pedir con documentos pendientes: la pantalla para
     * aceptarlos, leerlos, la suspensión y cerrar sesión.
     *
     * @var list<string>
     */
    private const PERMITIDAS = ['aceptacion', 'aceptacion.aceptar', 'legal.documento', 'suspendido', 'acceso.salir'];

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $empresa = tenant_actual();

        if (! $empresa instanceof Tenant || $empresa->esFicticia()) {
            return $siguiente($peticion);
        }

        if ($peticion->user()?->esOwner() === true) {
            return $siguiente($peticion);
        }

        if (in_array($peticion->route()?->getName(), self::PERMITIDAS, true)) {
            return $siguiente($peticion);
        }

        if (AceptacionDeLaEmpresa::de($empresa)->estaAlDia()) {
            return $siguiente($peticion);
        }

        return redirect()->route('aceptacion');
    }
}
