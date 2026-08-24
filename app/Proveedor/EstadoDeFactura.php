<?php

namespace App\Proveedor;

use App\Contratacion\OpcionesDeLista;

/**
 * El estado de una fila del historial de facturas y pagos (documento 4, capítulo
 * 6 y 17.2, `pagos`: «suscripción, monto, fecha, estado, número de factura»).
 *
 * **Dos, y la que falta es una decisión.** Una fila de `pagos` es plata que
 * entró: nace pagada. No existe el estado «pendiente» porque una factura sin
 * pagar no se registra como fila —lo que dice que hay plata por cobrar es la
 * próxima fecha de cobro de la suscripción ya vencida, que es exactamente el
 * indicador de mora del listado de empresas—. Registrar además la factura
 * pendiente sería contar el mismo hecho dos veces y dejar que las dos versiones
 * se contradigan.
 *
 * «Anulada» sí existe, y es la aplicación de la regla 4 del proyecto: un pago
 * mal registrado no se borra, se anula, y sigue en el historial con su número de
 * factura. Anular deshace exactamente lo que hizo registrar —devuelve la próxima
 * fecha de cobro al mes anterior—, igual que eliminar unas vacaciones devuelve
 * su saldo.
 */
enum EstadoDeFactura: string
{
    use OpcionesDeLista;

    case Pagada = 'pagada';

    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pagada => 'Pagada',
            self::Anulada => 'Anulada',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Pagada => 'exito',
            self::Anulada => 'neutro',
        };
    }

    public function estaAnulada(): bool
    {
        return $this === self::Anulada;
    }
}
