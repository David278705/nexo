<?php

namespace App\Autorizacion;

/**
 * Los tres roles del sistema, tal como los define el capítulo 3 de la
 * especificación funcional.
 *
 * Se guarda el texto legible en la base de datos, no un número: una fila de
 * `users` tiene que poder leerse sin una tabla de traducción en la cabeza.
 */
enum Rol: string
{
    /** El equipo dueño del producto. Vive en el panel del proveedor. */
    case Owner = 'owner';

    /** La cuenta empresa. Una sola por cliente, con acceso a todo. */
    case Superadministrador = 'superadmin';

    /** Usuario creado por el superadministrador, con los módulos que le asignen. */
    case Administrador = 'admin';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Owner => 'Proveedor',
            self::Superadministrador => 'Superadministrador',
            self::Administrador => 'Administrador',
        };
    }

    /**
     * Los roles que pertenecen a una empresa cliente.
     *
     * El `owner` no está: atraviesa empresas y no es de ninguna.
     *
     * @return list<self>
     */
    public static function deEmpresa(): array
    {
        return [self::Superadministrador, self::Administrador];
    }
}
