<?php

namespace App\Models;

use App\Contratacion\CambioAIndefinido;
use App\Contratacion\EstadoVinculacion;
use App\Contratacion\EventoContractual;
use App\Contratacion\HistorialDeContrato;
use App\Contratacion\RenovacionDeContrato;
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
use Illuminate\Support\Facades\DB;
use LogicException;

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
     * Agrega un tramo al historial, le asigna el número que le toca y deja lo
     * vigente de la vinculación apuntando a ese tramo.
     *
     * Es el único camino por el que crece el historial, para que nadie tenga
     * que calcular el `orden` a mano ni pueda equivocarse con él. Por aquí
     * pasan «Renovar» (F1-09) y «Cambiar a término indefinido» (F1-11).
     *
     * **Las dos escrituras van juntas a propósito.** `tipo_contrato` y
     * `fecha_fin` de esta tabla son lo vigente hoy y el historial es el tramo a
     * tramo: si una prórroga agregara la fila y no moviera la fecha, el listado
     * de contratos seguiría avisando de un vencimiento que ya se renovó. Se
     * mueven a la vez o quedan desalineados, y hacerlo aquí es lo que evita que
     * cada acción tenga que acordarse.
     *
     * Solo se guarda si algo cambió: cuando llama `ObservadorDeVinculacion` al
     * abrir el periodo inicial, el tramo trae exactamente lo que la vinculación
     * ya tiene, y un `update` con los mismos valores solo ensuciaría la
     * auditoría del registro con una modificación que nadie hizo.
     */
    public function registrarPeriodo(
        EventoContractual $evento,
        TipoContrato $tipoContrato,
        Carbon $fechaInicio,
        ?Carbon $fechaFin = null,
    ): PeriodoContractual {
        return DB::transaction(function () use ($evento, $tipoContrato, $fechaInicio, $fechaFin): PeriodoContractual {
            $periodo = $this->periodos()->create([
                'orden' => $this->historial()->proximoOrden(),
                'evento' => $evento,
                'tipo_contrato' => $tipoContrato,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ]);

            $this->fill(['tipo_contrato' => $tipoContrato, 'fecha_fin' => $fechaFin]);

            if ($this->isDirty()) {
                $this->save();
            }

            // El historial acaba de crecer: quien tenga la relación cargada de
            // antes leería un tramo menos.
            $this->unsetRelation('periodos');

            return $periodo;
        });
    }

    /**
     * Registra una prórroga del contrato a término fijo (documento 1, 5.5).
     *
     * El tramo nuevo empieza el día siguiente al vencimiento vigente: una
     * prórroga continúa el mismo contrato, no abre una vinculación nueva ni deja
     * un día sin cubrir.
     *
     * **Es una acción distinta de corregir la fecha de finalización.** Corregir
     * arregla un error de digitación y no toca el historial; renovar registra un
     * hecho jurídico nuevo y por eso agrega una fila.
     *
     * Aquí no se valida: que el contrato admita prórroga y que la fecha sea
     * posterior al vencimiento vigente lo comprueba `RenovarContrato`, y el
     * tope legal es F1-10.
     */
    public function renovar(EventoContractual $prorroga, Carbon $fechaFin): PeriodoContractual
    {
        $inicio = RenovacionDeContrato::de($this->historial())->fechaDeInicio();

        if (! $inicio instanceof Carbon) {
            throw new LogicException('Un contrato sin fecha de vencimiento vigente no se renueva.');
        }

        return $this->registrarPeriodo($prorroga, TipoContrato::Fijo, $inicio, $fechaFin);
    }

    /**
     * Convierte el contrato a término indefinido (documento 1, 5.5).
     *
     * El tramo nuevo empieza el día en que se hace el cambio y **no lleva fecha
     * de fin**: un indefinido no vence, así que `registrarPeriodo()` deja también
     * `fecha_fin` de la vinculación en nulo y con ella desaparecen el preaviso y
     * las alertas de vencimiento.
     *
     * **La fecha de ingreso no se toca**, que es el criterio de la tarea: el
     * vínculo es el mismo y solo cambia su modalidad, así que la antigüedad y la
     * acumulación de vacaciones siguen corriendo desde la fecha original. Por eso
     * el tramo inicial también se deja intacto: es lo que se pactó, y reescribirlo
     * borraría la trazabilidad que el cambio pretende dejar.
     *
     * Aquí no se valida: que el contrato sea a término fijo lo comprueba
     * `CambiarATerminoIndefinido`.
     */
    public function cambiarATerminoIndefinido(): PeriodoContractual
    {
        return $this->registrarPeriodo(
            EventoContractual::CambioAIndefinido,
            TipoContrato::Indefinido,
            CambioAIndefinido::de($this)->fechaDelCambio(),
        );
    }

    /**
     * Las incapacidades registradas sobre esta vinculación, la más reciente
     * primero.
     *
     * @return HasMany<Incapacidad, $this>
     */
    public function incapacidades(): HasMany
    {
        return $this->hasMany(Incapacidad::class)->orderByDesc('fecha_inicio');
    }

    /**
     * Las vacaciones registradas sobre esta vinculación, la más reciente
     * primero.
     *
     * @return HasMany<Vacacion, $this>
     */
    public function vacaciones(): HasMany
    {
        return $this->hasMany(Vacacion::class)->orderByDesc('fecha_inicio');
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
     * Cuándo terminó esta vinculación, o `null` si sigue viva.
     *
     * **Es provisional, y está en un solo sitio a propósito.** La fecha de
     * salida es un campo del módulo de terminaciones (documento 1, 7.1), que es
     * la tarea F2-09: hoy una vinculación inactiva no guarda cuándo terminó. Lo
     * único disponible es `fecha_fin`, que en un término fijo expirado es
     * exactamente esa fecha, y en el indefinido y en la obra o labor es nula.
     *
     * Cuando F2-09 exista, este método devuelve la fecha de salida registrada y
     * nadie más cambia. Lo único que hoy se pierde por devolver nulo es el
     * énfasis de los 30 días en la advertencia de reingreso: el aviso se muestra
     * igual, porque no depende de esta fecha sino de que haya una vinculación
     * anterior.
     */
    public function fechaDeSalida(): ?Carbon
    {
        if ($this->estaActiva()) {
            return null;
        }

        $fecha = $this->getAttribute('fecha_fin');

        return $fecha instanceof Carbon ? $fecha : null;
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
