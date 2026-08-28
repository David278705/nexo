<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `invitaciones` gana borrado lógico junto con `users` y `accesos` (REV-10).
 * Mismo motivo que `accesos`: hoy nada la borra, pero la tabla no debe seguir
 * siendo una excepción no declarada a la regla 4 del proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitaciones', function (Blueprint $tabla) {
            $tabla->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('invitaciones', function (Blueprint $tabla) {
            $tabla->dropSoftDeletes();
        });
    }
};
