<?php

namespace App\Autorizacion;

use App\Models\User;

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
        // El `owner` no entra al entorno del cliente por esta puerta. Su sitio
        // es el panel del proveedor, y cuando se le dé acceso al entorno de una
        // empresa (F5-07) será de solo lectura y quedando en la auditoría de
        // esa empresa. Mientras eso no exista, aquí es un no.
        if ($usuario->esOwner()) {
            return false;
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
