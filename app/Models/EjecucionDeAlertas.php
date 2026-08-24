<?php

namespace App\Models;

use Database\Factories\EjecucionDeAlertasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una corrida del proceso diario de alertas, ya terminada (F5-06, documento 4,
 * capítulo 9). La escribe `App\Dominio\Alertas\RegistroDeEjecuciones`.
 *
 * @property Carbon $fecha
 * @property Carbon $ejecutada_en
 * @property int $duracion_ms
 * @property int $empresas
 * @property int $alertas_nuevas
 * @property int $alertas_repetidas
 * @property int $sin_destinatarios
 * @property int $saldos_refrescados
 * @property int $empresas_fallidas
 * @property list<array{empresa: string, motivo: string}> $detalle_fallidas
 * @property bool $exitosa
 */
class EjecucionDeAlertas extends Model
{
    /** @use HasFactory<EjecucionDeAlertasFactory> */
    use HasFactory;

    protected $table = 'ejecuciones_de_alertas';

    /** @var list<string> */
    protected $fillable = [
        'fecha',
        'ejecutada_en',
        'duracion_ms',
        'empresas',
        'alertas_nuevas',
        'alertas_repetidas',
        'sin_destinatarios',
        'saldos_refrescados',
        'empresas_fallidas',
        'detalle_fallidas',
        'exitosa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'ejecutada_en' => 'datetime',
            'detalle_fallidas' => 'array',
            'exitosa' => 'boolean',
        ];
    }
}
