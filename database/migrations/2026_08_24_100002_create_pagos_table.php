<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de facturas y pagos de cada suscripción (documento 4, capítulo 6
 * y 17.2): «suscripción, monto, fecha, estado, número de factura».
 *
 * Una fila es **plata que entró**, con su número de factura. No son dos tablas
 * —una de facturas y otra de pagos— porque en este negocio no hay pagos sin
 * factura ni facturas que se paguen a trozos: la suscripción es mensual y se
 * cobra entera. Lo que dice que hay plata *por* cobrar no es una fila
 * pendiente, es la próxima fecha de cobro de la suscripción ya vencida, que es
 * el indicador de mora del listado de empresas.
 *
 * Cuelga de la suscripción y no de la empresa a propósito: la empresa se llega
 * por la suscripción, y colgarla de las dos permitiría que un pago apuntara a
 * la suscripción de una empresa y al nombre de otra.
 *
 * Como `suscripciones`, no lleva `tenant_id` con trait ni RLS: es una tabla del
 * panel del proveedor. Y nada se borra: la anulación de una factura es un
 * estado, no un `delete`, porque una factura anulada tiene que seguir
 * apareciendo en el historial con su número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('suscripcion_id')->constrained('suscripciones');

            $tabla->decimal('monto', 14, 2);

            // La fecha del hecho, sin hora: es una fecha de facturación, no un
            // instante (convención del proyecto).
            $tabla->date('fecha');

            $tabla->string('estado');

            // El número de factura es único en todo el sistema o no es un
            // número de factura. Puede faltar mientras la facturación
            // electrónica no esté habilitada (documento 6, A.6).
            $tabla->string('numero_factura')->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['suscripcion_id', 'fecha']);
        });

        DB::statement(
            'create unique index pagos_numero_factura_unico
                on pagos (numero_factura)
                where numero_factura is not null and deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
