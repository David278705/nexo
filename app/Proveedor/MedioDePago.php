<?php

namespace App\Proveedor;

use App\Contratacion\OpcionesDeLista;

/**
 * Con qué paga el cliente su suscripción (documento 4, capítulo 6, «medio de
 * pago», y la columna del prototipo).
 *
 * Los dos que el prototipo enseña: débito automático y transferencia. No se
 * inventa una lista de pasarelas ni de franquicias, porque la pasarela de pagos
 * con cobro recurrente es la decisión A.6 del documento 6 y todavía no existe:
 * hoy el cobro lo registra David a mano cuando le entra la plata, y lo único que
 * hace falta saber es si esa plata llega sola o si hay que esperarla.
 */
enum MedioDePago: string
{
    use OpcionesDeLista;

    case DebitoAutomatico = 'debito_automatico';

    case Transferencia = 'transferencia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::DebitoAutomatico => 'Débito automático',
            self::Transferencia => 'Transferencia',
        };
    }
}
