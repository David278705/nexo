<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La leyenda «reclamo en trámite» (`PROD-14`).
 *
 * La Ley 1581 de 2012, artículo 18, literal g, obliga al encargado del
 * tratamiento a «registrar en la base de datos la leyenda "reclamo en
 * trámite"» mientras se decide el reclamo de un titular, y el artículo 15 da
 * dos días hábiles para ponerla. El sistema no tenía dónde.
 *
 * Va en `personas` porque el titular del dato es la persona, no una de sus
 * vinculaciones: un reclamo sobre sus datos vale para todas. `personas` ya
 * tiene su RLS, y quién la pone y quién la quita queda en la auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->timestamp('reclamo_en_tramite_desde')->nullable();
            $tabla->text('reclamo_en_tramite_motivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->dropColumn(['reclamo_en_tramite_desde', 'reclamo_en_tramite_motivo']);
        });
    }
};
