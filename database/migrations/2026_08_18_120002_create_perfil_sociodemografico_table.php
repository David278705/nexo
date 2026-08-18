<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El perfil sociodemográfico, sección 5.2 del documento 1.
 *
 * Cuelga de la **persona** y no de la vinculación: el estrato, el nivel
 * educativo o el número de hijos son de la persona y no cambian porque la
 * recontraten. Es una fila por persona, y por eso el nombre de la tabla va en
 * singular.
 *
 * Su razón de ser no es estadística: es el insumo que el SG-SST le exige a la
 * empresa para el diagnóstico de condiciones de salud.
 *
 * ## Todas las columnas admiten nulos, y no es descuido
 *
 * La regla de 5.2 es explícita: **aunque el bloque esté habilitado, todos sus
 * campos son opcionales** y dejarlos en blanco no impide guardar. Es lo
 * contrario del resto del formulario. Además, una empresa puede activar el
 * bloque meses después de haber cargado su planta: los empleados que ya
 * existían quedan en blanco y eso no es un error que haya que señalar.
 *
 * Que el bloque se muestre o no lo decide el parámetro
 * `perfil_sociodemografico_habilitado` de la empresa, que ya existe en
 * `tenants`. La tabla no lo repite: el parámetro es de la empresa, no de cada
 * persona.
 *
 * ## Lo que espera a la decisión D.5
 *
 * `estado_civil`, `nivel_educativo`, `medio_transporte` y
 * `tiempo_desplazamiento` son listas cuyas opciones son parte de la decisión
 * D.5 de `docs/06-decisiones-pendientes.md`. Se guardan como texto legible, así
 * que cuando lleguen las opciones no hay que tocar ni la tabla ni el modelo:
 * solo el catálogo que alimenta el formulario.
 *
 * `tipo_vivienda` y `estrato` sí están decididos en el propio documento 1
 * —propia, arrendada o familiar; y del 1 al 6—, así que esos dos tienen su
 * enumerado y su rango desde ya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfil_sociodemografico', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('persona_id')->constrained('personas');

            $tabla->string('estado_civil')->nullable();
            $tabla->string('nivel_educativo')->nullable();
            $tabla->unsignedSmallInteger('numero_hijos')->nullable();
            $tabla->unsignedSmallInteger('personas_a_cargo')->nullable();
            $tabla->string('tipo_vivienda')->nullable();
            $tabla->unsignedSmallInteger('estrato')->nullable();
            $tabla->string('medio_transporte')->nullable();
            $tabla->string('tiempo_desplazamiento')->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'persona_id']);
        });

        // Una sola fila viva por persona. Parcial por lo mismo que el documento
        // en `personas`: el borrado de este sistema es lógico.
        DB::statement(
            'create unique index perfil_sociodemografico_por_persona
                on perfil_sociodemografico (tenant_id, persona_id)
                where deleted_at is null'
        );

        PoliticaRls::activarEn('perfil_sociodemografico');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('perfil_sociodemografico');

        Schema::dropIfExists('perfil_sociodemografico');
    }
};
