<?php

namespace App\Models;

use App\Multiempresa\PerteneceATenant;
use Database\Factories\RegistroDeCargaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * La constancia de una carga inicial ya ejecutada (documento 2, 6.1, paso 6).
 *
 * Guarda lo que el capítulo 5 del documento 4 le pide al historial del panel
 * —qué archivo, cuántas filas, quién y cuándo— y **nada del contenido**: el
 * Excel no se conserva (documento 1, 13.1), así que aquí solo queda su nombre.
 *
 * Es además la respuesta a la pregunta de la dependencia entre plantillas: si
 * existe una fila de la plantilla de empleados en esta empresa, los históricos
 * ya se pueden cargar. Se pregunta por el registro y no por la planta a
 * propósito: una empresa puede tener empleados registrados a mano por pantalla
 * sin haber pasado nunca por la carga inicial, y en ese caso la comprobación
 * contra la planta diría que sí a algo que nunca se hizo.
 *
 * @property int $id
 * @property string $plantilla
 * @property string $archivo
 * @property int $filas
 * @property int|null $usuario_id
 * @property Carbon|null $fecha_corte_saldo
 * @property Carbon $created_at
 * @property-read User|null $usuario
 */
class RegistroDeCarga extends Model
{
    /** @use HasFactory<RegistroDeCargaFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'cargas_iniciales';

    /** @var list<string> */
    protected $fillable = [
        'plantilla',
        'archivo',
        'filas',
        'usuario_id',
        'fecha_corte_saldo',
    ];

    /**
     * Quién ejecutó la carga. Nulo cuando la corrió el comando de consola, que
     * no tiene sesión detrás.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * El nombre con el que se enseña «quién» en el historial.
     *
     * Un registro sin usuario no es un hueco que rellenar con un guion: es una
     * carga ejecutada por consola, y decirlo es más útil que dejarlo en blanco.
     */
    public function quien(): string
    {
        return $this->usuario->name ?? 'Consola';
    }

    /**
     * @param  Builder<self>  $consulta
     * @return Builder<self>
     */
    public function scopeDeLaPlantilla(Builder $consulta, string $clave): Builder
    {
        return $consulta->where('plantilla', $clave);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_corte_saldo' => 'date',
            'filas' => 'integer',
        ];
    }
}
