<?php

namespace App\Models;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Incapacidades\OrigenIncapacidad;
use App\Multiempresa\PerteneceATenant;
use Database\Factories\IncapacidadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Una incapacidad médica de un empleado (documento 1, 6.3).
 *
 * Cuelga de la vinculación, no de la persona: es la regla 5 del proyecto,
 * igual que los periodos contractuales.
 *
 * **No hay columna de días.** Del 14 al 16 de julio son tres días, y esa
 * cuenta la hace `dias()` llamando al motor cada vez, nunca una columna que
 * pueda quedar desincronizada de `fecha_inicio` y `fecha_fin` si alguien las
 * corrige (F2-10).
 *
 * @property int $id
 * @property int $vinculacion_id
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property string $codigo_diagnostico
 * @property bool $es_prorroga
 * @property int $eps_id
 * @property OrigenIncapacidad $origen
 * @property-read Vinculacion $vinculacion
 * @property-read Eps $eps
 */
class Incapacidad extends Model
{
    /** @use HasFactory<IncapacidadFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'incapacidades';

    /** @var list<string> */
    protected $fillable = [
        'vinculacion_id',
        'fecha_inicio',
        'fecha_fin',
        'codigo_diagnostico',
        'es_prorroga',
        'eps_id',
        'origen',
    ];

    /**
     * @return BelongsTo<Vinculacion, $this>
     */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    /**
     * @return BelongsTo<Eps, $this>
     */
    public function eps(): BelongsTo
    {
        return $this->belongsTo(Eps::class);
    }

    /**
     * Los días de esta incapacidad, corridos e incluyendo el inicial y el
     * final. Se usa: `dias()` y no ninguna resta de fechas, en ningún sitio
     * fuera de aquí.
     */
    public function dias(): int
    {
        return MotorDeCalculo::diasCorridos($this->fecha_inicio, $this->fecha_fin);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'es_prorroga' => 'boolean',
            'origen' => OrigenIncapacidad::class,
        ];
    }
}
