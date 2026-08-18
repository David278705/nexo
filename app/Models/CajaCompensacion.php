<?php

namespace App\Models;

/**
 * Una caja de compensación familiar. Varían por departamento.
 *
 * Toda su forma y su comportamiento están en `Catalogo`: las cinco listas de
 * entidades son iguales y se administran igual.
 */
class CajaCompensacion extends Catalogo
{
    protected $table = 'cajas_compensacion';
}
