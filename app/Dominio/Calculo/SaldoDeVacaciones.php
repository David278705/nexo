<?php

namespace App\Dominio\Calculo;

use App\Models\Vinculacion;
use Carbon\CarbonInterface;

/**
 * El saldo de vacaciones de una vinculación a una fecha (regla 2 del proyecto).
 *
 * ```
 * saldo(fecha) = saldo_inicial
 *              + acumulación desde fecha_corte_saldo hasta fecha
 *              − días de vacaciones disfrutados
 *              − reducción por suspensiones, si la empresa lo tiene activo
 * ```
 *
 * **No existe ninguna columna `saldo_vacaciones` que se sume y se reste.** Es lo
 * que hace que corregir la fecha de ingreso recalcule todo solo y que eliminar
 * unas vacaciones restituya el saldo sin operación inversa.
 *
 * Como el resto del motor, aquí todavía no hay cálculo: F2-01 escribe la firma y
 * los casos; F2-02 escribe la cuenta.
 */
class SaldoDeVacaciones
{
    private function __construct(public readonly Vinculacion $vinculacion) {}

    public static function de(Vinculacion $vinculacion): self
    {
        return new self($vinculacion);
    }

    /**
     * Los días de vacaciones disponibles a esa fecha.
     *
     * **El mes se causa completo al cerrarse, no día a día.** Lo fijan dos de
     * los diez casos obligatorios leídos juntos: el saldo del 31 de julio y el
     * proyectado al 28 de agosto son el mismo, 12,75 días. Agosto no aporta nada
     * hasta que agosto termina.
     */
    public function al(CarbonInterface $fecha): float
    {
        throw CalculoSinImplementar::en(__METHOD__);
    }

    /**
     * Cuántos días faltan para poder disfrutar esos días en esa fecha, o cero si
     * el saldo alcanza.
     *
     * Se compara contra el saldo proyectado **a la fecha de disfrute**, no contra
     * el de hoy: quien pide vacaciones para dentro de tres meses habrá acumulado
     * tres meses más. Lo usa la advertencia de saldo insuficiente (F2-06), que
     * avisa y no bloquea.
     */
    public function faltantePara(float $dias, CarbonInterface $fecha): float
    {
        throw CalculoSinImplementar::en(__METHOD__);
    }
}
