<?php

namespace App\Presentacion;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Cómo se escriben las fechas, las cantidades de días y el dinero en pantalla.
 *
 * Antes de existir, `fecha()` estaba copiada palabra por palabra en cinco
 * clases y `dias()` en dos. Eran idénticas, así que nadie notaba la
 * duplicación; el problema aparece el día en que una cambia y las otras no, y
 * entonces la misma fecha se lee distinto en el listado y en la ficha.
 *
 * Son textos de presentación, no cálculos. Aquí no se cuenta ni un día: eso es
 * del motor de cálculo (F2-02).
 */
final class Formato
{
    /**
     * La raya que ocupa el lugar de un dato que no existe.
     *
     * Un espacio en blanco deja al que mira sin saber si el dato falta o si la
     * pantalla se rompió.
     */
    public const VACIO = '—';

    /**
     * Una fecha como se lee en Colombia: 25/12/2026.
     *
     * Acepta `mixed` porque casi siempre llega de un atributo de modelo, que
     * para el analizador estático puede ser cualquier cosa. Lo que no sea una
     * fecha sale como raya, nunca como texto raro.
     */
    public static function fecha(mixed $fecha): string
    {
        return $fecha instanceof Carbon ? $fecha->format('d/m/Y') : self::VACIO;
    }

    /**
     * Una fecha con su hora: 25/12/2026, 6:04 a. m.
     *
     * Es la única de la casa que lleva hora, y por un motivo concreto: las
     * fechas laborales del sistema son `date` sin hora a propósito, pero el
     * registro de envíos (documento 2, 5.5) tiene que decir **cuándo** salió el
     * correo, y «el 25 de diciembre» no responde a un cliente que dice que a esa
     * hora no le había llegado nada.
     */
    public static function fechaYHora(mixed $fecha): string
    {
        return $fecha instanceof Carbon
            ? $fecha->format('d/m/Y').', '.$fecha->translatedFormat('g:i a')
            : self::VACIO;
    }

    /**
     * La misma fecha escrita con letras: 25 de diciembre de 2026.
     *
     * Se usa dentro de una frase, donde `25/12/2026` se lee como un tropiezo.
     * Va en español porque el idioma de la aplicación está fijado en `es`.
     */
    public static function fechaLarga(mixed $fecha): string
    {
        return $fecha instanceof Carbon
            ? $fecha->translatedFormat('j \d\e F \d\e Y')
            : self::VACIO;
    }

    /**
     * El día y el mes sin el año: «28 de agosto».
     *
     * Es la advertencia de saldo insuficiente de vacaciones (documento 3, 5.5):
     * «Al 28 de agosto tendrá...» no lleva año porque la fecha de disfrute
     * siempre está a meses de hoy, nunca a años.
     */
    public static function diaYMes(mixed $fecha): string
    {
        return $fecha instanceof Carbon
            ? $fecha->translatedFormat('j \d\e F')
            : self::VACIO;
    }

    /**
     * Un periodo escrito como se lee en una frase: «del 14 al 16 de julio»
     * cuando las dos fechas caen en el mismo mes, y «del 28 de julio al 3 de
     * agosto» cuando no.
     *
     * Es la descripción del registro que llevan las confirmaciones de borrado
     * (documento 3, 6.3): «Vas a eliminar la incapacidad del 14 al 16 de julio
     * de Ana Torres». Repetir el mes cuando es el mismo hace que la frase se
     * lea como la escribiría un formulario y no una persona.
     */
    public static function periodo(mixed $inicio, mixed $fin): string
    {
        if (! $inicio instanceof Carbon || ! $fin instanceof Carbon) {
            return self::VACIO;
        }

        $mismoMes = $inicio->year === $fin->year && $inicio->month === $fin->month;

        return 'del '.($mismoMes ? (string) $inicio->day : self::diaYMes($inicio))
            .' al '.self::diaYMes($fin);
    }

    /**
     * El día con su nombre delante: «Lunes, 17 de agosto de 2026».
     *
     * Es la fecha que encabeza las dos pantallas de inicio, la del cliente
     * (documento 3, sección 5.2) y la del panel del proveedor. Se arma en el
     * servidor y no en el navegador para que salga en la zona horaria de la
     * aplicación —`America/Bogota`— y no en la del reloj de quien mira.
     */
    public static function diaCompleto(mixed $fecha): string
    {
        if (! $fecha instanceof Carbon) {
            return self::VACIO;
        }

        return Str::ucfirst($fecha->translatedFormat('l').', '.self::fechaLarga($fecha));
    }

    /**
     * «1 día» o «26 días», con el singular donde corresponde.
     */
    public static function dias(int $cantidad): string
    {
        return $cantidad.($cantidad === 1 ? ' día' : ' días');
    }

