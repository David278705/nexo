<?php

namespace App\Models;

use Database\Factories\CiudadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un municipio de Colombia. Catálogo global, sin `tenant_id` ni trait.
 *
 * Es el segundo eslabón de las listas encadenadas: nunca se ofrece la lista
 * entera de los mil cien municipios, siempre los del departamento elegido.
 *
 * @property-read Departamento $departamento
 */
class Ciudad extends Model
{
    /** @use HasFactory<CiudadFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'ciudades';

    /** @var list<string> */
    protected $fillable = ['departamento_id', 'codigo_dane', 'nombre', 'activo'];

    /**
     * @return BelongsTo<Departamento, $this>
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
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
