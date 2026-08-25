<?php

namespace App\Models;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Licencias\TipoDeLicencia;
use App\Multiempresa\PerteneceATenant;
use App\Novedades\ConfirmacionDeBorrado;
use App\Novedades\NovedadEditable;
use Database\Factories\LicenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Una licencia no remunerada, ausencia o suspensión de un empleado
 * (documento 1, 6.5).
 *
 * Cuelga de la vinculación, no de la persona: es la regla 5 del proyecto,
 * igual que incapacidades y vacaciones.
 *
 * **No hay columna de días.** Se cuentan en días corridos —domingos y
 * festivos incluidos— con `dias()`, que llama al motor cada vez, nunca una
 * columna que pueda quedar desincronizada de `fecha_inicio` y `fecha_fin` si
 * alguien las corrige (F2-10).
 *
 * @property int $id
 * @property int $vinculacion_id
 * @property TipoDeLicencia $tipo
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property-read Vinculacion $vinculacion
 */
class Licencia extends Model implements NovedadEditable
{
    /** @use HasFactory<LicenciaFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'licencias';

    /** @var list<string> */
    protected $fillable = [
        'vinculacion_id',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
    ];

    /**
     * @return BelongsTo<Vinculacion, $this>
     */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    /**
     * Los días corridos de esta novedad (documento 1, 6.5): domingos y
     * festivos sí cuentan, la misma regla que incapacidades y distinta de
     * vacaciones.
     */
    public function dias(): int
    {
        return MotorDeCalculo::diasCorridos($this->fecha_inicio, $this->fecha_fin);
    }

    public function vinculacionDeLaNovedad(): Vinculacion
    {
        return $this->vinculacion;
    }

    /**
     * @return array<string, string|bool|int|null>
     */
    public function valoresParaEdicion(): array
    {
        return [
            'vinculacion_id' => $this->vinculacion_id,
            'tipo' => $this->tipo->value,
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin->toDateString(),
        ];
    }

    /**
     * Vacío: el único campo de solo lectura del formulario son los días
     * corridos, y los cuenta el servidor con lo que se está digitando.
     *
     * @return array<string, string>
     */
    public function contextoDeEdicion(): array
    {
        return [];
    }

    public function confirmacionDeBorrado(): string
    {
        return ConfirmacionDeBorrado::deLicencia($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'tipo' => TipoDeLicencia::class,
        ];
    }
}
