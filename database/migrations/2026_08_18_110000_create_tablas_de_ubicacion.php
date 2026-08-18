<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Países, departamentos y ciudades: la geografía del formulario de empleado.
 *
 * **No siguen el patrón de tabla de negocio y es a propósito.** Son catálogos
 * globales del sistema, iguales para todos los clientes, así que no llevan
 * `tenant_id`, ni el trait `PerteneceATenant`, ni política de RLS. Están en la
 * lista de tablas globales de `.claude/rules/10-multiempresa.md`.
 *
 * La regla 5.1.1 del documento 1 es la que da forma a estas tres tablas: el
 * país siempre sale de una lista; si es Colombia, departamento y ciudad salen
 * de listas encadenadas; si no lo es, se digitan como texto libre. Por eso
 * `departamentos` y `ciudades` solo guardan Colombia: construir el mundo entero
 * no lo justifica el volumen y obligaría a pagar un servicio externo.
 *
 * `codigo_dane` es la llave del archivo oficial, no la nuestra. En
 * `departamentos` viene completo; en `ciudades` admite nulos porque la lista se
 * cargó por nombre y los códigos de municipio se completan cuando se cargue el
 * archivo del DANE desde el panel del proveedor (tarea F1B-04).
 *
 * `activo` existe porque un catálogo no se borra: una ciudad que deja de estar
 * disponible tiene que seguir leyéndose en los empleados que ya la usaron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paises', function (Blueprint $tabla) {
            $tabla->id();

            // ISO 3166-1 alfa-2. Es el código que se guarda en los empleados,
            // no el nombre: los nombres se corrigen, los códigos no.
            $tabla->char('codigo', 2)->unique();
            $tabla->string('nombre');
            $tabla->boolean('activo')->default(true);

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index('nombre');
        });

        Schema::create('departamentos', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->char('codigo_dane', 2)->unique();
            $tabla->string('nombre');
            $tabla->boolean('activo')->default(true);

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index('nombre');
        });

        Schema::create('ciudades', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('departamento_id')->constrained('departamentos');

            $tabla->char('codigo_dane', 5)->nullable()->unique();
            $tabla->string('nombre');
            $tabla->boolean('activo')->default(true);

            $tabla->timestamps();
            $tabla->softDeletes();

            // El primero es el de la lista encadenada: las ciudades de un
            // departamento, en orden alfabético. El segundo impide cargar dos
            // veces el mismo municipio al repetir el seeder.
            $tabla->index(['departamento_id', 'nombre']);
            $tabla->unique(['departamento_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudades');
        Schema::dropIfExists('departamentos');
        Schema::dropIfExists('paises');
    }
};
