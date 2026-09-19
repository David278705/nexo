<?php

namespace App\Contratacion;

use App\Models\Vinculacion;
use App\Presentacion\Formato;
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
     * Desde cuándo corre el tramo indefinido, **propuesto en hoy y editable
     * hacia atrás** (HD-11): quien registra el cambio puede estar poniendo al
     * día un contrato que se volvió indefinido hace unos días, no en este
     * instante.
     *
     * `$digitada` llega ya validada por `CambiarATerminoIndefinido` —dentro de
     * `[limiteInferior(), limiteSuperior()]`—, así que aquí no se vuelve a
     * comprobar el rango. Sin ella, o al armar la pantalla antes de que exista
     * una petición, la propuesta es `limiteSuperior()`.
     *
     * El único ajuste que sigue sin depender del usuario es el de coherencia
     * del historial: en un contrato que todavía no ha empezado, `limiteSuperior()`
     * ya vale lo mismo que `limiteInferior()` —el día del tramo vigente—, así
     * que un tramo que empezara antes que el anterior nunca llega a proponerse.
     */
    public function fechaDelCambio(?Carbon $digitada = null): Carbon
    {
        return $digitada instanceof Carbon ? $digitada->copy()->startOfDay() : $this->limiteSuperior();
    }

    /**
     * No antes del inicio del periodo vigente: un tramo indefinido que
     * empezara antes que el tramo que reemplaza dejaría el historial en un
     * orden imposible de leer.
     */
    public function limiteInferior(): Carbon
    {
        $vigente = $this->historial->vigente()?->fecha_inicio;

        return $vigente instanceof Carbon ? $vigente->copy()->startOfDay() : Carbon::today()->startOfDay();
    }

    /**
     * No después de hoy: el cambio documenta cuándo se acordó, no una fecha
     * futura.
     *
     * Cuando el contrato todavía no ha empezado, el límite inferior ya cae
     * después de hoy; en ese caso el superior se sube a la misma fecha, que es
     * el ajuste de coherencia heredado de F1-11 y la única fecha que ambos
     * límites admiten.
     */
    public function limiteSuperior(): Carbon
    {
        $hoy = Carbon::today()->startOfDay();
        $inferior = $this->limiteInferior();

        return $inferior->greaterThan($hoy) ? $inferior->copy() : $hoy;
    }

    /**
     * Todo lo que el modal de confirmación enseña.
     *
     * Viaja siempre, también en un contrato que no admite el cambio: la pantalla
     * necesita saber que no lo admite para no pintar el botón.
     *
     * `$fechaDigitada` es lo que el usuario ya escribió en el campo de fecha, en
     * `AAAA-MM-DD`: si es válida y cae dentro del rango permitido, las
     * implicaciones se redactan con ella; si falta, no tiene forma de fecha o
     * cae fuera del rango, se redactan con la propuesta por defecto. El servidor
     * es quien decide qué se rechaza cuando el formulario se envíe de verdad
     * (`CambiarATerminoIndefinido`); aquí solo se evita fingir una fecha que no
     * se va a poder guardar.
     *
     * @return array{disponible: bool, entrada: string, titulo: string, implicaciones: list<string>, advertencia: string, fecha: string, minimo: string, maximo: string}
     */
    public function paraLaPantalla(?string $fechaDigitada = null): array
    {
        $fecha = $this->resolverFechaDigitada($fechaDigitada);

        return [
            'disponible' => $this->disponible(),
            'entrada' => 'Vas a cambiar el contrato de '.$this->vinculacion->persona->nombreCompleto()
                .' de término fijo a término indefinido.',
            'titulo' => 'Qué implica este cambio',
            'implicaciones' => $this->implicaciones($fecha),
            'advertencia' => 'Este cambio no se puede revertir desde el sistema. Un contrato '
                .'indefinido no puede volver a ser a término fijo.',
            'fecha' => $fecha->toDateString(),
            'minimo' => $this->limiteInferior()->toDateString(),
            'maximo' => $this->limiteSuperior()->toDateString(),
        ];
    }

    /**
     * La fecha digitada, si tiene forma de fecha y cae dentro del rango
     * permitido; si no, la propuesta por defecto.
     */
    private function resolverFechaDigitada(?string $fechaDigitada): Carbon
    {
        if (! is_string($fechaDigitada) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDigitada) !== 1) {
            return $this->limiteSuperior();
        }

        $parseada = Carbon::parse($fechaDigitada)->startOfDay();

        if ($parseada->lessThan($this->limiteInferior()) || $parseada->greaterThan($this->limiteSuperior())) {
            return $this->limiteSuperior();
        }

        return $parseada;
    }

    /**
     * Las cinco consecuencias del prototipo, en el orden del prototipo.
     *
     * @return list<string>
     */
    private function implicaciones(Carbon $fecha): array
    {
        $ingreso = Formato::fecha($this->vinculacion->fecha_ingreso);

        return [
            'El vínculo continúa. No hay salida ni liquidación.',
            'La fecha de ingreso no cambia: sigue siendo el '.$ingreso.'.',
            'La antigüedad y el saldo de vacaciones siguen corriendo desde esa fecha.',
            'Dejarán de generarse las alertas de vencimiento y renovación.',
            'El cambio queda registrado en el historial con fecha del '
                .Formato::fecha($fecha).' y en la auditoría.',
        ];
    }
}
