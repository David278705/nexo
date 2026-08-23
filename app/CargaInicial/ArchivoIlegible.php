<?php

namespace App\CargaInicial;

use RuntimeException;

/**
 * El archivo entero no se pudo leer: no existe, no es una hoja de cálculo, o no
 * trae la hoja de la plantilla que se está simulando.
 *
 * Es distinto de un error de fila. Un error de fila se corrige escribiendo bien
 * una celda y el informe lo dice; esto significa que **no hay informe posible**
 * porque no se encontró nada que leer, y quien lo recibe tiene que volver a
 * subir otro archivo.
 *
 * Que falte una columna no llega aquí: eso sí produce informe, con la columna
 * ausente señalada en la fila 1, que es donde vive el título que falta.
 */
final class ArchivoIlegible extends RuntimeException {}
