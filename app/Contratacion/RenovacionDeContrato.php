<?php

namespace App\Contratacion;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * La acción «Renovar contrato» (documento 1, 5.5, y documento 3, sección 5.4).
 *
 * Responde lo que el modal enseña mientras se digita: **cuánto dura la
 * prórroga**, **en cuánto queda el vínculo acumulado** y **qué número de
 * prórroga sería**. Todo se calcula aquí, en el servidor, y viaja a la pantalla
 * ya escrito: el modal no cuenta un solo día por su cuenta.
 *
 * ## El tipo de prórroga (`PROD-33`, decidido por Angela el 2026-11-11)
 *
 * - **Pactada**: hubo un documento firmado por las partes. La fecha la digita el
 *   usuario y, al elegirla, confirma la declaración `DECLARACION_PACTADA`; queda
 *   en la auditoría con quién la registró.
 * - **Automática**: operó el silencio. La fecha **la pone el sistema**: igual al
 *   tramo vigente, de un año cuando la ley lo exige, y nunca más allá del tope.
 *
 * ## Las validaciones de 5.5
 *
 * - **Tope de cuatro años**: bloquea, con la fecha máxima en el mensaje (D).
 * - **Prórrogas cortas** en un contrato inicial inferior a un año: la regla de
 *   `HistorialDeContrato::limiteParaUnaProrrogaCorta()`, con los mensajes A, C y
 *   E. Si la prórroga de un año chocaría con el tope, gana el tope: se puede
 *   renovar justo hasta la fecha máxima.
 *
 * Con cuánta fuerza actúa cada una lo dice `config/validaciones.php`. El aviso
 * del modal y el bloqueo del servidor salen de `hallazgos()`.
 */
class RenovacionDeContrato
{
    /**
     * Las claves de `config/validaciones.php` de las dos reglas de 5.5.
     */
    public const VALIDACION_TOPE = 'renovacion_tope_termino_fijo';

    public const VALIDACION_PRORROGA_MINIMA = 'renovacion_prorroga_minima';

    /**
     * Lo que confirma quien registra una prórroga pactada. No se pide la fecha
     * de firma: la responsabilidad de la marca es del cliente.
     */
    public const DECLARACION_PACTADA = 'Declaro que esta prórroga consta en un documento firmado por la '
        .'empresa y el trabajador antes del vencimiento del contrato.';

    private function __construct(private readonly HistorialDeContrato $historial) {}

    public static function de(HistorialDeContrato $historial): self
    {
        return new self($historial);
    }

    /**
     * Si esta pantalla ofrece la acción. Solo el término fijo se renueva.
     */
    public function disponible(): bool
    {
        return $this->historial->tipoDeContratoVigente()?->admiteRenovacion() === true
            && $this->historial->fechaDeVencimiento() instanceof Carbon;
    }

    /**
     * Cuándo empieza el tramo nuevo: el día siguiente al vencimiento vigente.
     */
    public function fechaDeInicio(): ?Carbon
    {
        return $this->historial->fechaDeVencimiento()?->copy()->addDay();
    }

    /**
     * La fecha de fin de la prórroga automática, o nula si el contrato ya no
     * admite ninguna porque llegó al tope de cuatro años.
     */
    public function finDeLaAutomatica(): ?Carbon
    {
        $fin = $this->historial->finDeLaProximaProrrogaAutomatica();
        $inicio = $this->fechaDeInicio();

        if (! $fin instanceof Carbon || ! $inicio instanceof Carbon || $fin->lessThan($inicio)) {
            return null;
        }

        return $fin;
    }

    /**
     * Todo lo que necesita el modal.
     *
     * @param  array{tipo?: string|null, fecha_fin?: string|null}  $propuesta  Lo digitado en el modal.
     * @return array<string, mixed>
     */
    public function paraLaPantalla(array $propuesta = []): array
    {
        $inicio = $this->fechaDeInicio();
        $automatica = $this->disponible() ? $this->finDeLaAutomatica() : null;

        return [
            'disponible' => $this->disponible(),
            'inicio' => Formato::fecha($inicio),
            'minimo' => $inicio instanceof Carbon ? $inicio->toDateString() : '',
            'resumen' => $this->resumen(),
            'tipos' => EventoContractual::prorrogasParaElegir(),
            'automatica' => [
                'tipo' => EventoContractual::ProrrogaAutomatica->value,
                'fin' => Formato::fecha($automatica),
            ],
            'pactada' => EventoContractual::ProrrogaPactada->value,
            'declaracion' => self::DECLARACION_PACTADA,
            'propuesta' => [
                'tipo' => $propuesta['tipo'] ?? '',
                'fecha_fin' => $propuesta['fecha_fin'] ?? '',
            ],
            'proyeccion' => $this->proyeccionDe($propuesta),
        ];
    }

