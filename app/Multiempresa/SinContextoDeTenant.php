<?php

namespace App\Multiempresa;

use RuntimeException;

/**
 * Se lanza cuando algo intenta escribir datos de negocio sin que se haya
 * fijado la empresa. Es preferible reventar a escribir una fila huérfana o,
 * peor, en la empresa equivocada.
 */
class SinContextoDeTenant extends RuntimeException
{
    public static function alEscribir(string $modelo): self
    {
        return new self(
            "No hay empresa en contexto y se intentó crear un registro de [{$modelo}]. ".
            'Fija la empresa con ContextoTenant::paraTenant() antes de escribir.'
        );
    }
}
