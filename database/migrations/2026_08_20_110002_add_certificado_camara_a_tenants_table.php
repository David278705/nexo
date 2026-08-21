<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El certificado de Cámara de Comercio que el paso 1 de «crear empresa» pide
 * adjuntar (doc. 04, 4.2).
 *
 * Se guardan dos cosas y no una: **dónde quedó el archivo** y **cómo se llamaba
 * cuando llegó**. La ruta la inventa el servidor con un nombre al azar —un
 * archivo subido no puede decidir su propio nombre en el disco—, y el nombre
 * original es el único que le sirve a quien lo descargue seis meses después.
 *
 * Las dos columnas son nulas porque el archivo es opcional: el documento pide
 * adjuntar el certificado recibido, no impedir que se cree la empresa cuando la
 * reunión termina sin él a mano. Es un dato de expediente, no una regla legal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->string('certificado_camara_ruta')->nullable();
            $tabla->string('certificado_camara_nombre')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $tabla) {
            $tabla->dropColumn(['certificado_camara_ruta', 'certificado_camara_nombre']);
        });
    }
};
