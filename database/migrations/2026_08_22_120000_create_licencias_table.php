<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tercera de las tres novedades (documento 1, 6.5): licencia no
 * remunerada, ausencia y suspensión son un solo tipo de novedad con un campo
 * de clasificación, no tres tablas.
 *
 * Los días no se guardan: son días corridos entre `fecha_inicio` y
 * `fecha_fin` —domingos y festivos incluidos, la misma regla que
 * incapacidades y distinta de vacaciones—, y eso lo calcula
 * `MotorDeCalculo::diasCorridos()` cada vez.
 *
 * `tipo` guarda el texto legible de `TipoDeLicencia`, nunca un número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licencias', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('vinculacion_id')->constrained('vinculaciones');

            // licencia_no_remunerada · ausencia · suspension.
            $tabla->string('tipo');

            $tabla->date('fecha_inicio');
            $tabla->date('fecha_fin');

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'vinculacion_id']);
            $tabla->index(['tenant_id', 'fecha_inicio']);
        });

        PoliticaRls::activarEn('licencias');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('licencias');

        Schema::dropIfExists('licencias');
    }
};
