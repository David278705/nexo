<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el proveedor conserva cuando un cliente se va (`PROD-15`).
 *
 * Decidido por David el 2026-11-03, sobre la investigación del paso 14 del plan
 * de salida al mercado. Dos cambios de estructura:
 *
 * 1. **La aceptación de un documento legal sobrevive al usuario que aceptó.** Es
 *    la prueba de que el cliente aceptó el contrato, y se conserva; pero los
 *    usuarios del cliente sí se eliminan. Por eso la aceptación guarda desde el
 *    momento de aceptar el nombre y el correo de quien lo hizo, y `user_id` pasa
 *    a admitir nulo: al eliminar al cliente se suelta del usuario y la prueba
 *    queda entera. Las aceptaciones que ya existían toman el nombre y el correo
 *    de su usuario aquí mismo.
 * 2. **`constancias_de_salida`**: la constancia de la entrega de la información
 *    —con la huella SHA-256 del archivo entregado— y la de la eliminación, con
 *    lo eliminado por tabla. Antes solo se imprimían.
 *
 * `constancias_de_salida` es una tabla del proveedor, como `suscripciones`: no
 * lleva el trait `PerteneceATenant` ni política de RLS, porque la escribe y la
 * lee el `owner`, que no tiene empresa en contexto, y ninguna pantalla del
 * cliente la muestra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aceptaciones_legales', function (Blueprint $tabla) {
            $tabla->string('aceptado_por_nombre')->nullable()->after('user_id');
            $tabla->string('aceptado_por_correo')->nullable()->after('aceptado_por_nombre');
            $tabla->foreignId('user_id')->nullable()->change();
        });

        DB::statement(<<<'SQL'
            update aceptaciones_legales a
            set aceptado_por_nombre = u.name, aceptado_por_correo = u.email
            from users u
            where u.id = a.user_id and a.aceptado_por_nombre is null
            SQL);

        Schema::create('constancias_de_salida', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');

            // `entrega` o `eliminacion`.
            $tabla->string('tipo');
            $tabla->timestamp('ocurrida_en');
            $tabla->string('registrada_por')->nullable();

            // Las filas entregadas o eliminadas, por tabla.
            $tabla->json('detalle');

            // Solo en la entrega: la huella del ZIP. Prueba qué se entregó sin
            // conservar su contenido.
            $tabla->string('huella_sha256', 64)->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constancias_de_salida');

        Schema::table('aceptaciones_legales', function (Blueprint $tabla) {
            $tabla->dropColumn(['aceptado_por_nombre', 'aceptado_por_correo']);
        });
    }
};
