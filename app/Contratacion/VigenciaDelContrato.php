<?php

namespace App\Contratacion;

use Illuminate\Support\Carbon;

/**
 * En qué punto de su vida está un contrato hoy: cuánto le falta para vencer y
 * si el plazo del preaviso todavía alcanza.
 *
 * Es un objeto aparte de `HistorialDeContrato` porque responde a otra pregunta.
 * El historial calcula lo que se deduce de los tramos —prórrogas, duración,
 * tope legal— y no depende del día en que se mire. Esto sí: la misma
 * vinculación está «Vence en 26 días» hoy y «Vencido hace 4 días» un mes
 * después. Separarlos es lo que permite probar el estado fijando la fecha sin
 * tocar el historial.
 *
 * Lo usan las dos pantallas de F1-08 —el listado y el detalle— y lo usarán las
 * alertas de vencimiento del capítulo 9, que preguntan exactamente lo mismo.
 *
 * ## Lo que aquí no se decide
 *
 * No se decide cuándo mandar un correo ni cuándo bloquear una prórroga. Aquí
 * solo se responde en qué día está el contrato; qué se hace con eso es del
 * motor de alertas (F3-04) y de las validaciones (F1-10).
 */
class VigenciaDelContrato
{
    private function __construct(
        private readonly ?Carbon $vencimiento,
        private readonly ?Carbon $limiteDePreaviso,
        private readonly Carbon $hoy,
    ) {}

    /**
     * La vigencia que se deduce del historial de un contrato.
     *
     * `$hoy` se recibe para poder situarse en cualquier día en las pruebas y en
     * el proceso diario de alertas. Sin él, es hoy en `America/Bogota`.
     */
    public static function de(HistorialDeContrato $historial, ?Carbon $hoy = null): self
    {
        return new self(
            $historial->fechaDeVencimiento()?->copy()->startOfDay(),
            $historial->fechaLimiteDePreaviso()?->startOfDay(),
            ($hoy ?? Carbon::today())->copy()->startOfDay(),
        );
    }

    /**
     * Si el contrato termina en una fecha conocida.
     *
     * Solo el término fijo la tiene. El indefinido no vence y la obra o labor
     * termina con la obra, así que de ninguno de los dos se puede decir que
     * «vence en tantos días» (documento 1, 5.4).
     */
    public function tienePlazo(): bool
    {
        return $this->vencimiento instanceof Carbon;
    }

    public function fechaDeVencimiento(): ?Carbon
    {
        return $this->vencimiento?->copy();
    }

    public function fechaLimiteDePreaviso(): ?Carbon
    {
        return $this->limiteDePreaviso?->copy();
    }

    /**
     * Cuántos días faltan para el vencimiento. Negativo si ya pasó, cero si es
     * hoy, nulo si no hay plazo.
     */
    public function diasParaVencer(): ?int
    {
        return $this->vencimiento instanceof Carbon
            ? (int) $this->hoy->diffInDays($this->vencimiento, false)
            : null;
    }

    /**
     * Cuántos días quedan para avisar que el contrato no se prorroga. Negativo
     * si el plazo ya se pasó.
     */
    public function diasParaElPreaviso(): ?int
    {
        return $this->limiteDePreaviso instanceof Carbon
            ? (int) $this->hoy->diffInDays($this->limiteDePreaviso, false)
            : null;
    }

    public function yaVencio(): bool
    {
        $dias = $this->diasParaVencer();

        return $dias !== null && $dias < 0;
    }

    /**
     * El estado que se lee en la columna del listado y junto al título del
     * detalle, con las palabras del prototipo.
     */
    public function estado(): string
    {
        $dias = $this->diasParaVencer();

        if ($dias === null) {
            return 'Vigente';
        }

        if ($dias === 0) {
            return 'Vence hoy';
        }

        return $dias > 0
            ? 'Vence en '.self::dias($dias)
            : 'Vencido hace '.self::dias(-$dias);
    }

    /**
     * El color de la etiqueta de estado.
     *
     * El rojo solo cuando algo está mal de verdad, que aquí es un contrato que
     * venció sin que nadie registrara la prórroga ni la terminación. El ámbar
     * arranca el día en que se cumple la fecha límite del preaviso: a partir de
     * ahí, callar ya tiene consecuencia jurídica.
     */
    public function tono(): string
    {
        if ($this->yaVencio()) {
            return 'error';
        }

        $preaviso = $this->diasParaElPreaviso();

        return $preaviso !== null && $preaviso <= 0 ? 'advertencia' : 'exito';
    }

    /**
     * La línea que acompaña a la fecha límite del preaviso en el detalle.
     *
     * Toda referencia temporal lleva su fecha (regla de interfaz), y esta la
     * lleva en la tarjeta de al lado: aquí solo va la cuenta.
     */
    public function notaDePreaviso(): string
    {
        $dias = $this->diasParaElPreaviso();

        if ($dias === null) {
            return '';
        }

        if ($dias === 0) {
            return 'Hoy es el último día para avisar';
        }

        return $dias > 0
            ? 'Quedan '.self::dias($dias)
            : 'El plazo se pasó hace '.self::dias(-$dias);
    }

    private static function dias(int $cantidad): string
    {
        return $cantidad.($cantidad === 1 ? ' día' : ' días');
    }
}
