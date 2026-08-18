<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El calendario de festivos. Capítulo 10 del documento 1 y sección 4.3 del 2.
 *
 * Es la tabla de la que dependen el cálculo de vacaciones, el de licencias y el
 * corrimiento de todas las alertas al día hábil anterior. Un error aquí afecta
 * a todos los clientes a la vez, así que:
 *
 * - **Los festivos no se calculan con fórmulas.** Se cargan año por año y se
 *   verifican a mano. En Colombia varios se trasladan al lunes siguiente y las
 *   excepciones bastan para que cualquier fórmula falle tarde o temprano.
 * - **Se cargan también los que caen en sábado.** Para las empresas que tienen
 *   el sábado como día laboral, un festivo en sábado sí cambia el cálculo.
 *   Omitirlos daría cuentas equivocadas justo en ese grupo de clientes.
 * - Es tabla única del sistema, no por empresa: lo que varía por empresa es si
 *   el sábado es laboral. Por eso es catálogo global, sin `tenant_id`.
 *
 * `fecha` es única: lo que le importa al motor de cálculo es si un día es
 * festivo o no, no cuántas celebraciones caen en él. Cuando dos coinciden
 * —el 30 de junio de 2025, San Pedro y el Sagrado Corazón— van en una sola
 * fila con los dos nombres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festivos', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->date('fecha')->unique();
            $tabla->string('nombre');

            // Redundante con la fecha, y a propósito: el proceso que avisa si
            // falta cargar el año siguiente y la pantalla del panel consultan
            // por año, y agrupar por una columna indexada es más honesto que
            // envolver la fecha en una función en cada consulta.
            $tabla->smallInteger('anio');

            $tabla->timestamps();

            $tabla->index('anio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festivos');
    }
};
