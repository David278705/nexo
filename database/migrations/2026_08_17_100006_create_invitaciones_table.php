<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las invitaciones de primer ingreso.
 *
 * El superadministrador crea el usuario y el sistema le manda un enlace para
 * que defina su contraseña. Hasta que lo use, la cuenta existe pero no tiene
 * contraseña utilizable.
 *
 * **No lleva `tenant_id` ni política de RLS, a propósito.** El enlace se abre
 * sin sesión iniciada, así que en esa petición no hay empresa en contexto y una
 * política de aislamiento haría que la invitación no se encontrara nunca. La
 * empresa se sabe igualmente por el usuario invitado, que es de quien cuelga.
 *
 * El token se guarda con hash, como una contraseña: quien lea la tabla no puede
 * fabricar el enlace de nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('user_id')->constrained('users');
            $tabla->string('token')->unique();
            $tabla->timestamp('expira_en');
            $tabla->timestamp('aceptada_en')->nullable();

            $tabla->timestamps();

            $tabla->index(['user_id', 'aceptada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitaciones');
    }
};
