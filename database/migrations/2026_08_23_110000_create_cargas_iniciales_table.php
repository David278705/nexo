<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El registro de cada carga inicial ejecutada: «qué archivo, cuántas filas,
 * quién y cuándo» (documento 2, 6.1, paso 6, y documento 4, capítulo 5).
 *
 * Tiene dos oficios y el segundo importa más que el primero:
 *
 * 1. **Es la constancia** que el panel enseña en su historial de cargas.
 * 2. **Es lo que sabe si la plantilla de empleados ya se procesó.** El documento
 *    2, 6.3 manda impedir que un histórico entre antes que ella, y esa pregunta
 *    se responde aquí y no contando personas: una empresa puede tener empleados
 *    registrados por pantalla sin haber pasado nunca por la carga.
 *
 * `archivo` guarda el **nombre** del archivo que entregó el cliente, no el
 * archivo: el documento 1, 13.1 dice que el Excel solo se usa para cargar y que
 * no se almacena ni se conserva.
 *
 * `fecha_corte_saldo` solo lo llena la plantilla de empleados. Es el día al que
 * se refieren los «días de vacaciones disponibles hoy» que informó la empresa, y
 * queda escrito aquí además de en cada vinculación para no tener que deducirlo
 * después de `created_at`, que es un instante en UTC y no una fecha de Bogotá.
 *
 * `usuario_id` es nulable porque la carga también se ejecuta por consola, donde
 * no hay nadie autenticado. Apunta a `users` y no a la empresa: quien carga es
 * el proveedor, que no pertenece a ninguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargas_iniciales', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');

            // La clave de la plantilla: empleados · perfil · incapacidades ·
            // vacaciones · licencias · movimientos.
            $tabla->string('plantilla');
            $tabla->string('archivo');
            $tabla->unsignedInteger('filas');

            $tabla->foreignId('usuario_id')->nullable()->constrained('users');
            $tabla->date('fecha_corte_saldo')->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['tenant_id', 'plantilla']);
            $tabla->index(['tenant_id', 'created_at']);
        });

        PoliticaRls::activarEn('cargas_iniciales');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('cargas_iniciales');

        Schema::dropIfExists('cargas_iniciales');
    }
};
