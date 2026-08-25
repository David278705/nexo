<?php

namespace App\Models;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Multiempresa\PerteneceATenant;
use App\Novedades\ConfirmacionDeBorrado;
use App\Novedades\NovedadEditable;
use Database\Factories\VacacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Un periodo de vacaciones disfrutado por un empleado (documento 1, 6.4).
 *
 * Cuelga de la vinculación, no de la persona: es la regla 5 del proyecto,
 * igual que las incapacidades.
 *
 * **No hay columna de días.** Se cuentan en días hábiles con
 * `dias()`, que llama al motor cada vez, nunca una columna que pueda quedar
 * desincronizada de `fecha_inicio` y `fecha_fin` si alguien las corrige
 * (F2-10). `SaldoDeVacaciones::al()` resta esos días del saldo: el borrado
 * lógico de una fila lo restituye solo, sin operación inversa.
 *
 * @property int $id
 * @property int $vinculacion_id
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property-read Vinculacion $vinculacion
 */
class Vacacion extends Model implements NovedadEditable
{
    /** @use HasFactory<VacacionFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'vacaciones';

    /** @var list<string> */
    protected $fillable = [
        'vinculacion_id',
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
     * Los días hábiles de estas vacaciones (documento 1, 6.4): domingos y
     * festivos no cuentan, y el sábado cuenta o no según el parámetro de la
     * empresa dueña de la vinculación.
     */
    public function dias(): int
    {
        return MotorDeCalculo::diasHabilesVacaciones(
            $this->fecha_inicio,
            $this->fecha_fin,
            $this->vinculacion->tenant,
        );
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
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin->toDateString(),
        ];
    }

    /**
     * Vacío: los dos campos de solo lectura del formulario —los días hábiles y
     * la franja de saldo— los calcula el servidor con lo que se está digitando,
     * no con lo que hay guardado.
     *
     * @return array<string, string>
     */
    public function contextoDeEdicion(): array
    {
        return [];
    }

    public function confirmacionDeBorrado(): string
    {
        return ConfirmacionDeBorrado::deVacacion($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }
}
