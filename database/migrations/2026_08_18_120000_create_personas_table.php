<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La persona: el ser humano, no su contrato.
 *
 * Es la mitad estable de la decisión estructural de la sección 3.2 del
 * documento de arquitectura. Aquí vive lo que le pertenece a la persona y no
 * cambia porque la contraten o la liquiden: documento, nombres, nacimiento,
 * género y residencia. Todo lo que nace y muere con cada contratación —cargo,
 * salario, fechas, EPS, estado— vive en `vinculaciones`.
 *
 * **El documento es único por empresa aquí, no en `vinculaciones`.** Es la
 * regla derivada de 3.2 y el sitio donde se equivoca todo el mundo: si la
 * unicidad estuviera en la vinculación, recontratar a alguien obligaría a
 * duplicar sus datos personales y existirían dos versiones de la misma persona
 * desincronizándose. El índice está más abajo, y es parcial: solo alcanza a las
 * personas vivas, para que borrar una por error no deje su documento inutilizado
 * para siempre.
 *
 * ## Las tres ubicaciones y la regla 5.1.1
 *
 * Nacimiento, expedición del documento y residencia siguen la regla 5.1.1 del
 * documento 1, y cada una se guarda con el mismo par de columnas:
 *
 * - Si el país es Colombia, se guarda `ciudad_*_id`, el identificador del
 *   catálogo. El departamento no se repite: se llega a él por la ciudad, que
 *   ya sabe de cuál es. Guardarlo dos veces sería tener dos verdades.
 * - Si el país es otro, se guardan los dos textos libres `*_exterior`, que es
 *   lo único que el sistema puede pedir: no existe una lista de ciudades del
 *   mundo entero y la responsabilidad de lo digitado a mano es del cliente.
 *
 * Nunca las dos cosas en la misma columna. Una columna que a veces trae un
 * identificador y a veces un nombre es la que obliga meses después a adivinar
 * cuál de las dos cosas hay dentro.
 *
 * La residencia no tiene par de columnas: es siempre en Colombia, porque el
 * sistema solo gestiona contratos laborales colombianos (documento 1, 5.1).
 *
 * ## Lo que queda nulo a la espera de la decisión D.5
 *
 * `genero` es obligatorio en el formulario, pero sus opciones son parte de la
 * decisión D.5 de `docs/06-decisiones-pendientes.md`, que es de Angela. La
 * columna admite nulos por eso y por nada más: la obligatoriedad se aplica en
 * la validación del servidor cuando se construya el formulario (F1-04), que es
 * donde vive toda la validación de este proyecto. Se guarda como texto legible,
 * no como número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');

            $tabla->string('tipo_documento');
            $tabla->string('numero_documento');
            $tabla->date('fecha_expedicion_documento');

            $tabla->string('primer_nombre');
            $tabla->string('segundo_nombre')->nullable();
            $tabla->string('primer_apellido');
            $tabla->string('segundo_apellido')->nullable();

            $tabla->date('fecha_nacimiento');
            $tabla->string('genero')->nullable();

            // Nacimiento.
            $tabla->char('pais_nacimiento', 2);
            $tabla->foreignId('ciudad_nacimiento_id')->nullable()->constrained('ciudades');
            $tabla->string('departamento_nacimiento_exterior')->nullable();
            $tabla->string('ciudad_nacimiento_exterior')->nullable();

            // Lugar de expedición del documento.
            $tabla->char('pais_expedicion', 2);
            $tabla->foreignId('ciudad_expedicion_id')->nullable()->constrained('ciudades');
            $tabla->string('departamento_expedicion_exterior')->nullable();
            $tabla->string('ciudad_expedicion_exterior')->nullable();

            // Residencia. Siempre en Colombia, así que siempre del catálogo.
            $tabla->string('direccion');
            $tabla->foreignId('ciudad_residencia_id')->constrained('ciudades');

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->foreign('pais_nacimiento')->references('codigo')->on('paises');
            $tabla->foreign('pais_expedicion')->references('codigo')->on('paises');

            // El de los listados y la búsqueda por nombre. `tenant_id` primero,
            // porque el filtro por empresa está en todas las consultas.
            $tabla->index(['tenant_id', 'primer_apellido', 'primer_nombre']);
        });

        // La unicidad del documento por empresa. Es parcial —solo las filas
        // vivas— porque el borrado de este sistema es lógico: sin el `where`,
        // una persona eliminada seguiría reservando su documento para siempre y
        // la empresa no podría volver a registrarla nunca.
        DB::statement(
            'create unique index personas_documento_por_empresa
                on personas (tenant_id, numero_documento)
                where deleted_at is null'
        );

        PoliticaRls::activarEn('personas');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('personas');

        Schema::dropIfExists('personas');
    }
};
