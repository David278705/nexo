<?php

namespace App\Presentacion;

use Illuminate\Support\Carbon;

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
     * «1 día» o «26 días», con el singular donde corresponde.
     */
    public static function dias(int $cantidad): string
    {
        return $cantidad.($cantidad === 1 ? ' día' : ' días');
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
