<?php

namespace App\Dominio\Alertas;

use App\Dominio\Calculo\MotorDeCalculo;
use App\Presentacion\Formato;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Cómo se nombra en el texto de una alerta el día del que habla (documento 1, 9.1).
 *
 * Es la segunda mitad del corrimiento al día hábil anterior. La primera —que el
 * proceso adelante la evaluación y guarde la fecha corrida— ya la trajo F3-01.
 * Lo que faltaba es que el texto no mienta: un aviso escrito para el domingo
 * dice «mañana», y si sale el viernes tiene que decir «en tres días». Un correo
 * que se adelanta sin ajustar la frase es peor que no mandarlo, porque el que
 * lo lee actúa sobre una fecha equivocada.
 *
 * ## Por qué está aquí y no dentro de cada regla
 *
 * Las once alertas del capítulo 9 comparten esta frase. Escrita once veces, es
 * seguro que una queda mal el día en que Colombia mueva un festivo y aparezca
 * un adelanto de cuatro días que nadie había visto. Escrita una vez, se prueba
 * una vez.
 *
 * No cuenta días: eso es de `MotorDeCalculo::diasDeAnticipacion()`, que es
 * donde vive todo conteo. Aquí solo se redacta.
 *
 * ## La frase
 *
 * | Días | Frase |
 * |---|---|
 * | 0 | `hoy (21 de agosto)` |
 * | 1 | `mañana (21 de agosto)` |
 * | 2 a 9 | `en dos días (21 de agosto)` … `en nueve días (21 de agosto)` |
 * | 10 o más | `en 15 días (21 de agosto)` |
 *
 * Las de una cifra van con letras y las de dos con número, que es lo que hacen
 * las plantillas del documento 2, sección 5.4: «termina en dos días ({fecha})»
 * y «completará en 15 días ({fecha})». La fecha exacta entre paréntesis va
 * siempre, sin excepción: es la regla de redacción de la sección 9.1 y existe
 * para que quien recibe el correo no tenga que calcular nada.
 */
final class ReferenciaTemporal
{
    /**
     * Las cantidades que se escriben con letras.
     *
     * Empieza en el dos porque el uno es «mañana» y el cero es «hoy», así que
     * «en un día» no se escribe nunca.
     */
    private const EN_LETRAS = [
        2 => 'dos',
        3 => 'tres',
        4 => 'cuatro',
        5 => 'cinco',
        6 => 'seis',
        7 => 'siete',
        8 => 'ocho',
        9 => 'nueve',
    ];

    private function __construct(
        public readonly int $dias,
        public readonly Carbon $fecha,
    ) {}

    /**
     * La referencia de un correo que sale ese día y habla de ese hecho.
     *
     * Son las dos fechas que la regla de disparo ya recibe resueltas: la del
     * envío es hoy y la del hecho es el día que la alerta vigila. Cuando
     * coinciden no hubo corrimiento; cuando no, la diferencia es justo el
     * adelanto y la frase se ajusta sola.
     */
    public static function hasta(CarbonInterface $fechaDeEnvio, CarbonInterface $fechaDelHecho): self
    {
        $dias = MotorDeCalculo::diasDeAnticipacion($fechaDeEnvio, $fechaDelHecho);

        // Ninguna de las once alertas habla del pasado en relativo: los
        // resúmenes mensuales de contratos vencidos escriben la fecha de
        // vencimiento y no «hace dos días». Redactar eso sería inventar un
        // texto que no está en ninguna plantilla, así que se avisa fuerte.
        if ($dias < 0) {
            throw new \InvalidArgumentException(
                'El hecho del '.$fechaDelHecho->toDateString().' es anterior al envío del '
                .$fechaDeEnvio->toDateString().': ninguna alerta habla del pasado en relativo.'
            );
        }

        return new self($dias, Carbon::parse($fechaDelHecho->toDateString()));
    }

    /**
     * La frase para meter en mitad de una oración: «termina en dos días
     * (21 de agosto)».
     */
    public function frase(): string
    {
        return $this->referencia().' ('.Formato::diaYMes($this->fecha).')';
    }

    /**
     * La misma frase cuando abre la oración: «Mañana (21 de agosto) es el
     * último día del periodo de prueba».
     */
    public function fraseCapitalizada(): string
    {
        return Str::ucfirst($this->frase());
    }

    private function referencia(): string
    {
        return match ($this->dias) {
            0 => 'hoy',
            1 => 'mañana',
            default => 'en '.(self::EN_LETRAS[$this->dias] ?? (string) $this->dias).' días',
        };
    }
}
