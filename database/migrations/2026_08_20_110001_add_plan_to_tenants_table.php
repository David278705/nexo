<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que se pacta en el paso 3 de «crear empresa» (doc. 04, 4.2): nivel
 * contratado, empleados y usuarios incluidos, y fecha de inicio.
 *
 * **No es la tabla `planes` del capítulo 17.2, y no lo es a propósito.** Esa
 * tabla lleva precio y estado, cuelga de `suscripciones` y de `pagos`, y llega
 * con F5-04. Antes de construirla hace falta la tabla de precios, que la
 * decisión A.1 del documento 6 declara pendiente: «lo que falta es la tabla
 * concreta». Inventar aquí unos niveles cerrados obligaría a migrarlos el día
 * que existan los de verdad.
 *
 * Así que el plan se guarda como lo que hoy es: el nombre que se le dijo al
 * cliente en la reunión y los dos números que se le prometieron. El día que
 * exista `planes`, estas cuatro columnas se sustituyen por `plan_id` y la
 * suscripción, y lo escrito aquí es exactamente lo que hay que migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->string('plan_nombre')->nullable();
            $tabla->unsignedInteger('plan_empleados_incluidos')->nullable();
            $tabla->unsignedInteger('plan_usuarios_incluidos')->nullable();
            $tabla->date('plan_inicia_el')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->dropColumn([
                'plan_nombre',
                'plan_empleados_incluidos',
                'plan_usuarios_incluidos',
                'plan_inicia_el',
            ]);
        });
    }
};
