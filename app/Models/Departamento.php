<?php

namespace App\Models;

use Database\Factories\DepartamentoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un departamento de Colombia. Catálogo global, sin `tenant_id` ni trait.
 *
 * Es el primer eslabón de las listas encadenadas de la regla 5.1.1: al elegirlo
 * se cargan sus ciudades. Bogotá D.C. entra aquí como un departamento más, con
 * su código 11 y una sola ciudad, porque en el formulario se comporta igual.
 *
 * @property-read Collection<int, Ciudad> $ciudades
 */
class Departamento extends Model
{
    /** @use HasFactory<DepartamentoFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['codigo_dane', 'nombre', 'activo'];

    /**
     * @return HasMany<Ciudad, $this>
     */
    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudad::class);
    }

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
