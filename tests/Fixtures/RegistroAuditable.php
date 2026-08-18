<?php

namespace Tests\Fixtures;

use App\Multiempresa\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un modelo de negocio con borrado lógico, para probar la auditoría entera.
 *
 * Se separa de `RegistroDePrueba` porque aquel no tiene `deleted_at` y aquí
 * hacen falta las cuatro acciones: crear, editar, eliminar y restaurar. Su
 * tabla la crea la propia prueba.
 */
class RegistroAuditable extends Model
{
    use PerteneceATenant, SoftDeletes;

    protected $table = 'registros_auditables';

    /** @var list<string> */
    protected $fillable = ['titulo', 'salario'];
}
