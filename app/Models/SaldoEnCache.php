<?php

namespace App\Models;

use App\Multiempresa\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El saldo de vacaciones de un empleado, ya calculado y copiado aquí para poder
 * sumarlo con el resto de la planta (documento 2, 3.4).
 *
 * **Nunca es la fuente de verdad.** Quien pregunta por el saldo de una persona
 * —la ficha, la advertencia de saldo insuficiente, los resúmenes mensuales de
 * vacaciones— se lo pregunta a `SaldoDeVacaciones`, que lo calcula. Esta tabla
 * la lee un solo consumidor, `CacheDeSaldos::total()`, y solo para el total de
 * la empresa.
 *
 * Quien la escribe es también uno solo, `CacheDeSaldos::refrescar()`, llamado
 * cada noche por el proceso diario de alertas. Ningún controlador la toca.
 *
 * @property int $id
 * @property int $vinculacion_id
 * @property float $dias
 * @property Carbon $calculado_al
 * @property Carbon $calculado_en
 * @property-read Vinculacion $vinculacion
 */
class SaldoEnCache extends Model
{
    use PerteneceATenant;

    protected $table = 'saldos_en_cache';

    /** @var list<string> */
    protected $fillable = [
        'vinculacion_id',
        'dias',
        'calculado_al',
        'calculado_en',
    ];

    /**
     * @return BelongsTo<Vinculacion, $this>
     */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    /**
     * `dias` se guarda como decimal de precisión fija y se lee como número: es
     * la misma cifra que devuelve `SaldoDeVacaciones::al()` y tiene que poder
     * sumarse con ella sin convertir nada por el camino.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dias' => 'float',
            'calculado_al' => 'date',
            'calculado_en' => 'datetime',
        ];
    }
}
