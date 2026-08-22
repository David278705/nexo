<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El registro de todo lo que el motor de alertas envía (documento 2, cap. 5).
 *
 * Es una tabla con dos oficios a la vez, y los dos importan:
 *
 * 1. **Es la idempotencia.** La sección 5.2 lo llama crítico: si el proceso
 *    corre dos veces —un reinicio, un despliegue, alguien que lo lanza a
 *    mano— el cliente no puede recibir el mismo correo dos veces. Lo impide
 *    el índice único sobre `clave`, no una comprobación en el código: una
 *    comprobación se puede olvidar y además pierde la carrera si dos procesos
 *    corren a la vez.
 * 2. **Es la evidencia.** La sección 5.5 pide un registro consultable de a
 *    quién, cuándo, con qué asunto y con qué resultado. Es lo que se mira el
 *    día que un cliente diga que nunca le llegó, y lo que consultarán la
 *    consola del proveedor (F5-05) y el panel de alertas (F3-06).
 *
 * Por eso el asunto y el cuerpo se guardan **ya escritos**: lo que se envió no
 * se vuelve a calcular al consultarlo, porque el dato de la persona pudo
 * cambiar después y entonces el registro contaría otra historia.
 *
 * `vinculacion_id` es nulo en los resúmenes mensuales (documento 1, 9.7): esos
 * no son de un empleado, son de la empresa entera.
 *
 * `fecha_programada` es el día en que la alerta **se envía**, ya corrido al día
 * hábil anterior si hacía falta (F3-02), y no el día del hecho que avisa: ese
 * es `fecha_del_hecho`, el que se escribe entre paréntesis en el texto. Son dos
 * fechas distintas siempre que hay corrimiento, y la clave se arma con la
 * primera porque lo que no puede repetirse es un envío en un mismo día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_enviadas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('vinculacion_id')->nullable()->constrained('vinculaciones');

            // El texto legible de `TipoDeAlerta`, nunca un número.
            $tabla->string('tipo');

            // `tipo:vinculacion:fecha_programada`, la clave de la sección 5.2.
            $tabla->string('clave');

            $tabla->date('fecha_programada');
            $tabla->date('fecha_del_hecho');

            $tabla->string('asunto');
            $tabla->text('cuerpo');

            // A quién se le mandó, tal como estaba el día del envío.
            $tabla->json('destinatarios');

            // pendiente · enviada · fallida.
            $tabla->string('estado');

            $tabla->unsignedSmallInteger('intentos')->default(0);
            $tabla->text('motivo_del_fallo')->nullable();
            $tabla->timestamp('enviada_en')->nullable();

            $tabla->timestamps();
            $tabla->softDeletes();

            // La mitad del mecanismo de idempotencia que vive en la base de
            // datos. Lleva `tenant_id` delante como todos los índices del
            // proyecto: dos empresas distintas sí tienen la misma clave.
            $tabla->unique(['tenant_id', 'clave']);

            $tabla->index(['tenant_id', 'fecha_programada']);
            $tabla->index(['tenant_id', 'estado']);
        });

        PoliticaRls::activarEn('alertas_enviadas');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('alertas_enviadas');

        Schema::dropIfExists('alertas_enviadas');
    }
};
