<?php

namespace App\Proveedor;

use App\Contratacion\OpcionesDeLista;

/**
 * El estado de una suscripción (documento 4, capítulo 6).
 *
 * Son dos y no más, porque son las dos que el documento nombra: la suscripción
 * está activa, o está suspendida por la escalera de mora. No hay «cancelada»
 * todavía y no es un olvido: la salida de un cliente es la sección A.4 del
 * documento 6 —exportación, periodo de gracia, eliminación y certificado— y
 * ninguno de esos pasos existe como acción del panel.
 *
 * **El estado de la suscripción no es el estado de la empresa**, aunque
 * suspender mueva los dos: `EstadoDeEmpresa` tiene además «en prueba», que es
 * comercial y no de cobro, y una empresa en prueba no tiene suscripción de la
 * que hablar.
 */
enum EstadoDeSuscripcion: string
{
    use OpcionesDeLista;

    case Activa = 'activa';

    case Suspendida = 'suspendida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Suspendida => 'Suspendida',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Activa => 'exito',
            self::Suspendida => 'error',
        };
    }

    public function estaSuspendida(): bool
    {
        return $this === self::Suspendida;
    }
}
