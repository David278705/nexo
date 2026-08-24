<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca la empresa ficticia del ambiente de demostración (documento 4,
 * capítulo 10, F5-03).
 *
 * **Es una sola, y el índice único parcial lo hace cumplir.** No hay
 * necesidad de distinguir «cuál» ambiente de demostración: la empresa que
 * Angela enseña en la sesión guiada es siempre la misma, la que restaura el
 * proceso nocturno, y el `owner` la encuentra por esta bandera y no por su
 * nombre ni por su NIT.
 *
 * `demo_restaurado_en` es lo que enseña la pantalla del panel para responder
 * «¿cuándo quedó así de poblada por última vez?». No es la fecha en que se
 * creó la empresa —esa ya la tiene `created_at`— sino la de la última vez que
 * `AmbienteDeDemostracion::restaurar()` la dejó en su estado conocido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->boolean('es_ambiente_demo')->default(false);
            $tabla->timestamp('demo_restaurado_en')->nullable();
        });

        DB::statement(
            'create unique index tenants_un_solo_ambiente_demo
                on tenants (es_ambiente_demo)
                where es_ambiente_demo = true'
        );
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->dropColumn(['es_ambiente_demo', 'demo_restaurado_en']);
        });
    }
};
