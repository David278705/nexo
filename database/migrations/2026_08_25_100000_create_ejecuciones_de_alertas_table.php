<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de corridas del proceso diario de alertas (documento 4,
 * capítulo 9): «si corrió, cuándo, cuánto tardó y cuántas alertas produjo».
 *
 * `ResumenDelProceso` (F3-01) ya cuenta todo esto, pero solo en memoria durante
 * una corrida: el propio comando lo dice en su comentario, escrito pensando en
 * esta tarea. Sin una tabla, la salud del sistema no tiene de dónde leer «el
 * proceso de hoy» ni «las últimas ejecuciones», así que esta tabla es la pieza
 * que faltaba y la escribe `EnviarAlertasDiarias` al terminar cada corrida.
 *
 * **No lleva el trait `PerteneceATenant` ni política de RLS**, igual que
 * `suscripciones` y `planes`: es una tabla del panel del proveedor, no de una
 * empresa cliente. `empresas_fallidas` guarda cuántas fallaron y
 * `detalle_fallidas` guarda cuáles y por qué, para que la pantalla de salud
 * pueda enseñar el motivo sin tener que volver a correr nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejecuciones_de_alertas', function (Blueprint $tabla) {
            $tabla->id();

            // El día que se evaluó, no cuándo corrió: son iguales casi
            // siempre, pero `--fecha` del comando los puede separar.
            $tabla->date('fecha');
            $tabla->timestamp('ejecutada_en');
            $tabla->unsignedInteger('duracion_ms');

            $tabla->unsignedInteger('empresas')->default(0);
            $tabla->unsignedInteger('alertas_nuevas')->default(0);
            $tabla->unsignedInteger('alertas_repetidas')->default(0);
            $tabla->unsignedInteger('sin_destinatarios')->default(0);
            $tabla->unsignedInteger('saldos_refrescados')->default(0);
            $tabla->unsignedInteger('empresas_fallidas')->default(0);
            $tabla->json('detalle_fallidas')->nullable();

            $tabla->boolean('exitosa')->default(true);

            $tabla->timestamps();

            $tabla->index('ejecutada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejecuciones_de_alertas');
    }
};
