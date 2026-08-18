<?php

namespace App\Multiempresa;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Capa 1 del aislamiento entre empresas.
 *
 * Todo modelo de negocio lo usa. A cambio recibe dos cosas y no tiene que
 * hacer nada más:
 *
 * 1. Un global scope que filtra por la empresa en contexto en toda consulta.
 * 2. El relleno de `tenant_id` al crear.
 *
 * Los catálogos globales —países, ciudades, EPS, fondos, ARL, cajas, bancos,
 * festivos y planes— no lo usan: no pertenecen a ninguna empresa.
 *
 * @property int $tenant_id
 */
trait PerteneceATenant
{
    public static function bootPerteneceATenant(): void
    {
        static::addGlobalScope(new AlcanceTenant);

        static::creating(function (Model $modelo): void {
            // Se asigna siempre, ignorando lo que traiga el modelo. Si se
            // respetara un valor puesto a mano, un formulario con un campo
            // `tenant_id` escondido podría sembrar filas en otra empresa.
            $modelo->setAttribute(
                AlcanceTenant::COLUMNA,
                app(ContextoTenant::class)->idObligatorio($modelo::class)
            );
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, AlcanceTenant::COLUMNA);
    }
}
