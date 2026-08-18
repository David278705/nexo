<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La forma común de las cinco listas de entidades: EPS, fondos de pensión,
 * ARL, cajas de compensación y bancos.
 *
 * Las cinco son la misma tabla con distinto nombre —código, nombre y si está
 * activa—, así que comparten modelo en vez de repetirlo cinco veces. Lo único
 * que cada una declara es su tabla.
 *
 * Ninguna usa `PerteneceATenant`: son catálogos globales del sistema, iguales
 * para todos los clientes. No llevan `tenant_id` ni política de RLS.
 *
 * Una entidad no se borra nunca de verdad. Cuando una EPS se liquida se
 * desactiva: deja de ofrecerse en las listas y sigue leyéndose en los empleados
 * que ya la tenían.
 */
abstract class Catalogo extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['codigo', 'nombre', 'activo'];

    /**
     * Lo que se ofrece hoy en una lista desplegable, en orden alfabético.
     *
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
