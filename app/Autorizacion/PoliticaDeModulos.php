<?php

namespace App\Autorizacion;

use App\Models\User;
use App\Proveedor\AccesoAlEntorno;

/**
 * Quién puede entrar a qué módulo. Es el único sitio donde se decide.
 *
 * Se registra como la habilidad `modulo` en `AppServiceProvider`, así que la
 * pregunta se hace siempre igual desde cualquier parte:
 *
 *     Gate::allows('modulo', Modulo::Vacaciones)
 *
 * El middleware `modulo:` la consulta en cada petición y el menú la consulta
 * para saber qué pintar. Ocultar una opción del menú no es un control de
 * acceso; el control es este, y corre en el servidor.
 */
class PoliticaDeModulos
{
    public function puedeAcceder(User $usuario, Modulo $modulo): bool
    {
        /*
         * El `owner` no entra al entorno del cliente por esta puerta salvo
         * durante una visita registrada (documento 4, 4.4). Su sitio es el panel
         * del proveedor; lo que abre esta puerta es haber entrado de forma
         * expresa al entorno de una empresa, con su motivo escrito y su
         * constancia en la auditoría de ella.
         *
         * Durante la visita ve todos los módulos, como el superadministrador de
         * esa empresa: entra a resolver un problema que el cliente no sabe
         * describir y no se sabe de antemano en qué módulo está. **Lo que no
         * puede es escribir**: de eso responde `EntornoDeSoloLectura`, en el
         * servidor y en cada petición. Aquí se decide qué se ve, no qué se toca.
         */
        if ($usuario->esOwner()) {
            return app(AccesoAlEntorno::class)->enCurso() !== null;
        }

        // Un usuario de empresa sin empresa no debería existir. Si existe, no
        // ve nada: sin contexto no hay nada que autorizar.
        if ($usuario->tenant_id === null) {
            return false;
        }

        if ($modulo->esSoloDelSuperadministrador()) {
            return $usuario->esSuperadministrador();
        }

        if ($usuario->esSuperadministrador()) {
            return true;
        }

        if ($modulo->esDeTodos()) {
            return true;
        }

        return $usuario->tieneModuloAsignado($modulo);
    }

    /**
     * Los módulos a los que este usuario sí puede entrar.
     *
     * Es lo que se comparte con la interfaz para armar el menú. Se calcula con
     * el mismo método de arriba para que menú y servidor no puedan discrepar.
     *
     * @return list<Modulo>
     */
    public function modulosPermitidos(User $usuario): array
    {
        return array_values(array_filter(
            Modulo::cases(),
            fn (Modulo $modulo) => $this->puedeAcceder($usuario, $modulo),
        ));
    }
}
