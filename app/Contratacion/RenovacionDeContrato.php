<?php

namespace App\Contratacion;

use Illuminate\Support\Carbon;

/**
 * La acción «Renovar contrato» (documento 1, 5.5, y documento 3, sección 5.4).
 *
 * Responde las tres preguntas que el modal enseña mientras se digita la fecha:
 * **cuánto dura la prórroga**, **en cuánto queda el vínculo acumulado** y
 * **cuántas prórrogas cortas van a quedar**. Las tres se calculan aquí, en el
 * servidor, y viajan a la pantalla ya escritas: el modal no cuenta un solo día
 * por su cuenta. Cuando el usuario cambia la fecha o el tipo de prórroga, la
 * pantalla vuelve a pedir esta misma prop y no recalcula nada.
 *
 * ## Lo que aquí no se hace
 *
 * - **No se bloquea nada.** Si la fecha supera el tope de cuatro años o gasta el
 *   último cupo de prórroga corta, se dice con todas las letras, pero el
 *   guardado sigue permitido: convertir el aviso en bloqueo es F1-10, y depende
 *   de las decisiones B.1 y B.3 del documento 6.
 * - **No se cuenta ni se cuadra el historial.** Las prórrogas acumuladas, la
 *   duración del vínculo y el tope legal son `HistorialDeContrato`; escribir el
 *   tramo es `Vinculacion::renovar()`.
 */
class RenovacionDeContrato
{
    private function __construct(private readonly HistorialDeContrato $historial) {}

    public static function de(HistorialDeContrato $historial): self
    {
        return new self($historial);
    }

    /**
     * Si esta pantalla ofrece la acción.
     *
     * Solo el término fijo se renueva (documento 1, 5.5). Un indefinido no vence
     * y una obra o labor termina con la obra, que no es una fecha que se prorrogue.
     */
    public function disponible(): bool
    {
        return $this->historial->tipoDeContratoVigente()?->admiteRenovacion() === true
            && $this->historial->fechaDeVencimiento() instanceof Carbon;
    }

    /**
     * Cuándo empieza el tramo nuevo: el día siguiente al vencimiento vigente.
     *
     * Una prórroga continúa el mismo contrato. Empezarla el mismo día del
     * vencimiento contaría ese día dos veces, y empezarla más tarde dejaría un
     * hueco sin cubrir en un vínculo que nunca se interrumpió.
     */
    public function fechaDeInicio(): ?Carbon
    {
        return $this->historial->fechaDeVencimiento()?->copy()->addDay();
    }

    /**
     * Todo lo que necesita el modal, con la proyección incluida si ya hay una
     * fecha y un tipo propuestos.
     *
     * @param  array{tipo?: string|null, fecha_fin?: string|null}  $propuesta  Lo digitado en el modal.
     * @return array<string, mixed>
     */
    public function paraLaPantalla(array $propuesta = []): array
    {
        $inicio = $this->fechaDeInicio();

        return [
            'disponible' => $this->disponible(),
            'inicio' => self::fecha($inicio),
            'minimo' => $inicio instanceof Carbon ? $inicio->toDateString() : '',
            'resumen' => $this->resumen(),
            'tipos' => EventoContractual::prorrogasParaElegir(),
            'propuesta' => [
                'tipo' => $propuesta['tipo'] ?? '',
                'fecha_fin' => $propuesta['fecha_fin'] ?? '',
            ],
            'proyeccion' => $this->proyeccionDe($propuesta),
        ];
    }

    /**
     * El bloque de solo lectura de arriba del modal: en qué punto está el
     * contrato antes de tocar nada.
     *
     * @return list<array{etiqueta: string, valor: string}>
     */
    public function resumen(): array
    {
        $vigente = $this->historial->vigente();
        $duracion = $this->historial->duracionTotalEnDias();

        return [
            [
                'etiqueta' => 'Periodo vigente',
                'valor' => self::fecha($vigente?->fecha_inicio).' — '.self::fecha($vigente?->fecha_fin),
            ],
            [
                'etiqueta' => 'Prórrogas acumuladas',
                'valor' => (string) $this->historial->prorrogasAcumuladas(),
            ],
            [
                'etiqueta' => 'Duración total del vínculo',
                'valor' => $duracion === null ? '—' : self::dias($duracion),
            ],
        ];
    }

    /**
     * Qué pasaría si se guardara esa prórroga. Nulo mientras falte un dato.
     *
     * @param  array{tipo?: string|null, fecha_fin?: string|null}  $propuesta
     * @return array{tono: string, lineas: list<string>}|null
     */
    public function proyeccionDe(array $propuesta): ?array
    {
        $tipo = $propuesta['tipo'] ?? null;
        $fechaFin = $propuesta['fecha_fin'] ?? null;
        $inicio = $this->fechaDeInicio();

        if (! is_string($tipo) || ! is_string($fechaFin) || $tipo === '' || $fechaFin === '') {
            return null;
        }

        $prorroga = EventoContractual::tryFrom($tipo);

        if (! $prorroga instanceof EventoContractual || ! $prorroga->esProrroga()) {
            return null;
        }

        // La fecha llega de un campo de tipo `date`, así que siempre en ISO. Se
        // comprueba el formato en vez de atrapar una excepción: una cadena
        // cualquiera escrita en la dirección no es una proyección, es nada.
        if (! $inicio instanceof Carbon || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin) !== 1) {
            return null;
        }

