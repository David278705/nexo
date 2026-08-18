<?php

namespace App\Models;

/**
 * Un fondo de pensión.
 *
 * Toda su forma y su comportamiento están en `Catalogo`: las cinco listas de
 * entidades son iguales y se administran igual.
 */
class FondoPension extends Catalogo
{
    protected $table = 'fondos_pension';
}
