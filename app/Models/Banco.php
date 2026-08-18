<?php

namespace App\Models;

/**
 * Una entidad bancaria. Del empleado se guarda solo la entidad, nunca el número
 * de cuenta (documento 1, sección 5.1).
 *
 * Toda su forma y su comportamiento están en `Catalogo`: las cinco listas de
 * entidades son iguales y se administran igual.
 */
class Banco extends Catalogo
{
    protected $table = 'bancos';
}
