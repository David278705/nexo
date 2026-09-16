<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El celular y el correo del empleado, que `personas` nunca tuvo (`HD-06`).
 *
 * David lo confirmó buscándolos en el listado y en la ficha: no había ningún
 * dato de contacto, ni siquiera para las personas que ya estaban registradas.
 *
 * **Las dos columnas quedan nulas**, y no es lo mismo que decir que son
 * opcionales: el asistente y la carga masiva las van a exigir de aquí en
 * adelante, con la validación de `RegistrarEmpleado` —correo con formato de
 * correo, celular de diez dígitos—. Lo que no puede exigirse es completarlas
 * hacia atrás: esta empresa ya tiene personas registradas y nadie va a
 * adivinar su celular o su correo para llenar la columna. Es el mismo criterio
 * que `F1-06` aplicó al activar el perfil sociodemográfico después de
 * registrada la persona: el dato que falta se queda en blanco, no se inventa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->string('celular')->nullable()->after('direccion');
            $tabla->string('correo')->nullable()->after('celular');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->dropColumn(['celular', 'correo']);
        });
    }
};
