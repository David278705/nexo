<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El historial del contrato, tramo a tramo (documento 2, 3.3).
 *
 * Cada fila es un tramo de la vida del contrato y **la primera es el contrato
 * original**, no la primera prórroga: el historial que ve el usuario incluye el
 * periodo inicial, que era un requerimiento expreso (documento 1, 5.5).
 *
 * ## Por qué esta tabla y no unas columnas más en `vinculaciones`
 *
 * Una prórroga no genera una vinculación nueva —no hay salida de la persona ni
 * interrupción del vínculo—, así que el vínculo es uno solo y sus tramos son
 * varios. `vinculaciones.tipo_contrato` y `vinculaciones.fecha_fin` siguen
 * guardando **lo vigente hoy**, que es lo que se ve en los listados; el tramo a
 * tramo vive aquí. Cuando F1-09 construya «Renovar», tiene que mover las dos
 * cosas a la vez.
 *
 * ## Lo que se calcula sobre esta tabla
 *
 * Prórrogas acumuladas, duración total del vínculo a término fijo, fecha de
 * vencimiento vigente y el historial que se muestra en pantalla. Todo eso vive
 * en `App\Contratacion\HistorialDeContrato`, no en las consultas de cada
 * pantalla.
 *
 * ## El orden
 *
 * `orden` es 1 para el periodo inicial y 2 en adelante para cada movimiento
 * posterior. Es único por vinculación entre las filas vivas: dos tramos con el
 * mismo número harían ambiguo cuál es el vigente, y el vigente es el que
 * determina la fecha de vencimiento de la que dependen las alertas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_contractuales', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('vinculacion_id')->constrained('vinculaciones');

            $tabla->unsignedInteger('orden');

            // inicial · prorroga_pactada · prorroga_automatica ·
            // cambio_a_indefinido. Texto legible, no número: un reporte
            // exportado tiene que leerse sin diccionario.
            $tabla->string('evento');

            // La modalidad vigente en ese tramo. Permite ver el momento exacto
            // en que un contrato pasó de fijo a indefinido.
            $tabla->string('tipo_contrato');

            $tabla->date('fecha_inicio');

            // Vacía en indefinido y en obra o labor, igual que en la
            // vinculación (documento 1, 5.4).
            $tabla->date('fecha_fin')->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            // El historial de un contrato, que es la consulta de esta tabla.
            $tabla->index(['tenant_id', 'vinculacion_id', 'orden']);
        });

        // Un solo tramo por número dentro de cada vinculación. Parcial, como la
        // del documento en `personas`: el borrado de este sistema es lógico y
        // sin el `where` un tramo eliminado reservaría su número para siempre.
        DB::statement(
            'create unique index periodos_orden_por_vinculacion
                on periodos_contractuales (tenant_id, vinculacion_id, orden)
                where deleted_at is null'
        );

        PoliticaRls::activarEn('periodos_contractuales');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('periodos_contractuales');

        Schema::dropIfExists('periodos_contractuales');
    }
};
