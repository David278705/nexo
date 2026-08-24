<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada vez que el proveedor entra al entorno de un cliente (documento 4, 4.4).
 *
 * El capítulo lo marca **CRÍTICO** y le pone tres condiciones que no son
 * negociables. Dos son de software y esta tabla es la que las sostiene:
 *
 * 1. «Queda registrado en la auditoría de esa empresa, visible para el cliente,
 *    indicando que fue el proveedor.» La fila lleva `tenant_id`, así que el
 *    observador de auditoría escribe la línea **en la auditoría de esa
 *    empresa** —toma la empresa del propio registro— con el `owner` como autor.
 *    La constancia no se escribe a mano en ninguna parte: es la consecuencia de
 *    guardar esta fila, como manda la regla 4 del proyecto.
 * 2. «Es de solo lectura por defecto. Modificar datos del cliente exige
 *    activarlo de forma expresa y deja un registro aparte.» De ahí
 *    `escritura_activada_en`: nace nula **siempre**, y activarla es una segunda
 *    escritura sobre la fila, que deja su propia línea de auditoría. Ese es el
 *    «registro aparte»: dos hechos distintos, dos líneas distintas, las dos
 *    visibles para el cliente.
 *
 * La tercera condición —que el contrato y el acuerdo de tratamiento de datos lo
 * mencionen— es de papel y no de código. Está en las decisiones A.2 y A.6.
 *
 * **No lleva el trait `PerteneceATenant` ni política de RLS**, igual que
 * `suscripciones` y `notas_cliente`: es una tabla del panel del proveedor y se
 * escribe desde él, con el `owner`, que no tiene empresa en contexto. Lo que
 * llega al cliente es la constancia en su auditoría, no esta fila.
 *
 * **No tiene borrado lógico y no es un olvido.** Una visita registrada es una
 * constancia, como `auditoria` y como `accesos`: ninguna pantalla la borra y
 * ninguna la edita salvo para cerrarla. Lo que sí se guarda es cuándo terminó,
 * que es lo que convierte «el proveedor entró» en «el proveedor entró y estuvo
 * veinte minutos».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas_al_entorno', function (Blueprint $tabla) {
            $tabla->id();

            // La empresa visitada. De aquí sale el `tenant_id` de la línea de
            // auditoría, que es lo que hace que el cliente pueda verla.
            $tabla->foreignId('tenant_id')->constrained('tenants');

            // Quién entró: siempre un `owner`. Se guarda la cuenta y no el
            // nombre, por lo mismo que en `notas_cliente`.
            $tabla->foreignId('user_id')->constrained('users');

            // Para qué entró, escrito por quien entra. No hay lista de motivos
            // ni motivo por omisión, igual que en la suspensión de una
            // suscripción: esto lo lee el cliente dentro de seis meses.
            $tabla->text('motivo');

            $tabla->timestamp('terminada_en')->nullable();

            $tabla->timestamp('escritura_activada_en')->nullable();
            $tabla->text('motivo_de_escritura')->nullable();

            $tabla->timestamps();

            $tabla->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas_al_entorno');
    }
};
