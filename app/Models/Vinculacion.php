<?php

namespace App\Models;

use App\Contratacion\EstadoVinculacion;
use App\Contratacion\EventoContractual;
use App\Contratacion\HistorialDeContrato;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoRiesgo;
use App\Multiempresa\PerteneceATenant;
use Database\Factories\VinculacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
 * @property TipoContrato $tipo_contrato
 * @property Carbon $fecha_ingreso
 * @property Carbon|null $fecha_fin
 * @property-read Persona $persona
 * @property-read Collection<int, PeriodoContractual> $periodos
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
     * El historial del contrato, del tramo inicial al vigente.
     *
     * Nunca está vacío: el periodo inicial lo abre `ObservadorDeVinculacion` al
     * crearse la vinculación. `tipo_contrato` y `fecha_fin` de esta tabla
     * guardan lo vigente hoy; el tramo a tramo está aquí.
     *
     * @return HasMany<PeriodoContractual, $this>
     */
    public function periodos(): HasMany
    {
        return $this->hasMany(PeriodoContractual::class)->orderBy('orden');
    }

    /**
     * Lo que se calcula sobre ese historial: prórrogas acumuladas, duración
     * total del vínculo, vencimiento vigente y tope legal.
     */
    public function historial(): HistorialDeContrato
    {
        return HistorialDeContrato::de($this);
    }

    /**
     * Agrega un tramo al historial y le asigna el número que le toca.
     *
     * Es el único camino por el que crece el historial, para que nadie tenga
     * que calcular el `orden` a mano ni pueda equivocarse con él. Por aquí
     * pasarán «Renovar» y «Cambiar a término indefinido» (F1-09), que además
     * tendrán que mover `tipo_contrato` y `fecha_fin` de la vinculación: el
     * historial y lo vigente se mueven a la vez o quedan desalineados.
     */
    public function registrarPeriodo(
        EventoContractual $evento,
        TipoContrato $tipoContrato,
        Carbon $fechaInicio,
        ?Carbon $fechaFin = null,
    ): PeriodoContractual {
        return $this->periodos()->create([
            'orden' => $this->historial()->proximoOrden(),
            'evento' => $evento,
            'tipo_contrato' => $tipoContrato,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]);
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
            'tipo_riesgo' => TipoRiesgo::class,
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
