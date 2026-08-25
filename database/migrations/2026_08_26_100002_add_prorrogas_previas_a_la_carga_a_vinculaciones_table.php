<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El desplace de prórrogas que trae la carga inicial (REV-09).
 *
 * `PlantillaDeEmpleados` pregunta cuántas prórrogas ya llevaba el contrato
 * antes de cargarse, y la respuesta no puede convertirse en tramos de
 * `periodos_contractuales` porque solo trae un conteo, no la fecha ni el tipo
 * de cada una: reconstruirlos sería inventar registros (documento 1, 13.1).
 *
 * Esta columna guarda ese conteo aparte, sin tocar el historial visible.
 * `HistorialDeContrato::prorrogasCortas()` la suma al conteo que hace sobre
 * los tramos de verdad, que es lo único que necesitaba el cupo de prórrogas
 * cortas del artículo 46. Cero en toda vinculación registrada por pantalla:
 * ahí no hay nada anterior a la carga que sumar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinculaciones', function (Blueprint $tabla) {
            $tabla->unsignedInteger('prorrogas_previas_a_la_carga')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('vinculaciones', function (Blueprint $tabla) {
            $tabla->dropColumn('prorrogas_previas_a_la_carga');
        });
    }
};
