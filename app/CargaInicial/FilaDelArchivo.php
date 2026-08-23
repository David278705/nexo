<?php

namespace App\CargaInicial;

/**
 * Una fila del archivo tal como se leyó, antes de interpretar nada: el número
 * de fila del Excel y las celdas indexadas por el título de su columna.
 *
 * El número es el de la barra gris de la hoja, no el orden del registro. Es lo
 * único que hace que el informe de errores sirva para corregir el archivo.
 */
final class FilaDelArchivo
{
    /**
     * @param  array<string, mixed>  $celdas  indexadas por el título de la columna
     */
    public function __construct(
        public readonly int $numero,
        public readonly array $celdas,
    ) {}

    public function celda(string $titulo): mixed
    {
        return $this->celdas[$titulo] ?? null;
    }
}