    /**
     * «1 minuto», «18 minutos», «1 hora y 5 minutos».
     *
     * Es lo que duró una visita del proveedor al entorno de un cliente
     * (documento 4, 4.4), leída en el historial de su ficha. Va aparte de
     * `tiempoTranscurrido()` porque aquella cuenta meses y años, y una visita de
     * soporte se mide en minutos: pasarle los minutos a la otra escribiría
     * «0 meses».
     */
    public static function minutos(int $cantidad): string
    {
        $cantidad = max(0, $cantidad);
        $horas = intdiv($cantidad, 60);
        $restantes = $cantidad % 60;

        $enMinutos = $restantes === 1 ? '1 minuto' : $restantes.' minutos';

        if ($horas === 0) {
            return $enMinutos;
        }

        $enHoras = $horas === 1 ? '1 hora' : $horas.' horas';

        return $restantes === 0 ? $enHoras : $enHoras.' y '.$enMinutos;
    }

    /**
     * Un tiempo transcurrido como se dice en voz alta: «3 años y 9 meses»,
     * «un año», «9 meses», «un mes».
     *
     * Recibe los meses ya contados —contarlos es del motor,
     * `MotorDeCalculo::mesesCumplidos()`— y solo decide cómo se escriben. Es la
     * antigüedad que nombra la alerta de cercanía al tope de cuatro años
     * (documento 2, 5.4): «El vínculo a término fijo de {Nombre} lleva
     * {tiempo}…».
     *
     * El año y el mes sueltos van con letra —«lleva un año», no «lleva 1 año»—
     * porque así se lee dentro de una frase, que es el único sitio donde se usa.
     */
    public static function tiempoTranscurrido(int $meses): string
    {
        $meses = max(0, $meses);
        $anios = intdiv($meses, 12);
        $restantes = $meses % 12;

        $enAnios = $anios === 1 ? 'un año' : $anios.' años';
        $enMeses = $restantes === 1 ? 'un mes' : $restantes.' meses';

        if ($anios === 0) {
            return $enMeses;
        }

        return $restantes === 0 ? $enAnios : $enAnios.' y '.$enMeses;
    }

    /**
     * Los días que vienen con fracción: «12,75 días», «1,25 días», «3 días».
     *
     * Es el saldo de vacaciones y todo lo que sale del motor con decimales. Va
     * aparte de `dias()` porque esa recibe un entero y la mitad del sistema
     * cuenta en enteros —días corridos, días hábiles, duración de una prórroga—;
     * escribir «6,00 días» de una incapacidad sería peor que no formatear.
     *
     * La coma decimal es la de Colombia, y los decimales solo aparecen cuando
     * los hay: un saldo de trece días redondos se lee «13 días».
     */
    public static function diasDecimales(float $cantidad): string
    {
        return self::numeroDecimal($cantidad).($cantidad === 1.0 ? ' día' : ' días');
    }

    /**
     * El mismo número sin la palabra detrás: «12,75», «1,25», «13».
     *
     * Es la mitad de una frase que ya dice de qué habla: «el saldo pasará de
     * 4,25 a 12,25 días» (documento 3, 2.4). Escribir «días» tres veces en una
     * sola oración la vuelve ilegible, y por eso `diasDecimales()` sale de
     * aquí en lugar de tener su propio `number_format`.
     */
    public static function numeroDecimal(float $cantidad): string
    {
        $decimales = fmod($cantidad, 1.0) === 0.0 ? 0 : 2;

        return number_format($cantidad, $decimales, ',', '.');
    }

    /**
     * El nombre del mes, en minúscula: «agosto».
     *
     * Es como se lee dentro de una frase —«Días disfrutados en agosto»,
     * documento 1, 12.1—, nunca con mayúscula inicial porque ahí no empieza
     * una oración.
     */
    public static function mes(mixed $fecha): string
    {
        return $fecha instanceof Carbon ? $fecha->translatedFormat('F') : self::VACIO;
    }

    /**
     * Un porcentaje como se lee en un indicador: «4,2%», «0,0%».
     *
     * Siempre a un decimal fijo, para que dos índices del mismo panel —rotación
     * y ausentismo— se lean con la misma precisión y no den la impresión de que
     * uno se calculó con más cuidado que el otro.
     */
    public static function porcentaje(float $valor): string
    {
        return number_format($valor, 1, ',', '.').'%';
    }

    /**
     * El dinero como se lee en Colombia: punto de miles y coma decimal, y sin
     * centavos cuando no los hay, que es casi siempre.
     */
    public static function dinero(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return self::VACIO;
        }

        $numero = (float) $valor;
        $decimales = fmod($numero, 1.0) === 0.0 ? 0 : 2;

        return '$ '.number_format($numero, $decimales, ',', '.');
    }
}
