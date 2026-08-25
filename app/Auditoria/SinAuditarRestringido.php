<?php

namespace App\Auditoria;

use RuntimeException;

/**
 * Se lanza cuando algo intenta usar `ObservadorDeAuditoria::sinAuditar()`
 * fuera del ambiente de demostración.
 *
 * El bloque sin auditar existe por una sola razón: la restauración nocturna
 * de una planta ficticia que no le pertenece a ningún cliente. Abrirlo para
 * cualquier otra empresa sería la forma más fácil de esconder una edición
 * real, así que el guardia revienta en vez de dejarlo pasar en silencio.
 */
class SinAuditarRestringido extends RuntimeException
{
    public static function fueraDelAmbienteDeDemostracion(): self
    {
        return new self(
            'ObservadorDeAuditoria::sinAuditar() solo se puede usar dentro del ambiente de demostración.'
        );
    }
}
