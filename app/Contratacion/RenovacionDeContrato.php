<?php

namespace App\Contratacion;

use App\Presentacion\Formato;
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
 * ## Las dos validaciones de 5.5
 *
 * También decide, con esa misma fecha y ese mismo tipo, si la prórroga se puede
 * registrar: el tope de cuatro años y el límite de prórrogas cortas viven en
 * `hallazgos()`, con un solo mensaje escrito por regla que sirve al aviso del
 * modal y al error de la validación. Con cuánta fuerza actúa cada una —bloquear
 * o advertir— lo dice `config/validaciones.php`, porque es la decisión B.3 del
 * documento 6 y no está cerrada para las dos.
 *
 * `impedimentos()` es lo que consulta `App\Http\Requests\RenovarContrato` antes
 * de dejar guardar. El aviso de la pantalla y el bloqueo del servidor salen del
 * mismo sitio a propósito: un modal que deshabilita el botón y un servidor que
 * acepta la petición son dos reglas distintas, y una de las dos está mal.
 *
 * ## Lo que aquí no se hace
 *
 * - **No se cuenta ni se cuadra el historial.** Las prórrogas acumuladas, la
 *   duración del vínculo y el tope legal son `HistorialDeContrato`; escribir el
 *   tramo es `Vinculacion::renovar()`.
 */
class RenovacionDeContrato
{
    /**
     * Las claves de `config/validaciones.php` de las dos reglas de 5.5.
     */
    public const VALIDACION_TOPE = 'renovacion_tope_termino_fijo';

