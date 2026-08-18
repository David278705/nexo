<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un país de la lista de nacimiento (documento 1, sección 5.1).
 *
 * Catálogo global: sin `tenant_id`, sin trait y sin política de RLS.
 *
 * Colombia no es un país más en el formulario: es el que decide si el
 * departamento y la ciudad se eligen de listas encadenadas o se digitan a mano.
 * Esa comparación se hace contra `Pais::COLOMBIA`, el código ISO, y no contra
 * el nombre, que se puede escribir de varias maneras.
 */
class Pais extends Model
{
    use SoftDeletes;

    /**
     * El código ISO de Colombia, del que depende la regla 5.1.1.
     */
    public const COLOMBIA = 'CO';

    protected $table = 'paises';

    /** @var list<string> */
    protected $fillable = ['codigo', 'nombre', 'activo'];

    /**
     * @param  Builder<$this>  $consulta
     * @return Builder<$this>
     */
    public function scopeParaElegir(Builder $consulta): Builder
    {
        return $consulta->where('activo', true)->orderBy('nombre');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
