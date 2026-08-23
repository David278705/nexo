<?php

namespace App\Inicio\Indicadores;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Incapacidades\OrigenIncapacidad;
use App\Licencias\TipoDeLicencia;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * El bloque «Ausentismo» del panel de inicio (documento 1, 12.1, bloque 5).
 *
 * Se parte en dos porque sus cinco indicadores no vienen del mismo módulo:
 * cuatro son de Incapacidades y uno —días perdidos por ausencias no
 * justificadas— es de Licencias, que en este sistema son permisos
 * independientes (documento 1, cap. 3: «una persona puede encargarse solo de
 * vacaciones»). `BloqueDeIndicadores` arma el bloque con la mitad que el
 * usuario tenga, las dos, o ninguna.
 *
 * El índice de ausentismo, igual que el de rotación, es la fórmula estándar
 * del indicador —días perdidos sobre el total de días-persona disponibles del
 * mes, por cien— y no un valor legal: es un criterio de implementación, no una
 * pregunta para Angela.
 *
 * **Ni el numerador ni el denominador miran más allá de hoy.** Una
 * incapacidad certificada por adelantado hasta fin de mes no ha perdido todavía
 * los días que faltan por vivirse, así que contarlos de una vez inflaría el
 * índice de los primeros días del mes. Por eso el recorte es contra `$hoy` y
 * no contra el fin del mes, y el denominador son los días transcurridos del
 * mes y no los que tiene en total.
 */
class IndicadoresDeAusentismo
{
    /**
     * @return array{tarjetas: list<array<string, mixed>>, distribuciones: list<array<string, mixed>>}
     */
    public static function porIncapacidad(Carbon $hoy): array
    {
        $desde = $hoy->copy()->startOfMonth();
        $hasta = $hoy->copy();

        $incapacidades = Incapacidad::query()
            ->where('fecha_inicio', '<=', $hasta)
            ->where('fecha_fin', '>=', $desde)
            ->get();

        $diasOrigenComun = self::diasPerdidosEnElMes(
            $incapacidades->filter(fn (Incapacidad $incapacidad): bool => $incapacidad->origen === OrigenIncapacidad::Comun),
            $desde,
            $hasta,
        );

        $diasOrigenLaboral = self::diasPerdidosEnElMes(
            $incapacidades->filter(fn (Incapacidad $incapacidad): bool => $incapacidad->origen === OrigenIncapacidad::Laboral),
            $desde,
            $hasta,
        );

        $registradasEsteMes = $incapacidades->filter(
            fn (Incapacidad $incapacidad): bool => $incapacidad->fecha_inicio->betweenIncluded($desde, $hoy)
        );

        $activosHoy = IndicadoresDePlanta::activosEl($hoy);
        $disponibles = $activosHoy * $hoy->day;

        $indice = $disponibles > 0
            ? (($diasOrigenComun + $diasOrigenLaboral) / $disponibles) * 100
            : 0.0;

        return [
            'tarjetas' => [
                [
                    'valor' => Formato::porcentaje($indice),
                    'etiqueta' => 'Ausentismo por causa médica',
                ],
                [
                    'valor' => $diasOrigenComun,
                    'etiqueta' => 'Días perdidos — origen común',
                ],
                [
                    'valor' => $diasOrigenLaboral,
                    'etiqueta' => 'Días perdidos — origen laboral',
                ],
                [
                    'valor' => $registradasEsteMes->count(),
                    'etiqueta' => 'Incapacidades de '.Formato::mes($hoy),
                ],
            ],
            'distribuciones' => [
                [
                    'titulo' => 'Distribución por diagnóstico',
                    'items' => self::distribucionPorDiagnostico($registradasEsteMes),
                ],
            ],
        ];
    }

    /**
     * @return array{tarjetas: list<array<string, mixed>>, distribuciones: list<array<string, mixed>>}
     */
    public static function porAusencias(Carbon $hoy): array
    {
        $desde = $hoy->copy()->startOfMonth();
        $hasta = $hoy->copy();

        $ausencias = Licencia::query()
            ->where('tipo', TipoDeLicencia::Ausencia)
            ->where('fecha_inicio', '<=', $hasta)
            ->where('fecha_fin', '>=', $desde)
            ->get();

        return [
            'tarjetas' => [
                [
                    'valor' => self::diasPerdidosEnElMes($ausencias, $desde, $hasta),
                    'etiqueta' => 'Ausencias no justificadas',
                ],
            ],
            'distribuciones' => [],
        ];
    }

    /**
     * Los días corridos de un conjunto de novedades que caen dentro del mes,
     * recortados a sus dos extremos.
     *
     * Una novedad que empezó en julio y sigue en agosto solo aporta sus días de
     * agosto: el mes anterior ya los contó cuando le tocó a él. El recorte es
     * aritmética de fechas y no una regla de conteo nueva; quien cuenta sigue
     * siendo `MotorDeCalculo::diasCorridos()`.
     *
     * @param  iterable<Incapacidad|Licencia>  $novedades
     */
    private static function diasPerdidosEnElMes(iterable $novedades, Carbon $desde, Carbon $hasta): int
    {
        $dias = 0;

        foreach ($novedades as $novedad) {
            $inicio = $novedad->fecha_inicio->max($desde);
            $fin = $novedad->fecha_fin->min($hasta);
            $dias += MotorDeCalculo::diasCorridos($inicio, $fin);
        }

        return $dias;
    }

    /**
     * @param  Collection<int, Incapacidad>  $incapacidades
     * @return list<array{etiqueta: string, cuantos: int}>
     */
    private static function distribucionPorDiagnostico(Collection $incapacidades): array
    {
        return $incapacidades
            ->countBy(fn (Incapacidad $incapacidad): string => $incapacidad->codigo_diagnostico)
            ->sortDesc()
            ->map(fn (int $cuantos, string $diagnostico): array => ['etiqueta' => $diagnostico, 'cuantos' => $cuantos])
            ->values()
            ->all();
    }
}
