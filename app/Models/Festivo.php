<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un día festivo del calendario colombiano. Catálogo global del sistema: la
 * tabla es única, no por empresa (documento 1, capítulo 10).
 *
 * De aquí salen el conteo de días hábiles de vacaciones y el corrimiento de las
 * alertas al día hábil anterior. Este modelo solo lee el calendario; las tres
 * reglas de conteo son del motor de cálculo y llegan en la fase 2.
 *
 * @property Carbon $fecha
 */
class Festivo extends Model
{
    /** @var list<string> */
    protected $fillable = ['fecha', 'nombre', 'anio'];

    /**
     * Si esa fecha es festiva. Una sola consulta, sin cargar el año entero.
     */
    public static function esFestivo(CarbonInterface $fecha): bool
    {
        return static::query()
            ->where('fecha', $fecha->toDateString())
            ->exists();
    }

    /**
     * Las fechas festivas de un año, como cadenas `Y-m-d`.
     *
     * Devuelve texto y no objetos a propósito: quien pregunta si un día es
     * festivo lo hace dentro de un bucle sobre un rango de fechas, y comparar
     * cadenas en un conjunto es más barato y más difícil de equivocar que
     * comparar objetos con hora y zona horaria.
     *
     * @return list<string>
     */
    public static function fechasDelAnio(int $anio): array
    {
        return static::query()
            ->where('anio', $anio)
            ->orderBy('fecha')
            ->pluck('fecha')
            // `pluck` trae el valor crudo de la columna, que en PostgreSQL
            // llega con hora. Aquí se promete `Y-m-d` y eso es lo que sale.
            ->map(fn (mixed $fecha): string => Carbon::parse((string) $fecha)->toDateString())
            ->values()
            ->all();
    }

    /**
     * Los años que ya tienen calendario cargado.
     *
     * Lo usa el aviso del panel del proveedor: si el año siguiente no aparece
     * aquí, el calendario está sin cargar y hay que avisarlo antes de que
     * empiece, no en enero.
     *
     * @return list<int>
     */
    public static function aniosCargados(): array
    {
        return static::query()
            ->select('anio')
            ->distinct()
            ->orderBy('anio')
            ->pluck('anio')
            ->map(fn (mixed $anio): int => (int) $anio)
            ->values()
            ->all();
    }

    /**
     * @param  Builder<$this>  $consulta
     * @return Builder<$this>
     */
    public function scopeDelAnio(Builder $consulta, int $anio): Builder
    {
        return $consulta->where('anio', $anio)->orderBy('fecha');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Sin hora: es una fecha laboral, como todas las de este sistema.
            'fecha' => 'date',
            'anio' => 'integer',
        ];
    }
}
