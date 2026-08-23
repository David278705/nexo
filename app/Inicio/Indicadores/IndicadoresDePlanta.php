<?php

namespace App\Inicio\Indicadores;

use App\Contratacion\EstadoVinculacion;
use App\Contratacion\TipoContrato;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * El bloque «Planta» del panel de inicio (documento 1, 12.1, bloque 3).
 *
 * Los dos indicadores del documento: empleados activos con su variación frente
 * al mes anterior, y la distribución por tipo de contrato.
 */
class IndicadoresDePlanta
{
    /**
     * @return array{titulo: string, tarjetas: list<array<string, mixed>>, distribuciones: list<array<string, mixed>>}
     */
    public static function paraLaEmpresa(Carbon $hoy): array
    {
        $activosHoy = self::activosEl($hoy);
        $activosMesAnterior = self::activosEl($hoy->copy()->subMonthNoOverflow());
        $variacion = $activosHoy - $activosMesAnterior;

        return [
            'titulo' => 'Planta',
            'tarjetas' => [
                [
                    'valor' => $activosHoy,
                    'etiqueta' => 'Empleados activos',
                    'variacion' => abs($variacion).' frente al mes anterior',
                    'tendencia' => match (true) {
                        $variacion > 0 => 'sube',
                        $variacion < 0 => 'baja',
                        default => '',
                    },
                ],
            ],
            'distribuciones' => [
                [
                    'titulo' => 'Distribución por tipo de contrato',
                    'items' => self::distribucionPorTipoDeContrato(),
                ],
            ],
        ];
    }

    /**
     * Cuántas vinculaciones estaban activas en una fecha dada, sin recorrer la
     * planta una por una.
     *
     * Una vinculación estaba activa en `$fecha` si ya había ingresado y, o
     * sigue activa hoy, o la salida registrada —por terminación o, a falta de
     * ella, por `fecha_fin`, la misma jerarquía que sigue
     * `Vinculacion::fechaDeSalida()`— fue posterior a esa fecha. Se resuelve en
     * SQL y no con el motor porque no es un conteo de días: es una comparación
     * de fechas, y así es como esta cifra puede convivir con el resto del panel
     * dentro del umbral de dos segundos.
     */
    public static function activosEl(Carbon $fecha): int
    {
        $fecha = Carbon::parse($fecha->toDateString());

        return Vinculacion::query()
            ->where('fecha_ingreso', '<=', $fecha)
            ->where(function (Builder $consulta) use ($fecha): void {
                $consulta->where('estado', EstadoVinculacion::Activo->value)
                    ->orWhereHas('terminacion', fn (Builder $q) => $q->where('fecha_salida', '>', $fecha))
                    ->orWhere(function (Builder $q) use ($fecha): void {
                        $q->where('estado', EstadoVinculacion::Inactivo->value)
                            ->whereDoesntHave('terminacion')
                            ->where('fecha_fin', '>', $fecha);
                    });
            })
            ->count();
    }

    /**
     * @return list<array{etiqueta: string, cuantos: int}>
     */
    private static function distribucionPorTipoDeContrato(): array
    {
        $porTipo = Vinculacion::query()
            ->activas()
            ->selectRaw('tipo_contrato, count(*) as cuantos')
            ->groupBy('tipo_contrato')
            ->pluck('cuantos', 'tipo_contrato');

        return array_map(
            fn (TipoContrato $tipo): array => [
                'etiqueta' => $tipo->etiqueta(),
                'cuantos' => (int) ($porTipo[$tipo->value] ?? 0),
            ],
            TipoContrato::cases(),
        );
    }
}