    public const VALIDACION_PRORROGA_MINIMA = 'renovacion_prorroga_minima';

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
            'inicio' => Formato::fecha($inicio),
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
                'valor' => Formato::fecha($vigente?->fecha_inicio).' — '.Formato::fecha($vigente?->fecha_fin),
            ],
            [
                'etiqueta' => 'Prórrogas acumuladas',
                'valor' => (string) $this->historial->prorrogasAcumuladas(),
            ],
            [
                'etiqueta' => 'Duración total del vínculo',
                'valor' => $duracion === null ? '—' : Formato::dias($duracion),
            ],
        ];
    }

    /**
     * Qué pasaría si se guardara esa prórroga. Nulo mientras falte un dato.
     *
     * @param  array{tipo?: string|null, fecha_fin?: string|null}  $propuesta
     * @return array{tono: string, bloqueado: bool, lineas: list<string>}|null
     */
    public function proyeccionDe(array $propuesta): ?array
    {
        $resuelta = $this->resolver($propuesta);

        if ($resuelta === null) {
            return null;
        }

        [$prorroga, $inicio, $hasta] = $resuelta;

        return $this->proyeccion($prorroga, $inicio, $hasta);
    }

    /**
     * Los mensajes de las reglas que impiden guardar esa prórroga.
     *
     * Vacío cuando no hay ninguna, cuando falta un dato o cuando la regla está
     * configurada como advertencia. Es lo único que la validación necesita
     * saber: si devuelve algo, no se guarda, y lo que devuelve es el texto que
     * el usuario va a leer.
     *
     * @param  array{tipo?: string|null, fecha_fin?: string|null}  $propuesta
     * @return list<string>
     */
    public function impedimentos(array $propuesta): array
    {
        $resuelta = $this->resolver($propuesta);

        if ($resuelta === null) {
            return [];
        }

        return array_values(array_map(
            fn (array $hallazgo): string => $hallazgo['mensaje'],
            array_filter(
                $this->hallazgos(...$resuelta),
                fn (array $hallazgo): bool => $hallazgo['estado']->bloquea(),
            ),
        ));
    }

    /**
     * La propuesta convertida en objetos, o nulo si todavía no es una propuesta.
     *
     * @param  array{tipo?: string|null, fecha_fin?: string|null}  $propuesta
     * @return array{0: EventoContractual, 1: Carbon, 2: Carbon}|null
     */
    private function resolver(array $propuesta): ?array
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

        return $hasta->lessThan($inicio) ? null : [$prorroga, $inicio, $hasta];
    }

    /**
     * Las tres cuentas del modal, en las palabras del prototipo, y debajo lo que
     * las dos validaciones de 5.5 tengan que decir.
     *
     * @return array{tono: string, bloqueado: bool, lineas: list<string>}
     */
    private function proyeccion(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta): array
    {
        $hallazgos = $this->hallazgos($prorroga, $inicio, $hasta);

        $lineas = [
            'Prórroga de '.Formato::dias(self::diasCorridos($inicio, $hasta))
                .', del '.Formato::fecha($inicio).' al '.Formato::fecha($hasta).'.',
            $this->lineaDelVinculo($hasta),
            $this->lineaDeLaProrroga($prorroga, $inicio, $hasta),
            ...array_map(fn (array $hallazgo): string => $hallazgo['mensaje'], $hallazgos),
        ];

        $tono = $this->tono($hallazgos, $this->gastaElUltimoCupoCorto($prorroga, $inicio, $hasta));

        return [
            'tono' => $tono,
            // Rojo y bloqueado son lo mismo por definición, y por eso se deciden
            // en un solo sitio: el modal deshabilita el botón con esta bandera y
            // el servidor rechaza la petición con `impedimentos()`.
            'bloqueado' => $tono === 'error',
            'lineas' => array_values(array_filter($lineas)),
        ];
    }

    /**
     * Qué dicen las dos validaciones bloqueantes de 5.5 sobre esa prórroga, con
     * el estado que cada una tenga configurado.
     *
     * El mensaje cambia con el estado y no solo el color: «no se puede registrar»
     * en un bloqueo y la consecuencia a secas en una advertencia. Decirle a
     * alguien que no puede guardar y dejarlo guardar es peor que no avisar.
     *
     * @return list<array{estado: EstadoDeValidacion, mensaje: string}>
     */
    private function hallazgos(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta): array
    {
        $hallazgos = [];

        if ($this->historial->superaElTopeLegal($hasta)) {
            $estado = EstadoDeValidacion::configurada(self::VALIDACION_TOPE);

            $hallazgos[] = [
                'estado' => $estado,
                'mensaje' => ($estado->bloquea()
                    ? 'No se puede registrar esta prórroga: con esta fecha'
                    : 'Con esta fecha')
                    .' el vínculo a término fijo superaría el tope legal de cuatro años, y al '
                    .'superarlo el contrato se convierte en indefinido por ley. Usa la acción '
                    .'«Cambiar a término indefinido», que es la salida correcta y deja el '
                    .'contrato al día.',
            ];
        }

        if ($this->historial->prorrogasCortasRestantes($prorroga) === 0
            && self::esInferiorAUnAnio($inicio, $hasta)) {
            $estado = EstadoDeValidacion::configurada(self::VALIDACION_PRORROGA_MINIMA);

            $hallazgos[] = [
                'estado' => $estado,
                'mensaje' => ($estado->bloquea()
                    ? 'No se puede registrar esta prórroga: es inferior a un año'
                    : 'Esta prórroga es inferior a un año')
                    .' y el contrato ya agotó las que la ley admite. A partir de esta '
                    .'renovación el contrato no puede prorrogarse por menos de un año.'
                    .($estado->bloquea()
                        ? ''
                        : ' Si ya está firmada puedes registrarla, y queda documentada en la '
                            .'auditoría con quién la registró.'),
            ];
        }

        return $hallazgos;
    }

    /**
     * En cuánto quedaría el vínculo acumulado y contra qué tope se compara.
     *
     * Solo la cuenta: que la fecha se pase del tope lo dice `hallazgos()`, que es
     * quien sabe si eso impide guardar o no.
     */
    private function lineaDelVinculo(Carbon $hasta): string
    {
        $total = $this->historial->duracionTotalEnDias($hasta);
        $tope = $this->historial->fechaDelTopeLegal();

        if ($total === null || ! $tope instanceof Carbon) {
            return '';
        }

        return 'El vínculo quedaría en '.Formato::dias($total).' de los cuatro años permitidos, '
            .'que se cumplen el '.Formato::fecha($tope).'.';
    }

    /**
     * Qué número de prórroga sería y cuántos cupos cortos quedarían.
     *
     * Cuando ya no queda ninguno, la frase la escribe `hallazgos()`: ahí deja de
     * ser una cuenta y pasa a ser la regla de prórroga mínima.
     */
    private function lineaDeLaProrroga(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta): string
    {
        $numero = $this->historial->prorrogasAcumuladas() + 1;
        $frase = 'Sería la '.$numero.'ª prórroga del contrato, '
            .mb_strtolower($prorroga->etiqueta(), 'UTF-8').'.';

        $restantes = $this->historial->prorrogasCortasRestantes($prorroga);

        if ($restantes === null || $restantes === 0 || ! self::esInferiorAUnAnio($inicio, $hasta)) {
            return $frase;
        }

        // Esta misma prórroga gasta un cupo, así que lo que queda después es uno
        // menos de lo que había antes de registrarla.
        $quedan = $restantes - 1;

        return $frase.' Es una prórroga inferior a un año: '.($quedan === 0
            ? 'gasta el último cupo, y la siguiente renovación de este contrato deberá ser de mínimo un año.'
            : 'después de ella quedarían '.$quedan.($quedan === 1 ? ' prórroga corta' : ' prórrogas cortas').'.');
    }

    /**
     * Rojo cuando la prórroga no se puede guardar, ámbar cuando se puede pero
     * hay una consecuencia que la empresa debe conocer —que se pasa del tope, o
     * que gasta el último cupo corto— y azul cuando no tiene ninguna.
     *
     * El rojo solo aparece cuando algo está mal de verdad (documento 3, sección
     * 3.1), y «mal de verdad» aquí es exactamente «no se puede registrar».
     *
     * @param  list<array{estado: EstadoDeValidacion, mensaje: string}>  $hallazgos
     */
    private function tono(array $hallazgos, bool $gastaElUltimoCupoCorto): string
    {
        foreach ($hallazgos as $hallazgo) {
            if ($hallazgo['estado']->bloquea()) {
                return 'error';
            }
        }

        return $hallazgos !== [] || $gastaElUltimoCupoCorto ? 'advertencia' : 'informacion';
    }

    /**
     * Si esta prórroga deja el contrato sin cupos cortos.
     *
     * Todavía no incumple nada —por eso no es un hallazgo— pero la siguiente
     * renovación ya no podrá ser corta, y eso es una consecuencia que la empresa
     * tiene que conocer antes de firmar.
     */
    private function gastaElUltimoCupoCorto(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta): bool
    {
        return $this->historial->prorrogasCortasRestantes($prorroga) === 1
            && self::esInferiorAUnAnio($inicio, $hasta);
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
}
