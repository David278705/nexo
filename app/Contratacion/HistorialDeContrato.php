<?php

namespace App\Contratacion;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Models\PeriodoContractual;
use App\Models\Vinculacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que se calcula sobre el historial de un contrato (documento 2, 3.3).
 *
 * Un solo sitio para las cuatro cuentas que pide el documento —prórrogas
 * acumuladas, duración total del vínculo a término fijo, fecha de vencimiento
 * vigente y el historial que ve el usuario— para que ninguna pantalla las
 * repita con su propia versión. Son el insumo de las validaciones bloqueantes
 * de 5.5 y de las alertas del capítulo 9.
 *
 * ## Lo que este objeto no hace
 *
 * - **No bloquea nada.** `superaElTopeLegal()` y `prorrogasCortasRestantes()`
 *   responden; quién impide guardar es la validación de F1-10, que además
 *   depende de las decisiones B.1 y B.3.
 * - **No escribe.** Agregar un tramo es `Vinculacion::registrarPeriodo()`.
 */
class HistorialDeContrato
{
    /**
     * @param  Collection<int, PeriodoContractual>  $periodos  Del primero al último.
     * @param  int  $prorrogasPreviasALaCarga  Las que traía el contrato antes de la carga inicial (REV-09), sin tramo que las respalde.
     */
    private function __construct(
        private readonly Collection $periodos,
        private readonly int $prorrogasPreviasALaCarga = 0,
        private readonly bool $deLaCargaInicial = false,
    ) {}

    /**
     * El historial de una vinculación, leído de la base de datos.
     */
    public static function de(Vinculacion $vinculacion): self
    {
        return new self(
            $vinculacion->periodos()->enOrden()->get()->values(),
            (int) $vinculacion->getAttribute('prorrogas_previas_a_la_carga'),
            $vinculacion->vieneDeLaCargaInicial(),
        );
    }

    /**
     * El historial a partir de tramos ya cargados.
     *
     * Es el camino de los listados, que traen las vinculaciones con sus
     * periodos de una vez y no pueden permitirse una consulta por fila.
     *
     * @param  iterable<int, PeriodoContractual>  $periodos
     */
    public static function con(iterable $periodos, int $prorrogasPreviasALaCarga = 0, bool $deLaCargaInicial = false): self
    {
        return new self(
            (new Collection($periodos))->sortBy('orden')->values(),
            $prorrogasPreviasALaCarga,
            $deLaCargaInicial,
        );
    }

    /**
     * El historial de una vinculación que ya trae sus tramos cargados: el camino
     * de los listados y de las alertas, sin una consulta por fila.
     */
    public static function deCargada(Vinculacion $vinculacion): self
    {
        return self::con(
            $vinculacion->periodos,
            (int) $vinculacion->getAttribute('prorrogas_previas_a_la_carga'),
            $vinculacion->vieneDeLaCargaInicial(),
        );
    }

    /**
     * Los tramos, del inicial al vigente. Es el historial que se muestra en
     * pantalla, con el periodo inicial incluido.
     *
     * @return Collection<int, PeriodoContractual>
     */
    public function periodos(): Collection
    {
        return $this->periodos;
    }

    /**
     * El contrato original: la fila con `orden = 1`.
     */
    public function inicial(): ?PeriodoContractual
    {
        return $this->periodos->first();
    }

    /**
     * El tramo en el que está hoy el contrato: el último del historial.
     */
    public function vigente(): ?PeriodoContractual
    {
        return $this->periodos->last();
    }

    /**
     * Cuántas prórrogas acumula el contrato.
     *
     * El cambio a indefinido no suma: no extiende un plazo, cambia la
     * modalidad.
     */
    public function prorrogasAcumuladas(): int
    {
        return $this->periodos->filter(fn (PeriodoContractual $tramo) => $tramo->esProrroga())->count();
    }

    public function prorrogasPactadas(): int
    {
        return $this->cuantasCon(EventoContractual::ProrrogaPactada);
    }

    public function prorrogasAutomaticas(): int
    {
        return $this->cuantasCon(EventoContractual::ProrrogaAutomatica);
    }

