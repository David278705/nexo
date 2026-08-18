<?php

namespace App\Auditoria;

use RuntimeException;

/**
 * Se lanza cuando alguien intenta editar o borrar una línea de auditoría.
 *
 * Es una excepción y no un `return false` silencioso a propósito: si en algún
 * momento aparece código que toca la auditoría, tiene que romperse en la
 * primera prueba que lo ejecute, no pasar de largo sin hacer nada.
 */
class AuditoriaInmutable extends RuntimeException
{
    public static function alEditar(): self
    {
        return new self(
            'La auditoría no se edita. Una línea escrita es lo que pasó, y lo que pasó no cambia.'
        );
    }

    public static function alBorrar(): self
    {
        return new self(
            'La auditoría no se borra desde la aplicación, ni siquiera siendo superadministrador.'
        );
    }
}
