<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La bitácora interna del proveedor sobre un cliente (documento 4, 4.3 y 17.2):
 * «empresa, autor, fecha y texto».
 *
 * Es la pestaña «Bitácora» de la ficha: «notas internas del equipo sobre ese
 * cliente: llamadas, quejas, promesas hechas. Nadie recuerda dentro de seis
 * meses qué se le prometió a quién».
 *
 * **No lleva el trait `PerteneceATenant` ni política de RLS**, igual que
 * `suscripciones`, `pagos` y `accesos_de_prueba`: es una tabla del panel del
 * proveedor. La escribe y la lee el `owner`, que no tiene empresa en contexto, y
 * ninguna pantalla del cliente la muestra.
 *
 * **Y aquí eso no es solo una comodidad técnica: es el punto.** Una nota que
 * dice «llamó molesto por el cálculo, se le prometió revisarlo» es del equipo
 * del proveedor y **no** se le enseña al cliente. Por eso `NotaCliente` es
 * además el primer modelo que entra a la lista `SIN_AUDITAR` del observador por
 * un motivo de confidencialidad y no de ruido: la línea de auditoría llevaría el
 * texto de la nota a la auditoría **de esa empresa**, que es justamente la que
 * el cliente sí puede leer.
 *
 * `fecha` la pone el servidor el día en que se escribe la nota, y no es un campo
 * del formulario: el prototipo enseña la bitácora como una línea de tiempo con
 * la fecha y el autor, y una fecha que se digita es una fecha que se puede
 * mover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_cliente', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('tenant_id')->constrained('tenants');

            // El autor es del lado del proveedor. Se guarda el usuario y no su
            // nombre escrito: el nombre se lee de la cuenta y sigue siendo
            // correcto si cambia después.
            $tabla->foreignId('user_id')->constrained('users');

            $tabla->date('fecha');
            $tabla->text('texto');

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_cliente');
    }
};