    /**
     * El bloque de solo lectura de arriba del modal.
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
                'valor' => (string) $this->historial->prorrogasRegistradas(),
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
     * @param  array{tipo?: string|null, fecha_fin?: string|null}  $propuesta
     * @return list<string>
     */
    public function impedimentos(array $propuesta): array
    {
        $resuelta = $this->resolver($propuesta);

        if ($resuelta === null) {
            return [];
        }

        [$prorroga, $inicio, $hasta] = $resuelta;

        return array_values(array_map(
            fn (array $hallazgo): string => $hallazgo['mensaje'],
            array_filter(
                $this->hallazgos($prorroga, $inicio, $hasta),
                fn (array $hallazgo): bool => $hallazgo['estado']->bloquea(),
            ),
        ));
    }

    /**
     * La propuesta convertida en objetos, o nulo si todavía no es una propuesta.
     * En la automática la fecha no se lee: la pone el sistema.
     *
     * @param  array{tipo?: string|null, fecha_fin?: string|null}  $propuesta
     * @return array{0: EventoContractual, 1: Carbon, 2: Carbon}|null
     */
    private function resolver(array $propuesta): ?array
    {
        $tipo = $propuesta['tipo'] ?? null;
        $inicio = $this->fechaDeInicio();
        $prorroga = is_string($tipo) ? EventoContractual::tryFrom($tipo) : null;

        if (! $prorroga instanceof EventoContractual || ! $prorroga->esProrroga() || ! $inicio instanceof Carbon) {
            return null;
        }

        if ($prorroga === EventoContractual::ProrrogaAutomatica) {
            $hasta = $this->finDeLaAutomatica();

            return $hasta instanceof Carbon ? [$prorroga, $inicio, $hasta] : null;
        }

        $fechaFin = $propuesta['fecha_fin'] ?? null;

        // La fecha llega de un campo de tipo `date`, así que siempre en ISO.
        if (! is_string($fechaFin) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin) !== 1) {
            return null;
        }

        $hasta = Carbon::parse($fechaFin)->startOfDay();

