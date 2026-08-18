<?php

namespace Tests\Fixtures;

use App\Multiempresa\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Un modelo de negocio cualquiera, con el trait y nada más.
 *
 * Existe para poder probar el aislamiento sin esperar a que haya empleados o
 * contratos: lo que se prueba es el trait, no el módulo. Su tabla la crea la
 * propia prueba.
 */
class RegistroDePrueba extends Model
{
    use PerteneceATenant;

    protected $table = 'registros_de_prueba';

    /** @var list<string> */
    protected $fillable = ['titulo'];
}
