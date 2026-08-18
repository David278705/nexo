<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El registro de accesos: quién entró, cuándo, desde dónde y si lo consiguió.
 *
 * Lo pide la sección 7.1 del documento de arquitectura, «Inicios de sesión,
 * incluidos los fallidos, con dirección IP y fecha». Los fallidos son la mitad
 * que importa: son los que delatan un intento de entrar por la fuerza.
 *
 * **No sigue el patrón de tabla de negocio y es a propósito.** Un intento
 * fallido se escribe antes de saber quién es quien lo hace: puede que el correo
 * no exista, y entonces no hay empresa a la que atribuirlo. Con `tenant_id`
 * obligatorio y RLS activo, justo esas filas no se podrían escribir. Por eso la
 * empresa se guarda cuando se conoce, nulable, y el aislamiento de la consulta
 * lo pondrá la pantalla que la muestre, filtrando por la empresa del usuario.
 *
 * Tampoco lleva borrado lógico: es un registro de seguridad, no se borra ni se
 * edita desde la aplicación, igual que la auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos', function (Blueprint $tabla) {
            $tabla->id();

            // Nulos los dos cuando el correo no corresponde a ningún usuario.
            $tabla->foreignId('user_id')->nullable()->constrained('users');
            $tabla->foreignId('tenant_id')->nullable()->constrained('tenants');

            // Se guarda el correo tecleado, no solo el usuario: si no existe,
            // saber cuál se intentó es toda la información que hay.
            $tabla->string('correo');
            $tabla->string('resultado');
            $tabla->string('ip', 45)->nullable();
            $tabla->string('agente')->nullable();

            $tabla->timestamp('created_at');

            $tabla->index(['correo', 'created_at']);
            $tabla->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos');
    }
};
