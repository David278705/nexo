<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La auditoría: qué cambió, quién lo cambió y qué había antes.
 *
 * La pide la sección 7.2 del documento de arquitectura, y no es un añadido: es
 * la contrapartida de haber decidido que en este sistema todo se puede editar y
 * eliminar sin límite de antigüedad. Si unas vacaciones de hace ocho meses se
 * pueden borrar, tiene que quedar constancia de quién las borró.
 *
 * **No sigue el patrón de tabla de negocio y es a propósito**, por las mismas
 * dos razones que `accesos`:
 *
 * 1. La línea se escribe dentro de la misma transacción que la operación
 *    auditada. Con la política de RLS y su `with check`, escribir la línea
 *    fallaría en toda operación sin empresa en contexto —un comando de consola,
 *    la aceptación de una invitación, el panel del proveedor— y al fallar
 *    tumbaría la operación legítima con ella. Una auditoría que puede impedir
 *    guardar es peor que no tenerla.
 * 2. El `owner` no pertenece a ninguna empresa y sus acciones también se
 *    auditan, así que la columna tiene que admitir nulos.
 *
 * La empresa se guarda igual, tomada del propio registro auditado, y el
 * aislamiento de la consulta lo pone `Auditoria::deLaEmpresaActual()`, que es
 * por donde entrará la pantalla que la muestre.
 *
 * Tampoco lleva `updated_at` ni borrado lógico: una línea de auditoría se
 * escribe una vez y no se toca nunca más. Que la aplicación no pueda editarla
 * ni borrarla lo garantiza el modelo `Auditoria`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $tabla) {
            $tabla->id();

            // La empresa dueña del dato tocado. Nula cuando el registro no
            // pertenece a ninguna, como el usuario `owner`.
            $tabla->foreignId('tenant_id')->nullable()->constrained('tenants');

            // Quién lo hizo. Nulo cuando no lo hizo nadie: un comando
            // programado, un seeder o el proceso diario de alertas.
            $tabla->foreignId('user_id')->nullable()->constrained('users');

            // El registro tocado, por clase y clave. Se guarda la clase entera
            // y no un nombre corto para que la línea siga siendo legible
            // aunque el modelo se mueva de sitio.
            $tabla->string('modelo');
            $tabla->string('registro_id');

            $tabla->string('accion');

            // Estructurado, para poder mostrar exactamente qué cambió. En una
            // edición solo van los campos que cambiaron, no la fila entera.
            $tabla->json('valores_anteriores')->nullable();
            $tabla->json('valores_nuevos')->nullable();

            $tabla->string('ip', 45)->nullable();
            $tabla->string('agente')->nullable();

            $tabla->timestamp('created_at');

            // El primero es el de la pantalla del cliente: su empresa, lo más
            // reciente arriba. El segundo es el del historial de una ficha.
            $tabla->index(['tenant_id', 'created_at']);
            $tabla->index(['modelo', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
