<?php

namespace App\Inicio\Indicadores;

use App\Dominio\Calculo\CacheDeSaldos;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Vacacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * El bloque «Vacaciones» del panel de inicio (documento 1, 12.1, bloque 2).
 *
 * Los tres indicadores son literalmente los tres de la fila del documento:
 * días acumulados por toda la planta, personas con acumulación excesiva y
 * días disfrutados en el periodo. Ninguno lleva comparación contra el mes
 * anterior: esa cifra solo la pide el documento para el bloque de Planta.
 *
 * **Las dos primeras salen de la caché de saldos (F4-02) y no del motor.**
 * Es la misma razón por la que existe la caché: sumar o contar sobre
 * doscientos empleados llamando a `SaldoDeVacaciones` uno por uno —lo que ya
 * hace la tarjeta de acción diferida de `AtencionDelInicio`— tarda segundos, y
 * aquí no hay ninguna decisión sobre una persona que dependa de que el número
 * sea exacto al segundo: es un indicador informativo del panorama de
 * vacaciones, no un enlace a un listado de gente concreta. La caché puede dar
 * un número de ayer; nunca uno inventado.
 */
class IndicadoresDeVacaciones
{
    /**
     * @return array{titulo: string, tarjetas: list<array<string, mixed>>, distribuciones: list<array<string, mixed>>}
     */
    public static function paraLaEmpresa(Carbon $hoy): array
    {
        $total = CacheDeSaldos::total();
        $actualizado = 'Actualizado al '.Formato::fechaLarga($total->al).'.';
        $disfrutados = self::disfrutadosEnElMes($hoy);

        return [
            'titulo' => 'Vacaciones',
            'tarjetas' => [
                [
                    'valor' => Formato::numeroDecimal($total->dias),
                    'etiqueta' => 'Días acumulados por toda la planta',
                    'nota' => 'Es un pasivo real de la empresa. '.$actualizado,
                ],
                [
                    'valor' => CacheDeSaldos::conAcumulacionExcesiva(),
                    'etiqueta' => 'Personas con acumulación excesiva',
                    'nota' => 'Con '.Formato::diasDecimales(SaldoDeVacaciones::diasDeUnPeriodoCompleto())
                        .' o más acumulados. '.$actualizado,
                ],
                [
                    'valor' => Formato::numeroDecimal($disfrutados['dias']),
                    'etiqueta' => 'Días disfrutados en '.Formato::mes($hoy),
                    'nota' => $disfrutados['periodos'] === 1
                        ? 'En 1 periodo registrado'
                        : 'En '.$disfrutados['periodos'].' periodos registrados',
                ],
            ],
            'distribuciones' => [],
        ];
    }

    /**
     * Los días hábiles de vacaciones cuyo disfrute empezó este mes, hasta hoy.
     *
     * «El periodo» del documento 1, 12.1 se lee como el mes en curso —es lo
     * que enseña el prototipo, «Días disfrutados en agosto»—, y solo hasta hoy:
     * una vacación que ya se registró para la semana entrante todavía no se ha
     * disfrutado, y contarla adelantaría un número que aún no es cierto.
     *
     * **«Hasta hoy» vale para las dos fechas y no solo para la de inicio.** Un
     * periodo que empezó el lunes y termina la semana entrante lleva
     * disfrutados los días vividos, no los pactados, así que el fin se recorta
     * contra hoy igual que hace `IndicadoresDeAusentismo` con una incapacidad
     * que sigue abierta. El recorte es aritmética de fechas y no una regla de
     * conteo nueva: quien cuenta sigue siendo
     * `MotorDeCalculo::diasHabilesVacaciones()`, con la regla de días hábiles
     * que le corresponde a vacaciones y a nada más.
     *
     * @return array{dias: float, periodos: int}
     */
    private static function disfrutadosEnElMes(Carbon $hoy): array
    {
        $desde = $hoy->copy()->startOfMonth();

        // La empresa se trae por delante: sin esto, cada fila volvería a
        // pedirla para poder decidir si el sábado es hábil.
        $vacaciones = Vacacion::query()
            ->with('vinculacion.tenant')
            ->whereBetween('fecha_inicio', [$desde, $hoy])
            ->get();

        $dias = $vacaciones->sum(fn (Vacacion $vacacion): int => MotorDeCalculo::diasHabilesVacaciones(
            $vacacion->fecha_inicio,
            $vacacion->fecha_fin->min($hoy),
            $vacacion->vinculacion->tenant,
        ));

        return [
            'dias' => (float) $dias,
            'periodos' => $vacaciones->count(),
        ];
    }
}
