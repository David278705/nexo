<?php

namespace App\Inicio;

/**
 * Una tarjeta del bloque de acción del panel de inicio (documento 3, 5.2).
 *
 * Una tarjeta de acción **es algo que hay que hacer**, y lo que la distingue de
 * un indicador es que lleva a una lista filtrada donde se puede actuar. Por eso
 * el enlace no es opcional: una tarjeta sin destino sería un indicador con
 * ínfulas, y mezclar las dos cosas es «el error más común en este tipo de
 * paneles».
 *
 * Los `extras` son las cifras acumuladas que acompañan a la principal —«7 en 60
 * días · 9 en 90 días»—, cada una con su propio destino. Van aparte del enlace
 * principal porque un enlace dentro de otro no existe en HTML, y porque el
 * capítulo 12 pide las tres cifras enlazando cada una a lo suyo.
 */
final readonly class TarjetaDeAccion
{
    /**
     * @param  list<array{texto: string, enlace: string}>  $extras
     */
    private function __construct(
        public int $valor,
        public string $etiqueta,
        public string $enlace,
        public string $accion,
        public string $tono,
        public array $extras,
    ) {}

    /**
     * @param  list<array{texto: string, enlace: string}>  $extras
     */
    public static function de(
        int $valor,
        string $etiqueta,
        string $enlace,
        string $accion,
        string $tono = '',
        array $extras = [],
    ): self {
        return new self($valor, $etiqueta, $enlace, $accion, $tono, $extras);
    }

    /**
     * @return array{valor: int, etiqueta: string, enlace: string, accion: string, tono: string, extras: list<array{texto: string, enlace: string}>}
     */
    public function paraLaPantalla(): array
    {
        return [
            'valor' => $this->valor,
            'etiqueta' => $this->etiqueta,
            'enlace' => $this->enlace,
            'accion' => $this->accion,
            'tono' => $this->tono,
            'extras' => $this->extras,
        ];
    }
}
