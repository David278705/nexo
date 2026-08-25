<?php

namespace App\Models;

use App\Auditoria\AccionAuditada;
use App\Auditoria\AuditoriaInmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de la auditoría. Se escribe una vez y no se toca nunca más.
 *
 * No usa `PerteneceATenant`: la línea se escribe también cuando no hay empresa
 * en contexto, y el trait lo impediría. El porqué completo está en la
 * migración. Para leerla filtrada por empresa está `deLaEmpresaActual()`.
 *
 * @property AccionAuditada $accion
 * @property array<string, mixed>|null $valores_anteriores
 * @property array<string, mixed>|null $valores_nuevos
 * @property-read User|null $usuario
 */
class Auditoria extends Model
{
    /**
     * Solo hay fecha de creación: una línea de auditoría no se actualiza jamás.
     */
    public const UPDATED_AT = null;

    protected $table = 'auditoria';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'modelo',
        'registro_id',
        'accion',
        'valores_anteriores',
        'valores_nuevos',
        'ip',
        'agente',
    ];

    /**
     * La inmutabilidad, cumplida por la aplicación.
     *
     * El documento pide que la tabla no admita modificación ni borrado desde la
     * aplicación, «ni siquiera el superadministrador». Ponerlo aquí y no en cada
     * controlador es la única forma de que no haya un camino olvidado: cualquier
     * `update()` o `delete()` sobre este modelo, venga de donde venga, revienta.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw AuditoriaInmutable::alEditar();
        });

        static::deleting(function (): void {
            throw AuditoriaInmutable::alBorrar();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accion' => AccionAuditada::class,
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * La auditoría que le corresponde ver a la empresa en curso.
     *
     * Es el único camino admitido para leerla desde el entorno del cliente. La
     * tabla no lleva global scope —no puede, porque también guarda líneas sin
     * empresa—, así que el filtro va aquí, en un solo sitio, y no repartido por
     * los controladores.
     *
     * @param  Builder<$this>  $consulta
     * @return Builder<$this>
     */
    public function scopeDeLaEmpresaActual(Builder $consulta): Builder
    {
        // Sin empresa en contexto no se devuelve nada, igual que hace el global
        // scope de `PerteneceATenant`: fallar cerrado, nunca abierto.
        return $consulta->where('tenant_id', tenant_actual_id() ?? 0);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