    /**
     * Si el contrato original se pactó por menos de un año.
     *
     * Es la primera de las tres condiciones de la regla de prórroga mínima
     * (documento 1, 5.5): el límite de prórrogas cortas solo aplica a los
     * contratos a término fijo inferiores a un año.
     */
    public function contratoInicialEsInferiorAUnAnio(): bool
    {
        return $this->inicial()?->esInferiorAUnAnio() ?? false;
    }

    /**
     * Cuántas prórrogas inferiores a un año acumula el contrato.
     *
     * **Una sola cuenta, sin mirar el tipo** (documento 6, C.1, corregida por
     * Angela en agosto de 2026). El contrato tiene una única cadena de
     * prórrogas y el cupo de tres es de la cadena entera: dos pactadas cortas y
     * una automática corta lo agotan igual que tres pactadas.
     *
     * Solo cuentan las cortas, porque es lo que la regla del artículo 46
     * limita: una prórroga de dos años no gasta ninguno de los tres cupos.
     *
     * **Suma también las que trajo la carga inicial** (REV-09,
     * `prorrogasPreviasALaCarga()`), que no tienen tramo propio en
     * `periodos_contractuales` pero sí gastaron cupo en la realidad. Un
     * contrato registrado por pantalla trae ese número en cero, así que aquí
     * no cambia nada para él.
     */
    public function prorrogasCortas(): int
    {
        return $this->periodos
            ->filter(fn (PeriodoContractual $tramo) => $tramo->esProrroga()
                && $tramo->esInferiorAUnAnio())
            ->count()
            + $this->prorrogasPreviasALaCarga;
    }

    /**
     * Cuántas prórrogas traía el contrato antes de la carga inicial, sin
     * tramo que las respalde en el historial (REV-09).
     *
     * Es lo que la pantalla usa para avisar «Y N prórrogas anteriores a la
     * carga inicial, sin detalle» debajo del historial, sin inventar esas
     * filas dentro de la tabla.
     */
    public function prorrogasPreviasALaCarga(): int
    {
        return $this->prorrogasPreviasALaCarga;
    }

    /**
     * La línea aparte que acompaña al historial cuando el contrato viene de
     * la carga inicial con prórrogas previas (REV-09), o vacía cuando no las
     * tiene.
     *
     * Es una nota, no una fila: `filasDelHistorial()` sigue mostrando
     * únicamente los tramos que de verdad se registraron.
     */
    public function notaDeCargaInicial(): string
    {
        if ($this->prorrogasPreviasALaCarga <= 0) {
            return '';
        }

        return 'Y '.$this->prorrogasPreviasALaCarga.' prórroga'
            .($this->prorrogasPreviasALaCarga === 1 ? '' : 's')
            .' anterior'.($this->prorrogasPreviasALaCarga === 1 ? '' : 'es')
            .' a la carga inicial, sin detalle.';
    }

    /**
     * Cuántas prórrogas cortas todavía caben.
     *
     * Nulo cuando la regla no aplica, que es cuando el contrato inicial no es
     * inferior a un año. Nunca negativo: si el contrato ya pasó el límite,
     * quedan cero.
     *
     * Este método responde, no impide. El bloqueo del guardado es F1-10 y el
     * aviso por correo del último cupo es la alerta `UltimaRenovacionCorta`.
     */
    public function prorrogasCortasRestantes(): ?int
    {
        if (! $this->contratoInicialEsInferiorAUnAnio()) {
            return null;
        }

        $limite = (int) config('reglas_laborales.prorrogas_cortas');

        return max(0, $limite - $this->prorrogasCortas());
    }

    /**
     * Si el tramo vigente es justamente el que agotó el cupo de prórrogas
     * cortas.
     *
     * Es la condición de disparo de la alerta «última renovación corta posible»
     * (documento 1, 9.4, tercera fila), que habla del vencimiento **del tramo
     * que agota el cupo**: el vigente tiene que ser una prórroga inferior a un
     * año y detrás de ella no puede quedar ninguna más. Una renovación
     * posterior de un año o más deja de cumplirlo, y con razón: ya no es ese
     * tramo, y la empresa ya hizo lo que el aviso le pedía.
     */
    public function elTramoVigenteAgotoElCupoCorto(): bool
    {
        $vigente = $this->vigente();

        // Desde PROD-33 es la misma regla que aplica la renovación: la siguiente
        // prórroga ya no puede ser corta, ni siquiera pactada.
        return $vigente instanceof PeriodoContractual
            && $vigente->esProrroga()
            && $vigente->esInferiorAUnAnio()
            && $this->limiteParaUnaProrrogaCorta(EventoContractual::ProrrogaPactada) !== null;
    }