        return $hasta->lessThan($inicio) ? null : [$prorroga, $inicio, $hasta];
    }

    /**
     * @return array{tono: string, bloqueado: bool, lineas: list<string>}
     */
    private function proyeccion(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta): array
    {
        $hallazgos = $this->hallazgos($prorroga, $inicio, $hasta);
        $avisos = $this->avisos($prorroga, $inicio, $hasta, $hallazgos);

        $lineas = [
            'Prórroga de '.Formato::dias(MotorDeCalculo::diasCorridos($inicio, $hasta))
                .', del '.Formato::fecha($inicio).' al '.Formato::fecha($hasta).'.',
            $this->lineaDelVinculo($hasta),
            'Sería la '.($this->historial->prorrogasRegistradas() + 1).'ª prórroga del contrato, '
                .mb_strtolower($prorroga->etiqueta(), 'UTF-8').'.',
            ...$avisos,
            ...array_map(fn (array $hallazgo): string => $hallazgo['mensaje'], $hallazgos),
        ];

        $tono = 'informacion';

        foreach ($hallazgos as $hallazgo) {
            $tono = $hallazgo['estado']->bloquea() ? 'error' : 'advertencia';

            if ($tono === 'error') {
                break;
            }
        }

        if ($tono === 'informacion' && $avisos !== []) {
            $tono = 'advertencia';
        }

        return [
            'tono' => $tono,
            'bloqueado' => $tono === 'error',
            'lineas' => array_values(array_filter($lineas)),
        ];
    }

    /**
     * Lo que dicen las dos validaciones de 5.5 sobre esa prórroga, con el estado
     * que cada una tenga configurado.
     *
     * @return list<array{estado: EstadoDeValidacion, mensaje: string}>
     */
    private function hallazgos(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta): array
    {
        $hallazgos = [];
        $maxima = $this->historial->fechaDelTopeLegal();

        if ($maxima instanceof Carbon && $this->historial->superaElTopeLegal($hasta)) {
            $hallazgos[] = [
                'estado' => EstadoDeValidacion::configurada(self::VALIDACION_TOPE),
                'mensaje' => 'Con esta renovación el contrato superaría el tope legal de 4 años a término fijo '
                    .'(CST art. 46). La fecha de fin no puede ser posterior al '.Formato::fecha($maxima).'. '
                    .'Puede renovar hasta esa fecha o cambiar el contrato a término indefinido. Si la '
                    .'relación continúa después de esa fecha, el contrato es indefinido por ley.',
            ];
        }

        // La automática no se valida aquí: su fecha la calcula el sistema con la
        // misma regla, así que nunca es corta cuando no puede serlo.
        if ($prorroga === EventoContractual::ProrrogaPactada
            && self::esInferiorAUnAnio($inicio, $hasta)
            && ! $this->llegaJustoAlTope($inicio, $hasta)) {
            $limite = $this->historial->limiteParaUnaProrrogaCorta($prorroga);

            if ($limite !== null) {
                $hallazgos[] = [
                    'estado' => EstadoDeValidacion::configurada(self::VALIDACION_PRORROGA_MINIMA),
                    'mensaje' => $this->mensajeDelLimite($limite, $inicio),
                ];
            }
        }

        return $hallazgos;
    }

    /**
     * Los avisos que no impiden guardar pero que la empresa tiene que leer
     * antes de firmar.
     *
     * @param  list<array{estado: EstadoDeValidacion, mensaje: string}>  $hallazgos
     * @return list<string>
     */
    private function avisos(EventoContractual $prorroga, Carbon $inicio, Carbon $hasta, array $hallazgos): array
    {
        $avisos = [];
        $sinTope = $this->historial->finDeLaProximaAutomaticaSinTope();

        if ($prorroga === EventoContractual::ProrrogaAutomatica) {
            if ($this->historial->limiteParaUnaProrrogaCorta($prorroga) === HistorialDeContrato::CUARTA_AUTOMATICA) {
                $avisos[] = 'Las 3 prórrogas anteriores fueron pactadas, así que la ley permite una 4.ª prórroga '
                    .'corta, pero solo si también es pactada por escrito. Si esta prórroga fue automática (sin '
                    .'documento firmado), es por un año: la fecha de fin será el '.Formato::fecha($hasta).'.';
            } elseif ($this->historial->laProximaAutomaticaEsDeUnAnio()) {
                $avisos[] = 'Este contrato ya lleva 3 prórrogas o más: la prórroga automática es por un año '
                    .'(CST art. 46).';
            }

            if ($sinTope instanceof Carbon && $sinTope->greaterThan($hasta)) {
                $avisos[] = 'Por el tope legal de 4 años, la prórroga automática va solo hasta el '
                    .Formato::fecha($hasta).'. Si la relación continúa después de esa fecha, el contrato es '
                    .'indefinido por ley.';
            }
        }

        if ($hallazgos === [] && self::esInferiorAUnAnio($inicio, $hasta)
            && $this->historial->limiteDespuesDe($prorroga) !== null) {
            $avisos[] = 'Después de esta prórroga, la siguiente renovación de este contrato deberá ser de '
                .'mínimo un año.';
        }

        return $avisos;
    }

    /**
     * Los textos A, C y E de la propuesta de Angela.
     */
    private function mensajeDelLimite(string $limite, Carbon $inicio): string
    {
        $minimo = Formato::fecha($inicio->copy()->addYear()->subDay());

        return match ($limite) {
            HistorialDeContrato::LIMITE_SUPERADO => 'Este contrato ya tiene 4 prórrogas. Desde la 5.ª, cada '
                .'renovación debe ser de mínimo un año, con fecha de fin igual o posterior al '.$minimo
                .'. (CST art. 46)',
            HistorialDeContrato::LIMITE_POR_CARGA => 'Este contrato se registró en la carga inicial y no es '
                .'posible verificar cómo se hicieron sus prórrogas anteriores a Nexo. Por eso aplica el límite '
                .'de 3 prórrogas cortas: esta renovación debe ser de mínimo un año, con fecha de fin igual o '
                .'posterior al '.$minimo.'. (CST art. 46)',
            default => 'Este contrato ya tiene 3 prórrogas inferiores a un año y al menos una de ellas fue '
                .'automática (sin documento firmado). Por eso aplica el límite de 3 prórrogas cortas: esta '
                .'renovación debe ser de mínimo un año, con fecha de fin igual o posterior al '.$minimo
                .'. (CST art. 46)',
        };
    }

    /**
     * Si la prórroga corta llega exactamente a la fecha máxima del tope y una de
     * un año ya no cabría: ahí gana el tope y se permite (pendiente de confirmar
     * con el asesor laboral).
     */
    private function llegaJustoAlTope(Carbon $inicio, Carbon $hasta): bool
    {
        $maxima = $this->historial->fechaDelTopeLegal();

        return $maxima instanceof Carbon
            && $hasta->isSameDay($maxima)
            && $inicio->copy()->addYear()->subDay()->greaterThan($maxima);
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

        return 'El vínculo quedaría en '.Formato::dias($total).' de los cuatro años permitidos, '
            .'que se cumplen el '.Formato::fecha($tope).'.';
    }

    /**
     * Si el tramo propuesto dura menos de un año: la condición de «prórroga
     * corta» del artículo 46 del CST.
     */
    private static function esInferiorAUnAnio(Carbon $inicio, Carbon $hasta): bool
    {
        return $hasta->lessThan($inicio->copy()->addYear()->subDay());
    }
}
