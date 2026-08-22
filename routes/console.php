<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * El proceso diario de alertas (documento 2, 5.1): «una vez al día, temprano».
 *
 * A las seis de la mañana en `America/Bogota`, que es la zona de toda fecha
 * laboral del sistema: el correo tiene que estar en la bandeja cuando gestión
 * humana abra el computador, no a media mañana.
 *
 * `withoutOverlapping` no es el mecanismo de la idempotencia —ese es el índice
 * único de `alertas_enviadas`, y tiene que seguir siéndolo—, sino lo que evita
 * que dos corridas se pisen si un día el recorrido tarda más de la cuenta.
 */
Schedule::command('alertas:diarias')
    ->dailyAt('06:00')
    ->timezone('America/Bogota')
    ->withoutOverlapping();
