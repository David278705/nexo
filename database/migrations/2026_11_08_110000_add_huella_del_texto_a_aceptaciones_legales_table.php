<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La huella del texto que se aceptó (`PROD-25`).
 *
 * Una aceptación decía «términos, versión 1», pero el texto vive en un archivo
 * de `resources/legal/`: si alguien lo corregía sin subir la versión, no había
 * forma de demostrar qué decía el día en que el cliente lo aceptó. Con la
 * huella SHA-256 del texto exacto, guardada en el momento de aceptar, cualquier
 * diferencia se nota. Las aceptaciones anteriores quedan sin huella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aceptaciones_legales', function (Blueprint $tabla) {
            $tabla->string('huella_del_texto', 64)->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('aceptaciones_legales', function (Blueprint $tabla) {
            $tabla->dropColumn('huella_del_texto');
        });
    }
};
