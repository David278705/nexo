<?php

namespace App\Models;

use App\Contratacion\EstadoVinculacion;
use App\Contratacion\TipoContrato;
use App\Multiempresa\PerteneceATenant;
use Database\Factories\VinculacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una relación laboral concreta entre una persona y la empresa.
 *
 * Cada contratación es un registro independiente: si una persona se retira y
 * vuelve, se crea una vinculación nueva y **no se reactiva ni se edita la
 * anterior** (documento 1, 5.6). Esto es lo que permite que el indicador de
 * rotación cuente vínculos y no personas.
 *
 * Aquí no hay columna de saldo de vacaciones. Es la regla 2 del proyecto: el
 * saldo se calcula. `saldo_inicial_vacaciones` es otra cosa —el punto de
 * partida que trae la carga inicial— y solo se lee, nunca se suma ni se resta.
 *
 * @property int $id
 * @property-read Persona $persona
 */
class Vinculacion extends Model
{
    /** @use HasFactory<VinculacionFactory> */
    use HasFactory, PerteneceATenant, SoftDeletes;

    protected $table = 'vinculaciones';

    /** @var list<string> */
    protected $fillable = [
        'persona_id',
        'cargo',
        'tipo_contrato',
        'fecha_ingreso',
        'fecha_fin',
        'salario',
        'bonificacion',
        'eps_id',
        'fondo_pension_id',
        'arl_id',
        'tipo_riesgo',
        'caja_compensacion_id',
        'banco_id',
        'saldo_inicial_vacaciones',
        'fecha_corte_saldo',
    ];

    /**
     * El estado no está en `fillable` y es la mitad de la regla 5.3: no lo
     * digita el usuario. Nace activo por el valor por defecto de la columna y
     * solo lo cambia el módulo de terminaciones, llamando a `desactivar()`.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'estado' => EstadoVinculacion::Activo->value,
    ];

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * @return BelongsTo<Eps, $this>
     */
    public function eps(): BelongsTo
    {
        return $this->belongsTo(Eps::class);
    }

    /**
     * @return BelongsTo<FondoPension, $this>
     */
    public function fondoPension(): BelongsTo
    {
        return $this->belongsTo(FondoPension::class);
    }

    /**
     * @return BelongsTo<Arl, $this>
     */
    public function arl(): BelongsTo
    {
        return $this->belongsTo(Arl::class);
    }

    /**
     * @return BelongsTo<CajaCompensacion, $this>
     */
    public function cajaCompensacion(): BelongsTo
    {
        return $this->belongsTo(CajaCompensacion::class);
    }

    /**
     * @return BelongsTo<Banco, $this>
     */
    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function estaActiva(): bool
    {
        $estado = $this->getAttribute('estado');

        return $estado instanceof EstadoVinculacion && $estado->estaActivo();
    }

    /**
     * El único camino por el que una vinculación deja de estar activa.
     *
     * Lo llamará el módulo de terminaciones (F2-09) al registrar la salida. Se
     * expone como método y no como un campo editable para que no exista ninguna
     * pantalla donde alguien pueda «poner inactivo» a un empleado sin registrar
     * por qué se fue.
     */
    public function desactivar(): void
    {
        $this->setAttribute('estado', EstadoVinculacion::Inactivo);
    }

    /**
     * @param  Builder<$this>  $consulta
     * @return Builder<$this>
     */
    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('estado', EstadoVinculacion::Activo->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_contrato' => TipoContrato::class,
            'estado' => EstadoVinculacion::class,
            'fecha_ingreso' => 'date',
            'fecha_fin' => 'date',
            'fecha_corte_saldo' => 'date',
            'salario' => 'decimal:2',
            'bonificacion' => 'decimal:2',
            'saldo_inicial_vacaciones' => 'decimal:2',
        ];
    }
}
