<?php

namespace App\Models;

use App\Movimientos\TipoDeMovimiento;
use App\Multiempresa\PerteneceATenant;
use Database\Factories\MovimientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Un aumento salarial o una promoción de un empleado (documento 1, 8.1).
 *
 * Cuelga de la vinculación, no de la persona: es la regla 5 del proyecto, igual
 * que las tres novedades del capítulo 6.
 *
 * **Guarda el cargo y el salario anteriores** porque, al guardarse, los del
 * registro principal ya cambiaron: sin esta foto el histórico no podría enseñar
 * «el anterior frente al nuevo», que es lo que pide la sección 8.1. No es un
 * dato calculable que se cachea, es el hecho de lo que regía ese día.
 *
 * Aquí no se cuenta ningún día: un movimiento tiene una sola fecha, la de
 * efectividad, y por eso el motor de cálculo no aparece en este archivo.
 *
 * @property int $id
 * @property int $vinculacion_id
 * @property TipoDeMovimiento $tipo
 * @property string $cargo_anterior
 * @property string $cargo_nuevo
 * @property Carbon $fecha_efectividad
 * @property-read Vinculacion $vinculacion
 */
class Movimiento extends Model
{
    /** @use HasFactory<MovimientoFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'movimientos';

    /** @var list<string> */
    protected $fillable = [
        'vinculacion_id',
        'tipo',
        'cargo_anterior',
        'cargo_nuevo',
        'salario_anterior',
        'salario_nuevo',
        'fecha_efectividad',
    ];

    /**
     * @return BelongsTo<Vinculacion, $this>
     */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_efectividad' => 'date',
            'tipo' => TipoDeMovimiento::class,
            'salario_anterior' => 'decimal:2',
            'salario_nuevo' => 'decimal:2',
        ];
    }
}
