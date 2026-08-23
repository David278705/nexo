<?php

namespace App\Contratacion;

use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * ## Por qué las dos cuentas de días de aquí no son del motor
 *
 * `diasParaVencer()` y `diasParaElPreaviso()` son **distancias de calendario con
 * signo** —negativas cuando la fecha ya pasó—, y solo alimentan el texto que se
 * lee en pantalla: «Vence en 26 días», «Vencido hace 4 días». No son ninguna de
 * las tres reglas de conteo, que cuentan días trabajados o de novedad y por eso
 * viven en el motor con nombres distintos.
 *
 * El número que sí va escrito en un correo —«en dos días (14 de junio)»— **no
 * sale de aquí**: lo calcula `MotorDeCalculo::diasDeAnticipacion()` y lo redacta
 * `ReferenciaTemporal`. Ninguna cifra con consecuencia legal se cuenta en esta
 * clase, que es lo que hace que la excepción sea segura.
 */
class VigenciaDelContrato
{
    /**
     * El estado del contrato con el que se filtra el listado (documento 1,
     * 11.3), que es exactamente el que ya enseña su columna «Estado».
     *
     * No son un enumerado nuevo ni una regla nueva: son los tres estados que
     * `tono()` viene pintando desde F1-08 —verde, ámbar y rojo—, con un nombre
     * cada uno para que se puedan pedir por la dirección. El texto los nombra
     * por lo que hay que hacer con ellos, que es la pregunta de la pantalla:
     *
     * - **Al día:** todavía queda plazo para decidir, o el contrato no vence.
     * - **Plazo de preaviso pasado:** se llegó a la fecha límite para avisar, y
     *   a partir de ahí callar tiene consecuencia jurídica.
     * - **Vencido sin gestionar:** la fecha pasó sin prórroga ni terminación.
     */
    public const ESTADOS = [
        ['valor' => 'al_dia', 'texto' => 'Al día'],
        ['valor' => 'preaviso_pasado', 'texto' => 'Plazo de preaviso pasado'],
        ['valor' => self::VENCIDO, 'texto' => 'Vencido sin gestionar'],
    ];

    /**
     * El estado que la tarjeta de acción del panel de inicio pide por la
     * dirección. Es constante y no un literal más porque desde F4-03 lo escriben
     * tres sitios y el enlace que no coincidiera no filtraría nada.
     */
    public const VENCIDO = 'vencido';

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
            ? 'Vence en '.Formato::dias($dias)
            : 'Vencido hace '.Formato::dias(-$dias);
    }

    /**
     * En cuál de los tres estados de `ESTADOS` está el contrato hoy.
     *
     * Un contrato sin plazo —indefinido u obra o labor— está al día: no hay
     * ninguna fecha que se le pueda pasar.
     */
    public function estadoDeGestion(): string
    {
        if ($this->yaVencio()) {
            return self::VENCIDO;
        }

        $preaviso = $this->diasParaElPreaviso();

        return $preaviso !== null && $preaviso <= 0 ? 'preaviso_pasado' : 'al_dia';
    }

    /**
     * El color de la etiqueta de estado.
     *
     * El rojo solo cuando algo está mal de verdad, que aquí es un contrato que
     * venció sin que nadie registrara la prórroga ni la terminación. El ámbar
     * arranca el día en que se cumple la fecha límite del preaviso: a partir de
     * ahí, callar ya tiene consecuencia jurídica.
     *
     * Sale del estado de gestión y no de su propia cuenta, para que el color de
     * la fila y el filtro que la trae no puedan discrepar.
     */
    public function tono(): string
    {
        return match ($this->estadoDeGestion()) {
            self::VENCIDO => 'error',
            'preaviso_pasado' => 'advertencia',
            default => 'exito',
        };
    }

    /**
     * Acota una consulta de vinculaciones a uno de los tres estados.
     *
     * Es la otra cara de `estadoDeGestion()`: aquí la misma pregunta se
     * responde en SQL, porque filtrar en PHP obligaría a traer la empresa
     * entera para descartar la mayoría. Las dos definiciones tienen que decir
     * lo mismo, y por eso viven pegadas y hay una prueba que recorre las filas
     * de cada estado comprobando que su tono es el que le toca.
     *
     * Se filtra por `vinculaciones.fecha_fin`, que es el vencimiento vigente:
     * la columna se mueve con cada prórroga junto al historial
     * (`Vinculacion::registrarPeriodo()`), igual que en el filtro de rango de
     * vencimiento de al lado.
     *
     * @param  Builder<Vinculacion>  $consulta
     */
    public static function acotarAlEstado(Builder $consulta, string $estado, ?Carbon $hoy = null): void
    {
        $dia = ($hoy ?? Carbon::today())->copy()->startOfDay()->toDateString();
        $limite = ($hoy ?? Carbon::today())->copy()
            ->startOfDay()
            ->addDays(HistorialDeContrato::diasDePreaviso())
            ->toDateString();

        if ($estado === self::VENCIDO) {
            $consulta->whereNotNull('fecha_fin')->whereDate('fecha_fin', '<', $dia);

            return;
        }

        if ($estado === 'preaviso_pasado') {
            $consulta
                ->whereNotNull('fecha_fin')
                ->whereDate('fecha_fin', '>=', $dia)
                ->whereDate('fecha_fin', '<=', $limite);

            return;
        }

        // Al día: los que no vencen y los que vencen después de la fecha límite
        // del preaviso.
        $consulta->where(fn (Builder $contrato) => $contrato
            ->whereNull('fecha_fin')
            ->orWhereDate('fecha_fin', '>', $limite));
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
            ? 'Quedan '.Formato::dias($dias)
            : 'El plazo se pasó hace '.Formato::dias(-$dias);
    }
}
