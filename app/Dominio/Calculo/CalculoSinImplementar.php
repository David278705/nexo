<?php

namespace App\Dominio\Calculo;

use LogicException;

/**
 * Lo que lanza cada método del motor mientras el motor no existe.
 *
 * Es lo que hace que los diez casos obligatorios de F2-01 fallen **por falta de
 * implementación y no por un error de sintaxis**, que es literalmente lo que esa
 * tarea pide poder demostrar. Cuando F2-02 escriba el cálculo, esta clase se
 * queda sin quien la lance y desaparece.
 */
class CalculoSinImplementar extends LogicException
{
    public static function en(string $metodo): self
    {
        return new self(
            "«{$metodo}» todavía no está implementado. El motor de cálculo es la tarea F2-02; "
            .'F2-01 solo escribe los diez casos obligatorios y la firma de cada función.'
        );
    }
}
