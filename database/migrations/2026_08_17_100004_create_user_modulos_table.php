<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los módulos asignados a cada usuario administrador.
 *
 * Sigue el patrón de `database/stubs/migracion-tabla-de-negocio.stub`: lleva
 * `tenant_id`, índice con la empresa primero, borrado lógico y su política de
 * RLS en esta misma migración.
 *
 * Es una tabla de negocio aunque parezca de sistema: quién puede ver qué es
 * información de la empresa, y quitar un permiso tiene que quedar registrado
 * igual que cualquier otro cambio.
 *
 * El índice único es **parcial**: con borrado lógico, un módulo revocado deja
 * su fila, y sin el `where deleted_at is null` no se podría volver a conceder
 * nunca. PostgreSQL lo permite y es exactamente el caso para el que existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_modulos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('user_id')->constrained('users');
            $tabla->string('modulo');

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'user_id']);
        });

        DB::statement(
            'create unique index user_modulos_vigentes_unique
                on user_modulos (tenant_id, user_id, modulo)
                where deleted_at is null'
        );

        PoliticaRls::activarEn('user_modulos');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('user_modulos');

        Schema::dropIfExists('user_modulos');
    }
};
