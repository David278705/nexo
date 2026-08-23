<?php

namespace App\Inicio\Indicadores;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Models\Terminacion;
use App\Presentacion\Formato;
use App\Terminaciones\CategoriaDeRotacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * El bloque «Rotación» del panel de inicio (documento 1, 12.1, bloque 4).
 *
 * Los cuatro indicadores del documento: índice de rotación del mes, voluntaria
 * frente a involuntaria, rotación temprana —salidas de menos de seis meses de
 * vinculación, que es la propia definición que trae el documento— y los
 * principales motivos de salida del periodo.
 *
 * ## El índice de rotación no está en ningún documento con una fórmula
 *
 * Es la fórmula estándar del indicador —salidas del periodo sobre el promedio
 * de activos del mismo periodo, por cien—, la misma que usa cualquier sistema
 * de gestión humana. No es un valor legal ni una cifra que el documento 6 deje
 * pendiente de un asesor: es una razón aritmética, y por eso esta tarea la usa
 * en lugar de bloquearse, con la misma clase de criterio de implementación con
 * que F3-05 fijó la cadencia de los meses impares.
 *
 * ## «Principales motivos» no recorta a los primeros
 *
 * No existe un criterio para decidir cuántos son «los principales», y
 * cualquier número sería inventado —la misma razón por la que D.2 descartó un
 * umbral para la obra o labor prolongada—. Se muestran todos los motivos que
 * tuvieron al menos una salida en el mes, ordenados de mayor a menor.
 */
class IndicadoresDeRotacion
{
    /**
     * Los meses de vinculación por debajo de los cuales una salida cuenta como
     * rotación temprana. Es la definición literal del documento 1, 12.1: «menos
     * de seis meses de vinculación».
     */
    private const MESES_ROTACION_TEMPRANA = 6;

    /**
     * @return array{titulo: string, tarjetas: list<array<string, mixed>>, distribuciones: list<array<string, mixed>>}
     */
    public static function paraLaEmpresa(Carbon $hoy): array
    {
        $desde = $hoy->copy()->startOfMonth();

        $terminaciones = Terminacion::query()
            ->with('vinculacion')
            ->whereBetween('fecha_salida', [$desde, $hoy])
            ->get();

        $activosInicio = IndicadoresDePlanta::activosEl($desde->copy()->subDay());
        $activosHoy = IndicadoresDePlanta::activosEl($hoy);
        $promedioActivos = ($activosInicio + $activosHoy) / 2;

        $voluntarias = $terminaciones->filter(
            fn (Terminacion $terminacion): bool => $terminacion->categoriaDeRotacion() === CategoriaDeRotacion::Voluntaria
        )->count();

        $involuntarias = $terminaciones->filter(
            fn (Terminacion $terminacion): bool => $terminacion->categoriaDeRotacion() === CategoriaDeRotacion::Involuntaria
        )->count();

        $rotacionTemprana = $terminaciones->filter(
            fn (Terminacion $terminacion): bool => MotorDeCalculo::mesesCumplidos(
                $terminacion->vinculacion->fecha_ingreso,
                $terminacion->fecha_salida,
            ) < self::MESES_ROTACION_TEMPRANA
        )->count();

        $indice = $promedioActivos > 0.0
            ? ($terminaciones->count() / $promedioActivos) * 100
            : 0.0;

        return [
            'titulo' => 'Rotación',
            'tarjetas' => [
                [
                    'valor' => Formato::porcentaje($indice),
                    'etiqueta' => 'Índice de rotación de '.Formato::mes($hoy),
                ],
                [
                    'valor' => $voluntarias.' / '.$involuntarias,
                    'etiqueta' => 'Voluntaria / involuntaria',
                ],
                [
                    'valor' => $rotacionTemprana,
                    'etiqueta' => 'Rotación temprana (menos de 6 meses)',
                ],
            ],
            'distribuciones' => [
                [
                    'titulo' => 'Principales motivos de salida',
                    'items' => self::principalesMotivos($terminaciones),
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, Terminacion>  $terminaciones
     * @return list<array{etiqueta: string, cuantos: int}>
     */
    private static function principalesMotivos(Collection $terminaciones): array
    {
        return $terminaciones
            ->countBy(fn (Terminacion $terminacion): string => $terminacion->motivo)
            ->sortDesc()
            ->map(fn (int $cuantos, string $motivo): array => ['etiqueta' => $motivo, 'cuantos' => $cuantos])
            ->values()
            ->all();
    }
}
