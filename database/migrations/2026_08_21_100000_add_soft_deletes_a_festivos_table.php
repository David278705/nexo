<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado lógico para los festivos (tarea F1B-04).
 *
 * La tabla nació sin `deleted_at` porque hasta ahora nadie podía tocarla: se
 * cargaba con un seeder y se leía. Desde que el panel del proveedor administra
 * el calendario, quitar un festivo mal cargado es una acción del producto, y la
 * regla 4 del proyecto no admite excepciones: **nunca borrado físico**. Un
 * festivo que se quita cambia el conteo de días hábiles de todas las empresas a
 * la vez, así que tiene que quedar rastro de quién lo quitó y cuándo.
 *
 * Las cinco listas de entidades y las tres de geografía ya lo tenían. Esta era
 * la única tabla de catálogo que no.
 *
 * `fecha` sigue siendo única, e incluye a los festivos quitados: volver a
 * cargar una fecha que ya estuvo **restaura la fila anterior** en vez de crear
 * una segunda. Lo resuelve `Festivo::registrar()`, que es el único camino por el
 * que se escribe esta tabla desde la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('festivos', function (Blueprint $tabla) {
            $tabla->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('festivos', function (Blueprint $tabla) {
            $tabla->dropSoftDeletes();
        });
    }
};
