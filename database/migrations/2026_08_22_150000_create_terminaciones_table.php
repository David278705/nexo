<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terminaciones (documento 1, capítulo 7).
 *
 * Es el registro que cierra el ciclo de una vinculación: guarda cuándo salió la
 * persona, de qué tipo fue la salida y por qué. Al escribirse, la vinculación
 * pasa a inactiva, y por eso la tabla no lleva ninguna columna de estado: el
 * estado vive donde siempre ha vivido, en `vinculaciones`.
 *
 * **No hay columna de categoría de rotación.** La sección 7.4 dice que el
 * usuario no la digita, la deduce el sistema a partir del tipo de salida:
 * guardarla sería guardar algo calculable, que es lo que prohíbe la regla 2.
 *
 * `tipo_salida` guarda el texto legible de `TipoDeSalida` y `motivo` la frase
 * del catálogo de la sección 7.3, nunca un número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('vinculacion_id')->constrained('vinculaciones');

            // renuncia · mutuo_acuerdo · periodo_de_prueba ·
            // despido_con_justa_causa · sin_justa_causa · expiracion_del_plazo ·
            // obra_terminada.
            $tabla->string('tipo_salida');

            $tabla->string('motivo');
            $tabla->date('fecha_salida');
            $tabla->text('observaciones')->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'vinculacion_id']);
            $tabla->index(['tenant_id', 'fecha_salida']);
        });

        PoliticaRls::activarEn('terminaciones');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('terminaciones');

        Schema::dropIfExists('terminaciones');
    }
};
