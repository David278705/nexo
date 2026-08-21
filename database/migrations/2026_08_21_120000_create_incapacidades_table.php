<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La primera de las tres novedades (documento 1, 6.3).
 *
 * Los días no se guardan: son días corridos entre `fecha_inicio` y `fecha_fin`,
 * y eso lo calcula `MotorDeCalculo::diasCorridos()` cada vez, nunca una columna
 * que se pueda desincronizar de las dos fechas.
 *
 * `codigo_diagnostico` es el dato sensible del recuadro de la sección 6.3: se
 * guarda tal cual, y quien lo puede ver lo decide el permiso del módulo
 * —`modulo:incapacidades`, el mismo que protege toda esta tabla— y no una
 * columna aparte. La única fuga posible fuera de esa puerta es la auditoría, y
 * por eso el observador la trata como columna oculta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incapacidades', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('vinculacion_id')->constrained('vinculaciones');

            $tabla->date('fecha_inicio');
            $tabla->date('fecha_fin');

            $tabla->string('codigo_diagnostico');
            $tabla->boolean('es_prorroga')->default(false);

            $tabla->foreignId('eps_id')->constrained('eps');

            // comun · laboral. Texto legible, no número.
            $tabla->string('origen');

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'vinculacion_id']);
            $tabla->index(['tenant_id', 'fecha_inicio']);
        });

        PoliticaRls::activarEn('incapacidades');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('incapacidades');

        Schema::dropIfExists('incapacidades');
    }
};