    /**
     * Si el contrato entró por la carga inicial (`PROD-33`).
     */
    public function vieneDeLaCargaInicial(): bool
    {
        return $this->deLaCargaInicial;
    }

    /**
     * Todas las prórrogas del contrato: las registradas como tramo y las que
     * trajo la carga inicial sin detalle (REV-09).
     */
    public function prorrogasRegistradas(): int
    {
        return $this->prorrogasAcumuladas() + $this->prorrogasPreviasALaCarga;
    }

    /**
     * Si alguna prórroga fue automática. Las que trajo la carga sin detalle
     * cuentan como automáticas: no hay cómo saber si constaron por escrito.
     */
    public function tieneProrrogaAutomatica(): bool
    {
        return $this->prorrogasPreviasALaCarga > 0 || $this->prorrogasAutomaticas() > 0;
    }

    /**
     * Qué impide que la próxima prórroga sea inferior a un año (`PROD-33`,
     * decidido por Angela el 2026-11-11, sobre el artículo 46 del CST).
     *
     * Solo aplica a contratos con el periodo inicial inferior a un año. Nulo
     * cuando la prórroga corta se puede registrar; si no, la clave del caso:
     *
     * - `LIMITE_POR_AUTOMATICA` (A): ya lleva 3 y al menos una fue automática.
     *   El caso mixto no lo resuelve la ley y se toma la lectura más favorable
     *   al trabajador: el límite de 3 de las automáticas.
     * - `CUARTA_AUTOMATICA` (B): ya lleva 3, todas pactadas, y la nueva es
     *   automática: es por un año, y la fecha la pone el sistema.
     * - `LIMITE_SUPERADO` (C): ya lleva 4 o más.
     * - `LIMITE_POR_CARGA` (E): ya lleva 3 y el contrato vino de la carga
     *   inicial, cuyas prórrogas se tratan como automáticas.
     *
     * Con 3 prórrogas, todas pactadas, en un contrato creado en Nexo, una
     * cuarta pactada corta sí se puede: es la regla de la prórroga pactada.
     */
    public function limiteParaUnaProrrogaCorta(EventoContractual $tipo): ?string
    {
        return self::limite(
            $this->contratoInicialEsInferiorAUnAnio(),
            $this->prorrogasRegistradas(),
            $this->deLaCargaInicial,
            $this->tieneProrrogaAutomatica(),
            $tipo,
        );
    }

    /**
     * El mismo límite, para la renovación que vendría después de registrar una
     * prórroga más de ese tipo. Sirve para avisar antes de firmar que la
     * siguiente ya tendrá que ser de un año.
     */
    public function limiteDespuesDe(EventoContractual $tipo): ?string
    {
        return self::limite(
            $this->contratoInicialEsInferiorAUnAnio(),
            $this->prorrogasRegistradas() + 1,
            $this->deLaCargaInicial,
            $this->tieneProrrogaAutomatica() || $tipo === EventoContractual::ProrrogaAutomatica,
            EventoContractual::ProrrogaPactada,
        );
    }

    public const LIMITE_POR_AUTOMATICA = 'por_automatica';

    public const CUARTA_AUTOMATICA = 'cuarta_automatica';

    public const LIMITE_SUPERADO = 'superado';

    public const LIMITE_POR_CARGA = 'por_carga';

    private static function limite(
        bool $inicialCorto,
        int $prorrogas,
        bool $deLaCarga,
        bool $hayAutomatica,
        EventoContractual $tipo,
    ): ?string {
        $limite = (int) config('reglas_laborales.prorrogas_cortas');

        if (! $inicialCorto || $prorrogas < $limite) {
            return null;
        }

        if ($prorrogas > $limite) {
            return self::LIMITE_SUPERADO;
        }

        if ($deLaCarga) {
            return self::LIMITE_POR_CARGA;
        }

        if ($hayAutomatica) {
            return self::LIMITE_POR_AUTOMATICA;
        }

        return $tipo === EventoContractual::ProrrogaAutomatica ? self::CUARTA_AUTOMATICA : null;
    }

