<?php

namespace App\Dominio\Calculo;

use App\Models\Vacacion;
use App\Models\Vinculacion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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
 * unas vacaciones restituya el saldo sin operación inversa: `Vacacion` usa
 * borrado lógico, así que una vez eliminada deja de sumar en
 * `diasDisfrutadosHasta()` sin que nadie tenga que restar nada a mano.
 *
 * El último sumando todavía vale cero y se dice dónde: la reducción por
 * suspensiones vive en `MotorDeCalculo::acumulacionMes()`, a la espera de la
 * tabla de F2-07. Hoy no tiene de dónde leer un solo día.
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
        $hasta = Carbon::parse($fecha->toDateString());

        $saldo = $this->saldoInicial()
            + $this->acumulacionHasta($hasta)
            - $this->diasDisfrutadosHasta($hasta);

        // Cuatro decimales: 1,25 y sus proporciones no son exactos en coma
        // flotante y un saldo de 12,749999999 no se puede comparar con nada.
        // Quien lo escribe en pantalla lo redondea a dos, y eso es `Formato`.
        return round($saldo, 4);
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
        return round(max(0.0, $dias - $this->al($fecha)), 4);
    }

    /**
     * Los días hábiles de las vacaciones ya registradas cuyo inicio no es
     * posterior a la fecha consultada.
     *
     * El borrado lógico es lo que hace que restituir el saldo no necesite una
     * operación inversa: una vacación eliminada deja de contar aquí sola.
     */
    private function diasDisfrutadosHasta(Carbon $fecha): int
    {
        return (int) $this->vinculacion->vacaciones()
            ->where('fecha_inicio', '<=', $fecha)
            ->get()
            ->sum(fn (Vacacion $vacacion): int => $vacacion->dias());
    }

    /**
     * Lo que trajo la carga inicial, que solo se lee y nunca se suma ni se resta.
     */
    private function saldoInicial(): float
    {
        return (float) $this->vinculacion->getAttribute('saldo_inicial_vacaciones');
    }

    /**
     * Lo acumulado desde el primer día que no cubre el saldo inicial hasta la
     * fecha consultada, mes calendario por mes calendario.
     *
     * Solo entran los meses **ya cerrados** a esa fecha, que es la regla de
     * arriba. Cada mes acumula lo que dice `MotorDeCalculo::acumulacionMes()`
     * —que ya descuenta los días sin vínculo y los de suspensión—, reducido a la
     * parte del mes que queda después de la fecha de corte. Esa fracción vale
     * uno en todos los meses salvo, como mucho, en el del corte.
     */
    private function acumulacionHasta(Carbon $fecha): float
    {
        $inicio = $this->inicioDeLaAcumulacion();

        if ($inicio->greaterThan($fecha)) {
            return 0.0;
        }

        $acumulado = 0.0;

        for ($mes = $inicio->copy()->startOfMonth(); ; $mes->addMonth()) {
            // Sin hora en los dos lados: el último día del mes son las 23:59:59
            // y la fecha consultada las 00:00, y el 31 de julio se quedaría
            // fuera de su propio mes.
            if ($mes->copy()->endOfMonth()->startOfDay()->greaterThan($fecha)) {
                break;
            }

            $acumulado += MotorDeCalculo::acumulacionMes($this->vinculacion, $mes)
                * $this->fraccionPosteriorAlCorte($inicio, $mes);
        }

        return $acumulado;
    }

    /**
     * El primer día que todavía no está contado en el saldo inicial.
     *
     * Es el día siguiente a la fecha de corte cuando la vinculación viene de una
     * carga inicial, y la fecha de ingreso cuando no: sin corte, el saldo
     * inicial es cero y la acumulación corre desde el primer día trabajado.
     */
    private function inicioDeLaAcumulacion(): Carbon
    {
        $corte = $this->vinculacion->getAttribute('fecha_corte_saldo');

        return $corte instanceof Carbon
            ? $corte->copy()->addDay()->startOfDay()
            : $this->vinculacion->fecha_ingreso->copy()->startOfDay();
    }

    /**
     * Qué parte de la acumulación de ese mes es posterior al corte.
     *
     * Uno en los meses completamente posteriores, cero en los anteriores, y la
     * proporción comercial en el mes del corte, que es lo que pide la fórmula
     * del documento 2: «1,25 días por mes completo trabajado, prorrateada para
     * los meses parciales».
     *
     * **Se mide contra los días que ese mes ya tenía vínculo, no contra treinta.**
     * `acumulacionMes()` viene descontados los días sin vínculo, y volver a
     * dividirlos por treinta los descontaría dos veces: quien ingresa el 16 de
     * marzo sin carga inicial acumularía un cuarto de mes en lugar de medio.
     */
    private function fraccionPosteriorAlCorte(Carbon $inicio, Carbon $mes): float
    {
        $salida = $this->vinculacion->fechaDeSalida();

        $conVinculo = MotorDeCalculo::diasComercialesDelMes($this->vinculacion->fecha_ingreso, $salida, $mes);

        if ($conVinculo === 0) {
            return 0.0;
        }

        return MotorDeCalculo::diasComercialesDelMes($inicio->max($this->vinculacion->fecha_ingreso), $salida, $mes)
            / $conVinculo;
    }
}
