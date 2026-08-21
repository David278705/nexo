<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La ciudad de la empresa deja de ser texto y pasa a ser el municipio del
 * catálogo.
 *
 * Lo dejó anotado la migración que creó la tabla: «la ciudad queda como texto
 * hasta que F0-11 traiga el catálogo de ciudades de Colombia». Ya lo trajo —los
 * 1.101 municipios—, y la pantalla de crear empresa (F1B-02) es la primera que
 * la pregunta. Guardarla como texto libre en el único formulario que la escribe
 * habría dejado «Medellin», «medellín» y «Medellín, Ant.» como tres ciudades.
 *
 * Queda nula porque las empresas creadas antes de esta pantalla no tienen de
 * dónde sacarla, no porque una empresa pueda no estar en ninguna parte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->foreignId('ciudad_id')
                ->nullable()
                ->after('telefono')
                ->constrained('ciudades')
                ->nullOnDelete();

            $tabla->dropColumn('ciudad');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('ciudad_id');
            $tabla->string('ciudad')->nullable()->after('telefono');
        });
    }
};
