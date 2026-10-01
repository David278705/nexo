<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La constancia de que una empresa aceptó un documento legal: cuál, en qué
 * versión, quién lo aceptó a su nombre, cuándo y desde dónde.
 *
 * Es la prueba de un acuerdo, así que se escribe una vez y no se edita: si el
 * documento cambia, la versión nueva es otra fila. El índice único hace que
 * dos clics seguidos en «Acepto» no dejen dos constancias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aceptaciones_legales', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('user_id')->constrained('users');

            $tabla->string('documento');
            $tabla->string('version');

            $tabla->string('ip', 45)->nullable();
            $tabla->string('agente', 500)->nullable();
            $tabla->timestamp('aceptado_en');

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->unique(['tenant_id', 'documento', 'version']);
        });

        PoliticaRls::activarEn('aceptaciones_legales');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('aceptaciones_legales');
        Schema::dropIfExists('aceptaciones_legales');
    }
};
