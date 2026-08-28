<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `accesos` gana borrado lógico junto con `users` e `invitaciones` (REV-10):
 * las tres son la deuda que dejó F1-13 abierta.
 *
 * Nada en la aplicación borra hoy una fila de esta tabla —sigue siendo, como
 * dice su migración original, un registro de seguridad que no se edita ni se
 * borra desde ninguna pantalla—. La columna se agrega de todas formas para
 * que esta tabla deje de ser una excepción no declarada a la regla 4 del
 * proyecto («nunca borrado físico»): si algún día hace falta limpiar el
 * historial de accesos de un usuario, existe el camino correcto y no hay que
 * abrir otra tarea solo para añadir la columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accesos', function (Blueprint $tabla) {
            $tabla->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('accesos', function (Blueprint $tabla) {
            $tabla->dropSoftDeletes();
        });
    }
};
