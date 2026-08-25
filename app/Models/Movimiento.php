<?php

namespace App\Models;

use App\Movimientos\TipoDeMovimiento;
use App\Multiempresa\PerteneceATenant;
use App\Novedades\ConfirmacionDeBorrado;
use App\Novedades\NovedadEditable;
use App\Presentacion\Formato;
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
 * **Se edita y se elimina, pero solo el más reciente del empleado** (REV-03):
 * la foto de lo anterior encadena cada movimiento con el que le sigue, así que
 * tocar uno de en medio dejaría al de encima diciendo que venía de un salario
 * que ya no consta en ninguna parte. Lo responde `esElMasReciente()` y lo
 * ejecutan `Vinculacion::corregirMovimiento()` y `deshacerMovimiento()`.
 *
 * @property int $id
 * @property int $vinculacion_id
 * @property TipoDeMovimiento $tipo
 * @property string $cargo_anterior
 * @property string $cargo_nuevo
 * @property Carbon $fecha_efectividad
 * @property-read Vinculacion $vinculacion
 */
class Movimiento extends Model implements NovedadEditable
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
     * ¿Es este el último movimiento que se le registró al empleado?
     *
     * Se pregunta por el identificador y no por la fecha de efectividad a
     * propósito. Lo que encadena el histórico es **el orden en que se
     * registraron**: `registrarMovimiento()` copia como anteriores el cargo y
     * el salario que el empleado tenía en ese instante, así que el último en
     * entrar es el que puso los valores vigentes, aunque su fecha de
     * efectividad sea anterior a la de otro registrado antes.
     */
    public function esElMasReciente(): bool
    {
        return (int) $this->vinculacion->movimientos->max('id') === (int) $this->getKey();
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
            'cargo_nuevo' => $this->cargo_nuevo,
            'salario_nuevo' => (string) $this->getAttribute('salario_nuevo'),
            'fecha_efectividad' => $this->fecha_efectividad->toDateString(),
        ];
    }

    /**
     * El punto de partida del movimiento, que es lo que el formulario enseña
     * como «Cargo actual» y «Salario actual».
     *
     * Al corregir no se le pueden pedir al empleado: él ya lleva encima los
     * valores nuevos de este mismo movimiento, así que enseñarían el resultado
     * en la casilla del punto de partida.
     *
     * @return array<string, string>
     */
    public function contextoDeEdicion(): array
    {
        return [
            'cargo' => $this->cargo_anterior,
            'salario' => Formato::dinero($this->getAttribute('salario_anterior')),
        ];
    }

    public function confirmacionDeBorrado(): string
    {
        return ConfirmacionDeBorrado::deMovimiento($this);
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
