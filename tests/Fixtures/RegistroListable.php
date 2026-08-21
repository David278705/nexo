<?php

namespace Tests\Fixtures;

use App\Multiempresa\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Un modelo de negocio con dos columnas, para probar el armazón de listado.
 *
 * Se separa de `RegistroDePrueba` porque hacen falta dos columnas: una de texto
 * para la búsqueda y una de catálogo cerrado para el filtro de lista. Su tabla
 * la crea la propia prueba.
 */
class RegistroListable extends Model
{
    use PerteneceATenant;

    protected $table = 'registros_listables';

    /** @var list<string> */
    protected $fillable = ['titulo', 'estado'];
}
