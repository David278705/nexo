<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de planes del capítulo 6 del documento 4: «planes definidos con su
 * precio, número de empleados y de usuarios incluidos».
 *
 * **Es una tabla global, sin `tenant_id` ni RLS**, y no por descuido: la regla
 * de aislamiento del proyecto la nombra junto a los catálogos —países, EPS,
 * festivos— porque un plan no es de ninguna empresa, es la lista de niveles que
 * el proveedor ofrece a todas.
 *
 * **Nace vacía y es una decisión.** La tabla de precios es la decisión A.1 del
 * documento 6, que sigue aplazada a propósito: «antes de construirla hay que
 * calcular el costo real por cliente». Sembrar tres planes con precios
 * inventados sería tomar esa decisión en un `seeder`, y el propio prototipo
 * marca sus cifras como «valores de ejemplo». Por eso los planes se dan de alta
 * desde el panel —la regla del capítulo 8, «estos datos cambian con el tiempo y
 * no pueden vivir en el código»— y hasta que David escriba el primero, la
 * pantalla de Suscripciones lo dice.
 *
 * Los planes no se borran: se desactivan, igual que las EPS y los municipios.
 * Un plan que deja de ofrecerse sigue leyéndose en las suscripciones que lo
 * tienen contratado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->string('nombre')->unique();

            // Dinero en decimal de precisión fija, como los salarios: nunca en
            // coma flotante (convención del proyecto).
            $tabla->decimal('precio_mensual', 14, 2);

            $tabla->unsignedInteger('empleados_incluidos');
            $tabla->unsignedInteger('usuarios_incluidos');

            $tabla->boolean('activo')->default(true);

            $tabla->timestamps();
            $tabla->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
