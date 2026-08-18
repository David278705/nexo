<?php

use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;

if (! function_exists('tenant_actual')) {
    /**
     * La empresa de la petición en curso, o `null` si no hay ninguna fijada.
     *
     * Es de solo lectura a propósito: para cambiar de empresa está
     * `ContextoTenant::paraTenant()`, que además garantiza la limpieza.
     */
    function tenant_actual(): ?Tenant
    {
        return app(ContextoTenant::class)->tenant();
    }
}

if (! function_exists('tenant_actual_id')) {
    /**
     * El identificador de la empresa en curso, o `null` si no hay ninguna.
     */
    function tenant_actual_id(): ?int
    {
        return app(ContextoTenant::class)->id();
    }
}
