<?php

namespace App\Models;

use App\Multiempresa\PerteneceATenant;
use App\Novedades\ConfirmacionDeBorrado;
use App\Novedades\NovedadEditable;
use App\Terminaciones\CategoriaDeRotacion;
use App\Terminaciones\TipoDeSalida;
use Database\Factories\TerminacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * La salida de un empleado (documento 1, capítulo 7).
 *
 * Cuelga de la vinculación, no de la persona: es la regla 5 del proyecto, y
 * aquí es más literal que en ninguna otra tabla —lo que termina es el vínculo,
 * y la misma persona puede tener otro después—.
 *
 * **La categoría de rotación no se guarda, se deduce**: la responde
 * `TipoDeSalida::categoriaDeRotacion()` cada vez que se pregunta.
 *
 * **Se edita y se elimina** (REV-03), y eliminarla devuelve al empleado a
 * activo: `Vinculacion::deshacerTerminacion()`. No lleva la guardia del «solo
 * el más reciente» que sí lleva un movimiento, y no por descuido: una
 * vinculación tiene **una sola** terminación viva —es una relación `HasOne` y
 * registrarla exige empleado activo—, así que la que hay siempre es la última.
 *
 * @property int $id
 * @property int $vinculacion_id
 * @property TipoDeSalida $tipo_salida
 * @property string $motivo
 * @property Carbon $fecha_salida
 * @property string|null $observaciones
 * @property-read Vinculacion $vinculacion
 */
class Terminacion extends Model implements NovedadEditable
{
    /** @use HasFactory<TerminacionFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'terminaciones';

    /** @var list<string> */
    protected $fillable = [
        'vinculacion_id',
        'tipo_salida',
        'motivo',
        'fecha_salida',
        'observaciones',
    ];

    /**
     * @return BelongsTo<Vinculacion, $this>
     */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    /**
     * El grupo del indicador de rotación al que pertenece esta salida
     * (documento 1, 7.4).
     */
    public function categoriaDeRotacion(): CategoriaDeRotacion
    {
        return $this->tipo_salida->categoriaDeRotacion();
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
            'tipo_salida' => $this->tipo_salida->value,
            'motivo' => $this->motivo,
            'fecha_salida' => $this->fecha_salida->toDateString(),
            'observaciones' => $this->observaciones ?? '',
        ];
    }

    /**
     * Vacío: el formulario de la salida no tiene ningún campo de solo lectura.
     * Lo único que se le enseña al usuario y no digita es el aviso de la
     * consecuencia, y ese solo tiene sentido antes de registrarla.
     *
     * @return array<string, string>
     */
    public function contextoDeEdicion(): array
    {
        return [];
    }

    public function confirmacionDeBorrado(): string
    {
        return ConfirmacionDeBorrado::deTerminacion($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_salida' => 'date',
            'tipo_salida' => TipoDeSalida::class,
        ];
    }
}
