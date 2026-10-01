<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las dos constancias de la salida de un cliente (documento 6, A.4): cuándo se
 * le exportó toda su información y cuándo se eliminó.
 *
 * Viven en la empresa y no en una tabla aparte porque son dos hechos de la
 * empresa misma, y porque así cambiarlas deja su línea de auditoría sola, por
 * el observador, como todo lo demás.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->timestamp('datos_exportados_en')->nullable();
            $tabla->timestamp('datos_eliminados_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->dropColumn(['datos_exportados_en', 'datos_eliminados_en']);
        });
    }
};
