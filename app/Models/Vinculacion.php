<?php

namespace App\Models;

use App\Contratacion\CambioAIndefinido;
use App\Contratacion\EstadoVinculacion;
use App\Contratacion\EventoContractual;
use App\Contratacion\HistorialDeContrato;
use App\Contratacion\RenovacionDeContrato;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoRiesgo;
use App\Movimientos\TipoDeMovimiento;
use App\Multiempresa\PerteneceATenant;
use App\Terminaciones\TipoDeSalida;
use Database\Factories\VinculacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property Carbon|null $fecha_fin_estimada
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
        'fecha_fin_estimada',
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
        'prorrogas_previas_a_la_carga',
    ];

    /**
     * El estado no está en `fillable` y es la mitad de la regla 5.3: no lo
     * digita el usuario. Nace activo por el valor por defecto de la columna y
     * solo lo cambia el módulo de terminaciones, llamando a `desactivar()`.
     *
     * `prorrogas_previas_a_la_carga` repite aquí el valor por defecto de la
     * columna (REV-09) para que una vinculación recién creada, sin volver a
     * consultarse, ya responda 0 y no `null`: es lo que necesita
     * `HistorialDeContrato` justo después de registrar un empleado por
     * pantalla, antes de que nadie la recargue.
     *
     * @var array<string, string|int>
     */
    protected $attributes = [
        'estado' => EstadoVinculacion::Activo->value,
        'prorrogas_previas_a_la_carga' => 0,
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
     * El tramo nuevo empieza el día en que las partes acordaron el cambio
     * (HD-11) —`$fecha`, digitada en el modal y editable hacia atrás— y **no
     * lleva fecha de fin**: un indefinido no vence, así que `registrarPeriodo()`
     * deja también `fecha_fin` de la vinculación en nulo y con ella desaparecen
     * el preaviso y las alertas de vencimiento.
     *
     * **La fecha de ingreso no se toca**, que es el criterio de la tarea: el
     * vínculo es el mismo y solo cambia su modalidad, así que la antigüedad y la
     * acumulación de vacaciones siguen corriendo desde la fecha original. Por eso
     * el tramo inicial también se deja intacto: es lo que se pactó, y reescribirlo
     * borraría la trazabilidad que el cambio pretende dejar.
     *
     * Aquí no se valida: que el contrato sea a término fijo y que `$fecha` esté
     * dentro del rango permitido lo comprueba `CambiarATerminoIndefinido`.
     * Sin ella, `CambioAIndefinido::fechaDelCambio()` propone hoy —o el inicio
     * del periodo vigente, si el contrato todavía no ha empezado—.
     */
    public function cambiarATerminoIndefinido(?Carbon $fecha = null): PeriodoContractual
    {
        return $this->registrarPeriodo(
            EventoContractual::CambioAIndefinido,
            TipoContrato::Indefinido,
            CambioAIndefinido::de($this)->fechaDelCambio($fecha),
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
     * Las licencias, ausencias y suspensiones registradas sobre esta
     * vinculación, la más reciente primero.
     *
     * @return HasMany<Licencia, $this>
     */
    public function licencias(): HasMany
    {
        return $this->hasMany(Licencia::class)->orderByDesc('fecha_inicio');
    }

    /**
     * Los aumentos y las promociones de esta vinculación, el más reciente
     * primero.
     *
     * @return HasMany<Movimiento, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class)->orderByDesc('fecha_efectividad');
    }

    /**
     * Registra un aumento salarial o una promoción (documento 1, 8.1).
     *
     * **Es el único sitio donde se escribe en dos tablas a la vez**, y por eso
     * está aquí y no en el controlador. La sección 8.1 pide dos cosas de un
     * mismo hecho: que el movimiento quede en el histórico y que «el salario y
     * el cargo del empleado se actualicen en su registro principal». Si se
     * hicieran por separado, un fallo en medio dejaría un histórico que no
     * coincide con lo vigente, que es exactamente el desalineamiento que
     * `registrarPeriodo()` evita con el historial del contrato.
     *
     * El cargo con el que queda el empleado lo decide `TipoDeMovimiento`: en un
     * aumento salarial se repite el actual, en una promoción es el pedido.
     *
     * Aquí no se valida: que el empleado esté activo y que el salario tenga
     * formato de dinero lo comprueba `RegistrarMovimiento`.
     */
    public function registrarMovimiento(
        TipoDeMovimiento $tipo,
        ?string $cargoPedido,
        string $salarioNuevo,
        Carbon $fechaEfectividad,
    ): Movimiento {
        return DB::transaction(function () use ($tipo, $cargoPedido, $salarioNuevo, $fechaEfectividad): Movimiento {
            $cargoAnterior = (string) $this->getAttribute('cargo');
            $salarioAnterior = (string) $this->getAttribute('salario');
            $cargoNuevo = $tipo->cargoResultante($cargoAnterior, $cargoPedido);

            $movimiento = $this->movimientos()->create([
                'tipo' => $tipo,
                'cargo_anterior' => $cargoAnterior,
                'cargo_nuevo' => $cargoNuevo,
                'salario_anterior' => $salarioAnterior,
                'salario_nuevo' => $salarioNuevo,
                'fecha_efectividad' => $fechaEfectividad,
            ]);

            $this->fill(['cargo' => $cargoNuevo, 'salario' => $salarioNuevo]);

            if ($this->isDirty()) {
                $this->save();
            }

            // El histórico acaba de crecer: quien tenga la relación cargada de
            // antes leería un movimiento menos.
            $this->unsetRelation('movimientos');

            return $movimiento;
        });
    }

    /**
     * Corrige un movimiento ya registrado (REV-03).
     *
     * Escribe en los dos sitios de siempre, y por el mismo motivo que
     * `registrarMovimiento()`: si el movimiento dice ahora que el salario quedó
     * en otra cifra, el registro principal del empleado tiene que decir lo
     * mismo. **Solo se corrige el más reciente** —lo comprueba el controlador
     * con `Movimiento::esElMasReciente()`—, así que los valores nuevos de esta
     * fila son siempre los vigentes.
     *
     * Lo que **no** se toca es la foto de lo anterior: el cargo y el salario
     * que regían antes del movimiento son un hecho de aquel día, no un dato que
     * la corrección pueda cambiar. De ahí sale también el cargo con el que se
     * queda un aumento salarial, que repite el de partida.
     */
    public function corregirMovimiento(
        Movimiento $movimiento,
        TipoDeMovimiento $tipo,
        ?string $cargoPedido,
        string $salarioNuevo,
        Carbon $fechaEfectividad,
    ): Movimiento {
        return DB::transaction(function () use ($movimiento, $tipo, $cargoPedido, $salarioNuevo, $fechaEfectividad): Movimiento {
            $cargoNuevo = $tipo->cargoResultante((string) $movimiento->cargo_anterior, $cargoPedido);

            $movimiento->update([
                'tipo' => $tipo,
                'cargo_nuevo' => $cargoNuevo,
                'salario_nuevo' => $salarioNuevo,
                'fecha_efectividad' => $fechaEfectividad,
            ]);

            $this->fill(['cargo' => $cargoNuevo, 'salario' => $salarioNuevo]);

            if ($this->isDirty()) {
                $this->save();
            }

            $this->unsetRelation('movimientos');

            return $movimiento;
        });
    }

    /**
     * Deshace un movimiento (REV-03): «eliminar es volver al pasado».
     *
     * Devuelve al empleado el cargo y el salario que tenía justo antes de que
     * el movimiento se registrara —los que la propia fila guardó como
     * anteriores— y la elimina. Después, el empleado queda indistinguible de
     * como estaba, sin rastro de que el movimiento existió.
     *
     * Las dos escrituras van juntas por lo mismo que al registrar: un fallo en
     * medio dejaría al empleado con el salario devuelto y el movimiento todavía
     * en el histórico, o al revés.
     *
     * **Solo se deshace el más reciente**, y esa guardia es de quien llama.
     */
    public function deshacerMovimiento(Movimiento $movimiento): void
    {
        DB::transaction(function () use ($movimiento): void {
            $this->fill([
                'cargo' => (string) $movimiento->cargo_anterior,
                'salario' => $movimiento->getAttribute('salario_anterior'),
            ]);

            if ($this->isDirty()) {
                $this->save();
            }

            $movimiento->delete();

            $this->unsetRelation('movimientos');
        });
    }

    /**
     * La terminación que cerró esta vinculación, si ya se registró.
     *
     * Es una sola: lo que termina es el vínculo, y un vínculo termina una vez.
     * El flujo no deja registrar dos porque `RegistrarTerminacion` exige que el
     * empleado esté activo, y el primer registro lo desactiva.
     *
     * @return HasOne<Terminacion, $this>
     */
    public function terminacion(): HasOne
    {
        return $this->hasOne(Terminacion::class);
    }

    /**
     * Registra la salida del empleado (documento 1, capítulo 7).
     *
     * **Escribe en dos sitios**, igual que `registrarMovimiento()`: crea la
     * terminación y pasa la vinculación a inactiva. La sección 7.1 las pide
     * como un solo hecho —«al registrar la terminación, el empleado pasa a
     * estado inactivo»—, así que van en una transacción: un fallo en medio
     * dejaría una salida registrada sobre alguien que el sistema sigue
     * considerando activo, y le seguiría mandando alertas.
     *
     * El motivo con el que queda lo decide `TipoDeSalida`: en la expiración del
     * plazo y en la obra terminada se autocompleta y se ignora el que llegue.
     *
     * Aquí no se valida: que el empleado esté activo y que el motivo sea de ese
     * tipo lo comprueba `RegistrarTerminacion`.
     */
    public function registrarTerminacion(
        TipoDeSalida $tipo,
        ?string $motivoPedido,
        Carbon $fechaSalida,
        ?string $observaciones = null,
    ): Terminacion {
        return DB::transaction(function () use ($tipo, $motivoPedido, $fechaSalida, $observaciones): Terminacion {
            $terminacion = $this->terminacion()->create([
                'tipo_salida' => $tipo,
                'motivo' => $tipo->motivoResultante($motivoPedido),
                'fecha_salida' => $fechaSalida,
                'observaciones' => $observaciones,
            ]);

            $this->desactivar();
            $this->save();

            // La relación acaba de existir: quien la tuviera cargada de antes
            // seguiría creyendo que esta vinculación no tiene salida.
            $this->setRelation('terminacion', $terminacion);

            return $terminacion;
        });
    }

    /**
     * Corrige una salida ya registrada (REV-03).
     *
     * Es la única de las cuatro que **no** escribe en dos sitios: el empleado
     * ya está inactivo y sigue estándolo, se corrija lo que se corrija. Lo que
     * cambia de rebote no se guarda en ninguna columna —la categoría de
     * rotación la deduce el tipo, y la fecha de salida la lee
     * `fechaDeSalida()`—, así que corregir la fila es todo lo que hay que
     * hacer.
     *
     * El motivo con el que queda lo decide `TipoDeSalida`, igual que al
     * registrar: en la expiración del plazo y en la obra terminada se
     * autocompleta y se ignora el que llegue.
     */
    public function corregirTerminacion(
        Terminacion $terminacion,
        TipoDeSalida $tipo,
        ?string $motivoPedido,
        Carbon $fechaSalida,
        ?string $observaciones = null,
    ): Terminacion {
        $terminacion->update([
            'tipo_salida' => $tipo,
            'motivo' => $tipo->motivoResultante($motivoPedido),
            'fecha_salida' => $fechaSalida,
            'observaciones' => $observaciones,
        ]);

        $this->setRelation('terminacion', $terminacion);

        return $terminacion;
    }

    /**
     * Deshace una terminación (REV-03): «eliminar es volver al pasado».
     *
     * Devuelve al empleado a activo y elimina la salida. Con él vuelven todas
     * sus alertas, sin que haya que reactivar ninguna: el motor recorre
     * empleados activos, así que dejar de estar inactivo es volver a entrar en
     * los avisos —igual que registrar la salida fue lo único que hizo falta
     * para dejar de recibirlos—.
     *
     * **`fecha_fin` no se toca**, y es deliberado: `registrarTerminacion()`
     * tampoco la escribe. En un contrato a término fijo esa columna es la fecha
     * hasta la que se pactó el contrato, un dato anterior a la salida y ajeno a
     * ella; borrarla al deshacer no sería volver al pasado, sería perder algo
     * que el pasado tenía.
     */
    public function deshacerTerminacion(Terminacion $terminacion): void
    {
        DB::transaction(function () use ($terminacion): void {
            $this->reactivar();
            $this->save();

            $terminacion->delete();

            // La relación deja de existir: quien la tuviera cargada seguiría
            // creyendo que esta vinculación tiene salida, y `fechaDeSalida()`
            // devolvería una fecha de un registro eliminado.
            $this->unsetRelation('terminacion');
        });
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
     * Lo llama `registrarTerminacion()` y nadie más. Se expone como método y no
     * como un campo editable para que no exista ninguna pantalla donde alguien
     * pueda «poner inactivo» a un empleado sin registrar por qué se fue.
     */
    public function desactivar(): void
    {
        $this->setAttribute('estado', EstadoVinculacion::Inactivo);
    }

    /**
     * El único camino de vuelta, y lo recorre solo `deshacerTerminacion()`.
     *
     * Se expone igual que `desactivar()` y por la misma razón invertida: nadie
     * puede «poner activo» a un empleado desde una pantalla, solo eliminar la
     * salida que lo desactivó, que es una acción con su confirmación y su línea
     * en la auditoría.
     */
    public function reactivar(): void
    {
        $this->setAttribute('estado', EstadoVinculacion::Activo);
    }

    /**
     * Cuándo terminó esta vinculación, o `null` si sigue viva.
     *
     * **Manda la fecha de salida registrada** en la terminación (documento 1,
     * 7.1), que es lo que F2-09 vino a arreglar: hasta entonces solo existía
     * `fecha_fin`, que en el indefinido y en la obra o labor es nula y en el
     * término fijo dice cuándo *iba* a terminar el contrato, no cuándo se fue
     * la persona. De aquí sale el énfasis de los 30 días de la advertencia de
     * reingreso, y también el mes en que el saldo de vacaciones deja de
     * acumular.
     *
     * `fecha_fin` queda como respaldo de las vinculaciones inactivas que no
     * tienen terminación registrada. No es un resto del código anterior: la
     * carga inicial (F5-02) trae personal histórico que se fue antes de que la
     * empresa usara el sistema, y para ese la fecha de finalización pactada es
     * la única que hay.
     */
    public function fechaDeSalida(): ?Carbon
    {
        if ($this->estaActiva()) {
            return null;
        }

        $registrada = $this->terminacion?->fecha_salida;

        if ($registrada instanceof Carbon) {
            return $registrada;
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
            'fecha_fin_estimada' => 'date',
            'fecha_corte_saldo' => 'date',
            'salario' => 'decimal:2',
            'bonificacion' => 'decimal:2',
            'saldo_inicial_vacaciones' => 'decimal:2',
        ];
    }
}
