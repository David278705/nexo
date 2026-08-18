<?php

use App\Autorizacion\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El rol del usuario: `owner`, `superadmin` o `admin`.
 *
 * El valor por defecto es el más limitado de los tres a propósito. Si alguna
 * vez se crea un usuario sin decir su rol —una carga, un seeder a medias, un
 * formulario incompleto—, el error debe ser que la persona no ve algo, nunca
 * que lo ve todo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->string('rol')
                ->default(Rol::Administrador->value)
                ->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropColumn('rol');
        });
    }
};
