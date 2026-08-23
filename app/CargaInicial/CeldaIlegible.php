<?php

namespace App\CargaInicial;

use RuntimeException;

/**
 * Una celda que no se pudo convertir en el valor que su columna necesita: una
 * fecha que no es una fecha, un salario con letras, una EPS que no está en el
 * catálogo.
 *
 * Es una excepción y no un valor de retorno por una razón práctica: la lectura
 * de una celda tiene un solo resultado bueno y muchos malos, y devolver
 * `null` para los malos obligaría a distinguir después entre «no se pudo leer»
 * y «la celda estaba vacía», que son cosas muy distintas —la segunda la juzgan
 * las reglas de la plantilla, que saben si esa columna era obligatoria—.
 *
 * Nunca sale de la simulación: `SimulacionDeCarga` la atrapa y la convierte en
 * un `ErrorDeCarga` con su número de fila. El motivo que trae es el que va a
 * leer una persona para corregir el archivo, así que se escribe para ella.
 */
final class CeldaIlegible extends RuntimeException {}
