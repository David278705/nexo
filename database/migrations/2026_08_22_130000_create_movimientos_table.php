<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aumentos y promociones (documento 1, 8.1).
 *
 * No es una novedad del capítulo 6 sino un movimiento del 8, y por eso es la
 * primera tabla de este grupo que **guarda el valor anterior**: al guardar, el
 * cargo y el salario del empleado se actualizan en su registro principal, así
 * que la vinculación deja de saber qué había antes. El histórico tiene que
 * poder enseñar «el salario y el cargo anterior frente al nuevo», que es
 * literal de la sección 8.1.
 *
 * Eso no contradice la regla 2 del proyecto. Lo que allí se prohíbe es guardar
 * un saldo que se pueda calcular; aquí no hay nada que calcular: el cargo y el
 * salario que regían el día del movimiento son un hecho, y un hecho se guarda.
 *
 * `tipo` guarda el texto legible de `TipoDeMovimiento`, nunca un número. El
 * dinero va en decimal de precisión fija, igual que `vinculaciones.salario`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('vinculacion_id')->constrained('vinculaciones');

            // aumento_salarial · promocion.
            $tabla->string('tipo');

            $tabla->string('cargo_anterior');
            $tabla->string('cargo_nuevo');
            $tabla->decimal('salario_anterior', 14, 2);
            $tabla->decimal('salario_nuevo', 14, 2);

            $tabla->date('fecha_efectividad');

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'vinculacion_id']);
            $tabla->index(['tenant_id', 'fecha_efectividad']);
        });

        PoliticaRls::activarEn('movimientos');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('movimientos');

        Schema::dropIfExists('movimientos');
    }
};
