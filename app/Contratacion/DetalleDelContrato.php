<?php

namespace App\Contratacion;

use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * El detalle del contrato (documento 3, sección 5.4).
 *
 * Arma lo que la pantalla enseña: el contrato vigente, **la fecha límite del
 * preaviso destacada** —que el documento 1, 5.5, exige que se vea aquí y no
 * solo que llegue por correo— y debajo el historial completo empezando por el
 * periodo inicial.
 *
 * ## Lo que aquí no se calcula
 *
 * Ni una cuenta. Las prórrogas, la duración del vínculo y el tope legal son
 * `HistorialDeContrato`; los días que faltan y el estado son
 * `VigenciaDelContrato`; y la tabla del historial es
 * `FichaDeEmpleado::filasDelHistorial()`, la misma que enseña la pestaña de
 * contrato de la ficha. Escribir aquí una segunda versión de cualquiera de las
 * tres las separaría a la primera corrección.
 */
class DetalleDelContrato
{
    private function __construct(
        private readonly Vinculacion $vinculacion,
        private readonly HistorialDeContrato $historial,
        private readonly VigenciaDelContrato $vigencia,
    ) {}

    public static function de(Vinculacion $vinculacion, ?Carbon $hoy = null): self
    {
        $vinculacion->loadMissing(['persona', 'periodos']);

        $historial = HistorialDeContrato::con(
            $vinculacion->periodos,
            (int) $vinculacion->getAttribute('prorrogas_previas_a_la_carga'),
        );

        return new self($vinculacion, $historial, VigenciaDelContrato::de($historial, $hoy));
    }

    /**
     * @param  array{tipo?: string|null, fecha_fin?: string|null}  $propuesta  Lo digitado en el modal de renovación.
     * @return array<string, mixed>
     */
    public function paraLaPantalla(array $propuesta = []): array
    {
        return [
            'contrato' => $this->encabezado(),
            'indicadores' => $this->indicadores(),
            'campos' => $this->campos(),
            'historial' => FichaDeEmpleado::filasDelHistorial($this->historial),
            'notaDeHistorial' => $this->historial->notaDeCargaInicial(),
            /*
             * El modal de «Renovar contrato» (F1-09). Viaja siempre, también en
             * un contrato que no admite renovación: la pantalla necesita saber
             * que no la admite para no pintar el botón.
             *
             * Las cuentas del modal llegan ya escritas desde el servidor. Cuando
             * el usuario cambia la fecha o el tipo de prórroga, la pantalla pide
             * de nuevo solo esta prop y no calcula nada por su cuenta.
             */
            'renovacion' => RenovacionDeContrato::de($this->historial)->paraLaPantalla($propuesta),
            /*
             * El modal de «Cambiar a término indefinido» (F1-11). No pide ningún
             * dato: es la confirmación de un cambio que no se puede deshacer, y
             * lo que enseña son sus consecuencias, escritas también en el
             * servidor.
             */
            'cambioAIndefinido' => CambioAIndefinido::de($this->vinculacion, $this->historial)
                ->paraLaPantalla(),
        ];
    }

    /**
     * Quién es y en qué punto está: «Contrato de Juan Pablo Mejía · Auxiliar de
     * Bodega · Término fijo · Vence en 26 días».
     *
     * @return array{empleadoId: int, empleado: string, cargo: string, tipo: string, estado: string, tono: string, admiteRenovacion: bool}
     */
    public function encabezado(): array
    {
        $tipo = $this->tipoVigente();

        return [
            'empleadoId' => (int) $this->vinculacion->getKey(),
            'empleado' => $this->vinculacion->persona->nombreCompleto(),
            'cargo' => (string) $this->vinculacion->getAttribute('cargo'),
            'tipo' => $tipo->etiqueta(),
            'estado' => $this->vigencia->estado(),
            'tono' => $this->vigencia->tono(),
            /*
             * Renovar y cambiar a indefinido son exclusivas del término fijo
             * (documento 1, 5.5). Cada botón lee su propia bandera: la de
             * renovar exige además un vencimiento del que colgar la prórroga.
             */
            'admiteRenovacion' => $tipo->admiteRenovacion(),
        ];
    }