    /**
     * Si la próxima prórroga automática tiene que ser de un año por ley: en un
     * contrato inicial inferior a un año que ya lleva tres prórrogas o más.
     */
    public function laProximaAutomaticaEsDeUnAnio(): bool
    {
        return $this->contratoInicialEsInferiorAUnAnio()
            && $this->prorrogasRegistradas() >= (int) config('reglas_laborales.prorrogas_cortas');
    }

    /**
     * Hasta cuándo iría la próxima prórroga automática, **sin mirar el tope**:
     * por el término del tramo vigente, o por un año cuando la ley lo exige.
     */
    public function finDeLaProximaAutomaticaSinTope(): ?Carbon
    {
        $vencimiento = $this->fechaDeVencimiento();
        $fin = $this->finDeLaProrrogaAutomatica();

        if (! $vencimiento instanceof Carbon || ! $fin instanceof Carbon) {
            return null;
        }

        $unAnio = $vencimiento->copy()->addDay()->addYear()->subDay();

        return $this->laProximaAutomaticaEsDeUnAnio() && $fin->lessThan($unAnio) ? $unAnio : $fin;
    }

    /**
     * La fecha de fin que pone el sistema al registrar una prórroga automática.
     * Si choca con el tope de cuatro años, gana el tope (pendiente de confirmar
     * con el asesor laboral).
     */
    public function finDeLaProximaProrrogaAutomatica(): ?Carbon
    {
        $fin = $this->finDeLaProximaAutomaticaSinTope();
        $tope = $this->fechaDelTopeLegal();

        if ($fin instanceof Carbon && $tope instanceof Carbon && $fin->greaterThan($tope)) {
            return $tope->copy();
        }

        return $fin;
    }

    /**
     * La modalidad vigente, que es la del último tramo.
     */
    public function tipoDeContratoVigente(): ?TipoContrato
    {
        return $this->vigente()?->tipo_contrato;
    }

    /**
     * La fecha de vencimiento vigente: la del último tramo.
     *
     * Vacía en indefinido y en obra o labor, que no vencen por plazo.
     */
    public function fechaDeVencimiento(): ?Carbon
    {
        return $this->vigente()?->fecha_fin;
    }

    /**
     * La fecha límite para avisar que el contrato no se prorroga.
     *
     * Treinta días calendario antes del vencimiento vigente (artículo 46 del
     * CST, modificado por la Ley 2466 de 2025). Pasada esa fecha sin aviso, el
     * silencio prorroga el contrato solo.
     *
     * Vacía donde no hay plazo: un indefinido no vence, y una obra o labor
     * termina cuando termina la obra, que no es una fecha que se sepa antes.
     */
    public function fechaLimiteDePreaviso(): ?Carbon
    {
        return $this->fechaDeVencimiento()?->copy()->subDays(self::diasDePreaviso());
    }

    /**
     * Cuántos días antes del vencimiento hay que avisar.
     *
     * Se lee de la configuración y no de un 30 escrito a mano, y se lee **desde
     * aquí** porque son tres los que preguntan lo mismo: esta fecha límite, el
     * día en que disparan las alertas del preaviso (`ReglaDePreavisoDelFijo`) y
     * el estado de gestión con el que se filtra el listado de contratos
     * (`VigenciaDelContrato`). Tres lecturas sueltas de la misma clave son tres
     * sitios donde acordarse el día que deje de ser una constante global.
     */
    public static function diasDePreaviso(): int
    {
        return (int) config('reglas_laborales.preaviso_dias');
    }

