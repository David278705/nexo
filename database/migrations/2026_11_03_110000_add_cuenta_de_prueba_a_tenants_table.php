<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una cuenta de prueba propia para cada prospecto (`PROD-17`).
 *
 * Decidido por David el 2026-11-03. Hasta ahora todos los accesos de prueba
 * entraban a la única empresa del ambiente de demostración con el mismo usuario,
 * y dos prospectos explorando a la vez veían lo que el otro cambiaba. Ahora cada
 * acceso crea su propia empresa ficticia, poblada con la misma planta de
 * muestra.
 *
 * Es una columna aparte de `es_ambiente_demo` y no una segunda empresa con esa
 * marca: el índice único `tenants_un_solo_ambiente_demo` sigue garantizando que
 * la demostración compartida —la de la sesión guiada de Angela, que se restaura
 * cada noche— sea una sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->boolean('es_cuenta_de_prueba')->default(false)->after('es_ambiente_demo');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->dropColumn('es_cuenta_de_prueba');
        });
    }
};
