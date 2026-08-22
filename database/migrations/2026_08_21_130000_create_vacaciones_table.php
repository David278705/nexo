<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La segunda de las tres novedades (documento 1, 6.4), y la primera que
 * descuenta del saldo.
 *
 * Los días no se guardan: son días hábiles entre `fecha_inicio` y
 * `fecha_fin`, y eso lo calcula `MotorDeCalculo::diasHabilesVacaciones()`
 * cada vez —regla 2 del proyecto—. `SaldoDeVacaciones::al()` resta la suma de
 * esos días de aquí, así que eliminar una fila (borrado lógico) restituye el
 * saldo sin ninguna operación inversa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('vinculacion_id')->constrained('vinculaciones');

            $tabla->date('fecha_inicio');
            $tabla->date('fecha_fin');

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'vinculacion_id']);
            $tabla->index(['tenant_id', 'fecha_inicio']);
        });

        PoliticaRls::activarEn('vacaciones');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('vacaciones');

        Schema::dropIfExists('vacaciones');
    }
};
