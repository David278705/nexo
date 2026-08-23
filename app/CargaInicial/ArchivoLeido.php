<?php

namespace App\CargaInicial;

/**
 * Lo que `LectorDeArchivo` saca de un archivo: sus filas con datos y los
 * títulos de columna que la plantilla esperaba y no encontró.
 *
 * Los títulos ausentes viajan aparte de las filas porque no son un error de
 * nadie que digitó: son un archivo con la estructura cambiada, y las filas que
 * traiga se leen igual —lo que falte saldrá como campo vacío— para que el
 * informe enseñe de una vez todo lo que hay que arreglar.
 */
final class ArchivoLeido
{
    /**
     * @param  list<FilaDelArchivo>  $filas
     * @param  list<string>  $titulosQueFaltan
     */
    public function __construct(
        public readonly array $filas,
        public readonly array $titulosQueFaltan,
    ) {}
}
