<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El segundo factor de autenticación.
 *
 * El capítulo 10 del documento del panel del proveedor lo pide obligatorio para
 * todo el equipo, y el motivo está escrito ahí mismo: «estas cuentas ven la
 * información de todos los clientes a la vez».
 *
 * Las dos columnas van en `users` y no en una tabla aparte porque son un dato
 * del usuario, uno por cuenta, y porque el panel las consulta en cada petición:
 * una tabla aparte sería una consulta más para responder lo mismo.
 *
 * `segundo_factor_secreto` es texto y no una longitud fija: se guarda cifrado
 * —el modelo lo declara `encrypted`— y el texto cifrado ocupa mucho más que la
 * clave. Se guarda cifrado y no con hash porque hay que poder leerlo para
 * calcular el código de cada momento; no es una contraseña.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->text('segundo_factor_secreto')->nullable()->after('password');

            // Mientras esté nula, el secreto está emitido pero sin comprobar: se
            // guarda al enseñar la clave y solo esta fecha demuestra que la
            // aplicación del usuario produce el código correcto. Sin ella, un
            // secreto que nadie llegó a registrar dejaría la cuenta fuera.
            $tabla->timestamp('segundo_factor_confirmado_en')
                ->nullable()
                ->after('segundo_factor_secreto');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropColumn(['segundo_factor_secreto', 'segundo_factor_confirmado_en']);
        });
    }
};