    /**
     * Las cuatro tarjetas de arriba del prototipo.
     *
     * En un contrato sin plazo —indefinido u obra o labor— no hay vencimiento
     * ni preaviso ni tope que enseñar, así que quedan las dos que sí existen.
     * Una tarjeta con una raya no informa: ocupa.
     *
     * @return list<array{valor: string, etiqueta: string, nota: string, tono: string}>
     */
    public function indicadores(): array
    {
        $prorrogas = $this->historial->prorrogasAcumuladas();

        if (! $this->vigencia->tienePlazo()) {
            return [
                self::tarjeta((string) $prorrogas, 'Prórrogas acumuladas'),
                self::tarjeta(
                    Formato::fecha($this->historial->inicial()?->fecha_inicio),
                    'Inicio del contrato',
                    'Este contrato no termina en una fecha conocida.',
                ),
            ];
        }

        return [
            self::tarjeta(
                Formato::fecha($this->vigencia->fechaDeVencimiento()),
                'Fecha de vencimiento',
                $this->vigencia->estado(),
                $this->vigencia->tono() === 'exito' ? '' : $this->vigencia->tono(),
            ),
            self::tarjeta(
                Formato::fecha($this->vigencia->fechaLimiteDePreaviso()),
                'Límite de preaviso',
                $this->vigencia->notaDePreaviso(),
                ($this->vigencia->diasParaElPreaviso() ?? 1) <= 0 ? 'advertencia' : '',
            ),
            self::tarjeta((string) $prorrogas, 'Prórrogas acumuladas'),
            self::tarjeta($this->duracionTotal(), 'Duración total del vínculo', $this->notaDelTope()),
        ];
    }

    /**
     * La tarjeta de datos del contrato vigente, con la misma forma que los
     * bloques de la ficha del empleado.
     *
     * @return list<array{etiqueta: string, valor: string}>
     */
    public function campos(): array
    {
        $tipo = $this->tipoVigente();

        $campos = [
            'Empleado' => $this->vinculacion->persona->nombreCompleto(),
            'Documento' => (string) $this->vinculacion->persona->numero_documento,
            'Cargo' => (string) $this->vinculacion->getAttribute('cargo'),
            'Tipo de contrato' => $tipo->etiqueta(),
            'Fecha de ingreso' => Formato::fecha($this->vinculacion->fecha_ingreso),
            'Inicio del periodo vigente' => Formato::fecha($this->historial->vigente()?->fecha_inicio),
        ];

        if ($tipo->exigeFechaFin()) {
            $campos['Fecha de vencimiento'] = Formato::fecha($this->vigencia->fechaDeVencimiento());
            $campos['Fecha límite de preaviso'] = Formato::fecha($this->vigencia->fechaLimiteDePreaviso());
            $campos['Tope legal de cuatro años'] = Formato::fecha($this->historial->fechaDelTopeLegal());
        }

        return array_map(
            fn (string $etiqueta, string $valor): array => ['etiqueta' => $etiqueta, 'valor' => $valor],
            array_keys($campos),
            array_values($campos),
        );
    }

    private function tipoVigente(): TipoContrato
    {
        return $this->historial->tipoDeContratoVigente() ?? $this->vinculacion->tipo_contrato;
    }

    private function duracionTotal(): string
    {
        $dias = $this->historial->duracionTotalEnDias();

        return $dias === null ? Formato::VACIO : Formato::dias($dias);
    }

    private function notaDelTope(): string
    {
        $tope = $this->historial->fechaDelTopeLegal();

        return $tope instanceof Carbon
            ? 'Tope legal de cuatro años: '.Formato::fecha($tope)
            : '';
    }

    /**
     * @return array{valor: string, etiqueta: string, nota: string, tono: string}
     */
    private static function tarjeta(string $valor, string $etiqueta, string $nota = '', string $tono = ''): array
    {
        return ['valor' => $valor, 'etiqueta' => $etiqueta, 'nota' => $nota, 'tono' => $tono];
    }
}
