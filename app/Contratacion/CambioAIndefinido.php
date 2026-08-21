<?php

namespace App\Contratacion;

use App\Models\Vinculacion;
use Illuminate\Support\Carbon;

/**
 * La acción «Cambiar a término indefinido» (documento 1, 5.5, y documento 3,
 * sección 5.4).
 *
 * No pide ningún dato: es una confirmación. Lo único que este objeto responde es
 * **si la acción existe en ese contrato** y **qué implica el cambio**, que es lo
 * que el modal enseña antes de que alguien confirme algo que no se puede
 * deshacer.
 *
 * ## Por qué las frases se escriben aquí
 *
 * Son las mismas cinco del prototipo y todas afirman algo que el sistema
 * después cumple: que el vínculo continúa, que la fecha de ingreso no se mueve,
 * que la antigüedad sigue corriendo desde ella, que dejan de generarse las
 * alertas de vencimiento y que el cambio queda en el historial. Si vivieran en
 * el componente Vue, la pantalla podría prometer una cosa y el servidor hacer
 * otra; escritas aquí, la misma prueba que comprueba el efecto lee el texto.
 *
 * ## Lo que aquí no se hace
 *
 * - **No se escribe.** El tramo lo agrega `Vinculacion::cambiarATerminoIndefinido()`,
 *   que pasa por `registrarPeriodo()` como cualquier otro hecho contractual.
 * - **No se cuenta nada del historial.** Eso es `HistorialDeContrato`.
 */
class CambioAIndefinido
{
    private function __construct(
        private readonly Vinculacion $vinculacion,
        private readonly HistorialDeContrato $historial,
    ) {}

    public static function de(Vinculacion $vinculacion, ?HistorialDeContrato $historial = null): self
    {
        return new self($vinculacion, $historial ?? $vinculacion->historial());
    }

    /**
     * Si esta pantalla ofrece la acción.
     *
     * Solo el término fijo (documento 1, 5.5). El cambio inverso no existe, y en
     * obra o labor el paso a indefinido se hace por otrosí, fuera del sistema.
     */
    public function disponible(): bool
    {
        return $this->historial->tipoDeContratoVigente()?->admiteRenovacion() === true;
    }

    /**
     * Desde cuándo corre el tramo indefinido: **el día en que se hace el cambio**.
     *
     * Es lo que pide el documento 1, 5.5: el cambio «queda registrado con la
     * fecha en que se hizo, para efectos de trazabilidad y reportes». No es la
     * fecha de ingreso —esa no se toca— ni el vencimiento del tramo anterior:
     * un contrato que se vuelve indefinido lo es desde que las partes lo
     * acordaron, no desde que habría vencido.
     *
     * El único ajuste es de coherencia del historial, no de derecho laboral: en
     * un contrato que todavía no ha empezado, el tramo nuevo arranca el mismo
     * día que el vigente. Un tramo que empezara antes que el anterior dejaría el
     * historial en un orden imposible de leer.
     */
    public function fechaDelCambio(): Carbon
    {
        $hoy = Carbon::today()->startOfDay();
        $vigente = $this->historial->vigente()?->fecha_inicio;

        return $vigente instanceof Carbon && $hoy->lessThan($vigente)
            ? $vigente->copy()
            : $hoy;
    }

    /**
     * Todo lo que el modal de confirmación enseña.
     *
     * Viaja siempre, también en un contrato que no admite el cambio: la pantalla
     * necesita saber que no lo admite para no pintar el botón.
     *
     * @return array{disponible: bool, entrada: string, titulo: string, implicaciones: list<string>, advertencia: string}
     */
    public function paraLaPantalla(): array
    {
        return [
            'disponible' => $this->disponible(),
            'entrada' => 'Vas a cambiar el contrato de '.$this->vinculacion->persona->nombreCompleto()
                .' de término fijo a término indefinido.',
            'titulo' => 'Qué implica este cambio',
            'implicaciones' => $this->implicaciones(),
            'advertencia' => 'Este cambio no se puede revertir desde el sistema. Un contrato '
                .'indefinido no puede volver a ser a término fijo.',
        ];
    }

    /**
     * Las cinco consecuencias del prototipo, en el orden del prototipo.
     *
     * @return list<string>
     */
    private function implicaciones(): array
    {
        $ingreso = self::fecha($this->vinculacion->fecha_ingreso);

        return [
            'El vínculo continúa. No hay salida ni liquidación.',
            'La fecha de ingreso no cambia: sigue siendo el '.$ingreso.'.',
            'La antigüedad y el saldo de vacaciones siguen corriendo desde esa fecha.',
            'Dejarán de generarse las alertas de vencimiento y renovación.',
            'El cambio queda registrado en el historial con la fecha de hoy ('
                .self::fecha($this->fechaDelCambio()).') y en la auditoría.',
        ];
    }

    private static function fecha(mixed $fecha): string
    {
        return $fecha instanceof Carbon ? $fecha->format('d/m/Y') : '—';
    }
}
