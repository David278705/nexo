<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un nivel de licencia del catálogo de planes (documento 4, capítulo 6).
 *
 * No usa `PerteneceATenant`: es una tabla global, como los catálogos. Un plan no
 * es de ninguna empresa, es lo que el proveedor ofrece a todas.
 *
 * @property string $nombre
 * @property float $precio_mensual
 * @property int $empleados_incluidos
 * @property int $usuarios_incluidos
 * @property bool $activo
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'planes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'precio_mensual',
        'empleados_incluidos',
        'usuarios_incluidos',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'empleados_incluidos' => 'integer',
            'usuarios_incluidos' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Suscripcion, $this>
     */
    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    /**
     * Los que se pueden contratar hoy. Un plan desactivado sigue leyéndose en
     * las suscripciones que lo tienen, pero no se ofrece para una nueva.
     *
     * @param  Builder<$this>  $consulta
     * @return Builder<$this>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }

    /**
     * Cómo se lee un plan en una sola línea: «Pequeña · 50 empleados · 2
     * usuarios», que es lo que pide la columna «Plan» del listado de empresas
     * (documento 4, 4.1): «nivel contratado, empleados y usuarios incluidos».
     */
    public function comoSeLee(): string
    {
        return $this->nombre
            .' · '.$this->empleados_incluidos.' empleados'
            .' · '.$this->usuarios_incluidos.' usuarios';
    }
}
