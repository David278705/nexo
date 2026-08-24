<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los errores de la aplicación de las últimas 24 horas (documento 4,
 * capítulo 9).
 *
 * No existía ningún mecanismo estructurado para esto: `bootstrap/app.php`
 * traía `withExceptions` vacío y todo lo que se reportaba caía solo en
 * `storage/logs/laravel.log`, un archivo plano que la salud del sistema no
 * puede consultar. Esta tabla es lo que reemplaza «parsear el log» por «leer
 * una fila», y la escribe `App\Proveedor\RegistroDeErrores` desde el
 * `reportable()` que ahora sí tiene `bootstrap/app.php`.
 *
 * **No lleva `PerteneceATenant` ni RLS**: un error puede ocurrir sin ninguna
 * empresa en contexto —la propia pantalla de acceso, un comando programado—,
 * así que atribuirlo a un tenant sería inventar un dato que no siempre existe.
 * Es una tabla del proveedor, como `ejecuciones_de_alertas`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('errores_aplicacion', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->timestamp('ocurrido_en');
            $tabla->string('clase');
            $tabla->text('mensaje');
            $tabla->string('archivo')->nullable();
            $tabla->unsignedInteger('linea')->nullable();

            // De dónde vino: una petición web, un comando de consola o un
            // trabajo de la cola. No es de qué empresa —eso ya se decidió que
            // no se guarda—, es de qué parte del sistema.
            $tabla->string('contexto')->nullable();

            $tabla->timestamps();

            $tabla->index('ocurrido_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('errores_aplicacion');
    }
};
