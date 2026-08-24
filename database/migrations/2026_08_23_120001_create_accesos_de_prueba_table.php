<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los accesos de prueba con vigencia limitada del capítulo 10 del documento 4,
 * los que David entrega a un prospecto en la etapa «Prueba» del proceso
 * comercial (documento 4, 12.1): «se le entrega un acceso de prueba con
 * vigencia limitada para que lo explore solo».
 *
 * **No lleva `tenant_id` con el trait ni política de RLS, a propósito, igual
 * que `invitaciones`.** El enlace se abre sin sesión iniciada, así que en esa
 * petición no hay empresa en contexto; y a diferencia de una invitación, aquí
 * la empresa de destino es siempre la misma —el ambiente de demostración—, así
 * que el aislamiento entre clientes no tiene nada que proteger en esta tabla:
 * es una tabla del panel del proveedor, no un dato de un cliente.
 *
 * El token se guarda con hash, como en `invitaciones`: quien lea la tabla no
 * puede fabricar el enlace de nadie.
 *
 * **La vigencia no sale de una constante.** El documento 6, D.12, deja sin
 * decidir cuántos días dura un acceso de demostración, y el documento 4, 12.1,
 * dice quién lo decide cada vez: «David genera el acceso». Por eso
 * `dias_de_vigencia` es lo que quien lo genera escribe en el formulario, no un
 * valor por omisión inventado aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos_de_prueba', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');

            $tabla->string('nombre_prospecto');
            $tabla->string('token')->unique();
            $tabla->unsignedInteger('dias_de_vigencia');

            $tabla->timestamp('generado_en');
            $tabla->timestamp('expira_en');
            $tabla->timestamp('ultimo_uso_en')->nullable();
            $tabla->timestamp('revocado_en')->nullable();

            $tabla->timestamps();

            $tabla->index(['tenant_id', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_de_prueba');
    }
};
