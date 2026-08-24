<?php

namespace App\Models;

use Database\Factories\MetricaMensualFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * La foto de cierre de un mes, para la variación de las dos cifras de negocio
 * del inicio del panel (F5-06, documento 4, 3.2). La escribe
 * `App\Console\Commands\CapturarMetricasMensuales`.
 *
 * @property string $mes
 * @property int $empresas_activas
 * @property float $ingreso_recurrente
 * @property Carbon $capturado_en
 */
class MetricaMensual extends Model
{
    /** @use HasFactory<MetricaMensualFactory> */
    use HasFactory;

    protected $table = 'metricas_mensuales';

    /** @var list<string> */
    protected $fillable = [
        'mes',
        'empresas_activas',
        'ingreso_recurrente',
        'capturado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capturado_en' => 'datetime',
            'ingreso_recurrente' => 'float',
        ];
    }
}
