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
     */
    private function __construct(private readonly Collection $periodos) {}

    /**
     * El historial de una vinculación, leído de la base de datos.
     */
    public static function de(Vinculacion $vinculacion): self
    {
        return new self(
            $vinculacion->periodos()->enOrden()->get()->values()
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
    public static function con(iterable $periodos): self
    {
        return new self(
            (new Collection($periodos))->sortBy('orden')->values()
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
     * Cuántas prórrogas inferiores a un año acumula el contrato de ese tipo.
     *
     * Se cuentan por tipo y solo las cortas, porque es lo que la regla del
     * artículo 46 limita: una prórroga de dos años no gasta ninguno de los
     * cuatro cupos de prórroga corta.
     */
    public function prorrogasCortas(EventoContractual $evento): int
    {
        return $this->periodos
            ->filter(fn (PeriodoContractual $tramo) => $tramo->evento === $evento
                && $tramo->esInferiorAUnAnio())
            ->count();
    }

    /**
     * Cuántas prórrogas cortas de ese tipo todavía caben.
     *
     * Nulo cuando la regla no aplica: el contrato inicial no es inferior a un
     * año, o el evento no es una prórroga. Nunca negativo: si el contrato ya
     * pasó el límite, quedan cero.
     *
     * Este método responde, no impide. El bloqueo del guardado es F1-10.
     */
    public function prorrogasCortasRestantes(EventoContractual $evento): ?int
    {
        $limite = $evento->limiteDeProrrogasCortas();

        if ($limite === null || ! $this->contratoInicialEsInferiorAUnAnio()) {
            return null;
        }

        return max(0, $limite - $this->prorrogasCortas($evento));
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
        $dias = (int) config('reglas_laborales.preaviso_dias');

        return $this->fechaDeVencimiento()?->copy()->subDays($dias);
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