        $hasta = Carbon::parse($fechaFin)->startOfDay();

        if ($hasta->lessThan($inicio)) {
            return null;
        }

        return $this->proyeccion($prorroga, $inicio, $hasta);
    }

    /**
     * Las tres cuentas del modal, en las palabras del prototipo.
     *
     * @return array{tono: string, lineas: list<string>}
     */
    private function proyeccion(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta): array
    {
        $lineas = [
            'Prórroga de '.self::dias(self::diasCorridos($inicio, $hasta))
                .', del '.self::fecha($inicio).' al '.self::fecha($hasta).'.',
        ];

        $lineas[] = $this->lineaDelVinculo($hasta);
        $lineas[] = $this->lineaDeLaProrroga($prorroga, $inicio, $hasta);

        return [
            'tono' => $this->tono($prorroga, $inicio, $hasta),
            'lineas' => array_values(array_filter($lineas)),
        ];
    }

    /**
     * En cuánto quedaría el vínculo acumulado y contra qué tope se compara.
     */
    private function lineaDelVinculo(Carbon $hasta): string
    {
        $total = $this->historial->duracionTotalEnDias($hasta);
        $tope = $this->historial->fechaDelTopeLegal();

        if ($total === null || ! $tope instanceof Carbon) {
            return '';
        }

        $frase = 'El vínculo quedaría en '.self::dias($total).' de los cuatro años permitidos, '
            .'que se cumplen el '.self::fecha($tope).'.';

        if (! $this->historial->superaElTopeLegal($hasta)) {
            return $frase;
        }

        return $frase.' Con esta fecha el vínculo superaría el tope legal, y al superarlo el '
            .'contrato se convierte en indefinido por ley: la salida correcta es «Cambiar a '
            .'término indefinido».';
    }

    /**
     * Qué número de prórroga sería y cuántos cupos cortos quedarían.
     */
    private function lineaDeLaProrroga(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta): string
    {
        $numero = $this->historial->prorrogasAcumuladas() + 1;
        $frase = 'Sería la '.$numero.'ª prórroga del contrato, '
            .mb_strtolower($prorroga->etiqueta(), 'UTF-8').'.';

        $restantes = $this->historial->prorrogasCortasRestantes($prorroga);

        if ($restantes === null || ! self::esInferiorAUnAnio($inicio, $hasta)) {
            return $frase;
        }

        if ($restantes === 0) {
            return $frase.' Es una prórroga inferior a un año y el contrato ya agotó las que la '
                .'ley admite: esta renovación tendría que ser de mínimo un año.';
        }

        // Esta misma prórroga gasta un cupo, así que lo que queda después es uno
        // menos de lo que había antes de registrarla.
        $quedan = $restantes - 1;

        return $frase.' Es una prórroga inferior a un año: '.($quedan === 0
            ? 'gasta el último cupo, y la siguiente renovación de este contrato deberá ser de mínimo un año.'
            : 'después de ella quedarían '.$quedan.($quedan === 1 ? ' prórroga corta' : ' prórrogas cortas').'.');
    }

    /**
     * Ámbar cuando hay algo que la empresa tiene que saber antes de guardar: que
     * se pasa del tope, o que gasta el último cupo de prórroga corta. Azul
     * cuando la prórroga no tiene ninguna consecuencia que advertir.
     */
    private function tono(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta): string
    {
        if ($this->historial->superaElTopeLegal($hasta)) {
            return 'advertencia';
        }

        $restantes = $this->historial->prorrogasCortasRestantes($prorroga);

        $gastaElUltimoCupo = $restantes !== null
            && self::esInferiorAUnAnio($inicio, $hasta)
            && $restantes <= 1;

        return $gastaElUltimoCupo ? 'advertencia' : 'informacion';
    }

    /**
     * Si el tramo propuesto dura menos de un año, que es la única condición de
     * «prórroga corta» del artículo 46 del CST.
     *
     * Es la misma cuenta de `PeriodoContractual::esInferiorAUnAnio()`, aplicada
     * a un tramo que todavía no existe.
     */
    private static function esInferiorAUnAnio(Carbon $inicio, Carbon $hasta): bool
    {
        return $hasta->lessThan($inicio->copy()->addYear()->subDay());
    }

    /**
     * Días corridos contando los dos extremos.
     *
     * Se formaliza en el motor de cálculo (F2-02) igual que en
     * `HistorialDeContrato::duracionTotalEnDias()`. Cuando exista
     * `diasCorridos()`, este método lo llama en lugar de restar fechas.
     */
    private static function diasCorridos(Carbon $desde, Carbon $hasta): int
    {
        return (int) $desde->copy()->startOfDay()->diffInDays($hasta->copy()->startOfDay()) + 1;
    }

    private static function dias(int $cantidad): string
    {
        return $cantidad.($cantidad === 1 ? ' día' : ' días');
    }

    private static function fecha(mixed $fecha): string
    {
        return $fecha instanceof Carbon ? $fecha->format('d/m/Y') : '—';
    }
}
