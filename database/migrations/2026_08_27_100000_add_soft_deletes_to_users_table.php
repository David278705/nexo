<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La deuda abierta desde F1-13: `users` no tenía borrado lógico.
 *
 * El documento 3, sección 5.7, es explícito: «Un usuario se desactiva, no se
 * borra». Sin esta columna, la única forma de quitarle el acceso a alguien
 * era borrar la fila de verdad —lo que la regla 4 del proyecto prohíbe— o
 * dejarlo activo para siempre. Con ella, «desactivar» es un borrado lógico
 * como el de cualquier otra tabla: la fila se conserva, y con ella las líneas
 * de auditoría que ese usuario escribió mientras tuvo acceso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropSoftDeletes();
        });
    }
};