    /**
     * Hasta cuándo llegaría el contrato si nadie avisa y el silencio lo
     * prorroga solo, **sin mirar el tope**.
     *
     * La prórroga automática opera «por un término igual al último pactado»
     * (artículo 46 del CST), así que arranca el día siguiente al vencimiento
     * vigente y dura lo mismo que el tramo vigente. Un tramo del 1 de enero al
     * 31 de diciembre de 2026 se prorroga hasta el 31 de diciembre de 2027.
     *
     * Devuelve el plazo entero a propósito, sin truncar: quien pregunta —la
     * alerta del efecto del silencio— necesita justamente comparar este plazo
     * contra `fechaDelTopeLegal()` para saber si se pasa, y truncarlo aquí le
     * borraría la pregunta.
     */
    public function finDeLaProrrogaAutomatica(): ?Carbon
    {
        $vigente = $this->vigente();
        $vencimiento = $this->fechaDeVencimiento();

        if ($vigente === null || ! $vencimiento instanceof Carbon) {
            return null;
        }

        // Un tramo de meses exactos se prorroga por los mismos meses (PROD-33): el
        // del 1 de enero al 31 de marzo va del 1 de abril al 30 de junio, no al
        // 29, aunque el primer trimestre tenga un día menos. Si no son meses
        // exactos, por los mismos días.
        $meses = MotorDeCalculo::mesesCumplidos($vigente->fecha_inicio, $vencimiento);

        if ($meses > 0 && MotorDeCalculo::cumpleMesesEl($vigente->fecha_inicio, $meses)->isSameDay($vencimiento)) {
            return Carbon::parse(
                MotorDeCalculo::cumpleMesesEl($vencimiento->copy()->addDay(), $meses)->toDateString()
            );
        }

        return $vencimiento->copy()->addDays(
            MotorDeCalculo::diasCorridos($vigente->fecha_inicio, $vencimiento)
        );
    }

    /**
     * Desde cuándo corren los cuatro años del tope (documento 6, C.2).
     *
     * Desde el inicio del primer tramo, salvo que el contrato sea anterior a la
     * entrada en vigencia de la Ley 2466 de 2025: los contratos que se venían
     * prorrogando bajo la norma anterior son situaciones consolidadas y la ley
     * no tiene efecto retroactivo, así que para ellos el conteo arranca el día
     * de la reforma y no el de la firma.
     */
    public function inicioDelConteoDelTope(): ?Carbon
    {
        $inicio = $this->inicial()?->fecha_inicio;

        if (! $inicio instanceof Carbon) {
            return null;
        }

        $reforma = $this->entradaEnVigenciaDeLaReforma();

        return $inicio->lessThan($reforma) ? $reforma : $inicio->copy();
    }

    /**
     * La duración total del vínculo, en días corridos y contando los dos
     * extremos.
     *
     * Corre desde el inicio del conteo del tope —no desde la firma, cuando el
     * contrato es anterior a la reforma— hasta la fecha que se pida, que por
     * defecto es el vencimiento vigente. Devuelve nulo cuando no hay contra qué
     * medir: un indefinido no tiene fecha de finalización.
     *
     * El conteo lo hace el motor de cálculo: aquí no se resta ni una fecha.
     */
    public function duracionTotalEnDias(?Carbon $hasta = null): ?int
    {
        $desde = $this->inicioDelConteoDelTope();
        $hasta ??= $this->fechaDeVencimiento();

        if (! $desde instanceof Carbon || ! $hasta instanceof Carbon) {
            return null;
        }

        return MotorDeCalculo::diasCorridos($desde, $hasta);
    }

    /**
     * La última fecha de finalización que todavía no supera el tope legal.
     *
     * Cuatro años desde el inicio del conteo (artículo 46 del CST reformado por
     * la Ley 2466 de 2025). Pasado el tope el contrato **es** indefinido por
     * ministerio de la ley: no es una opción del empleador.
     */
    public function fechaDelTopeLegal(): ?Carbon
    {
        $anios = (int) config('reglas_laborales.tope_termino_fijo_anios');

        return $this->inicioDelConteoDelTope()?->addYears($anios);
    }

    /**
     * Si terminar el contrato en esa fecha superaría los cuatro años.
     *
     * Se pregunta con la fecha de finalización propuesta antes de registrar una
     * prórroga. Terminar justo el día del tope no lo supera.
     */
    public function superaElTopeLegal(Carbon $fechaFin): bool
    {
        $tope = $this->fechaDelTopeLegal();

        return $tope instanceof Carbon && $fechaFin->greaterThan($tope);
    }

    /**
     * El número que le toca al próximo tramo. El inicial es el 1.
     */
    public function proximoOrden(): int
    {
        return (int) $this->periodos->max('orden') + 1;
    }

    private function cuantasCon(EventoContractual $evento): int
    {
        return $this->periodos
            ->filter(fn (PeriodoContractual $tramo) => $tramo->evento === $evento)
            ->count();
    }

    private function entradaEnVigenciaDeLaReforma(): Carbon
    {
        return Carbon::parse((string) config('reglas_laborales.vigencia_reforma_termino_fijo'))
            ->startOfDay();
    }
}
