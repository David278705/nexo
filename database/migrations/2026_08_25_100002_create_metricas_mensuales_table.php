<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La foto de cierre de cada mes para las dos cifras del inicio del panel que
 * piden variación (documento 4, 3.2): «empresas activas, con la variación
 * frente al mes anterior» e «ingreso recurrente mensual y su variación».
 *
 * Las dos son un total de hoy, y «hoy contra hace un mes» no se puede
 * reconstruir de otra forma: el estado de una empresa cambia sin dejar
 * historia propia —solo la auditoría, que no está pensada para sumarse— y el
 * ingreso recurrente depende de qué plan tenía cada suscripción ese día, no
 * el de hoy. Por eso esta tabla guarda una fila por mes, la más simple posible:
 * el total de cada cifra en el momento en que se captura.
 *
 * **No lleva `PerteneceATenant` ni RLS**: es del negocio entero, como
 * `suscripciones` y `planes`.
 *
 * La captura la hace `App\Console\Commands\CapturarMetricasMensuales`, el día
 * 1 de cada mes a primera hora, y guarda el mes que **acaba de cerrar** —nada
 * cambia entre las 00:00 y la hora en que corre el comando, así que el valor
 * capturado es el mismo que hubiera tenido a las 23:59 del día anterior—.
 * `mes` es único: dos corridas del mismo mes no duplican la fila, y por eso el
 * comando puede reponerse sin miedo, igual que `alertas:diarias`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metricas_mensuales', function (Blueprint $tabla) {
            $tabla->id();

            // «2026-08», el mes que cierra esta foto.
            $tabla->string('mes', 7)->unique();

            $tabla->unsignedInteger('empresas_activas');
            $tabla->decimal('ingreso_recurrente', 14, 2);

            $tabla->timestamp('capturado_en');

            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_mensuales');
    }
};
