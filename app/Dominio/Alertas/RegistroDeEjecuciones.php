<?php

namespace App\Dominio\Alertas;

use App\Models\EjecucionDeAlertas;
use Illuminate\Support\Carbon;

/**
 * Deja constancia de una corrida del proceso diario, para que la salud del
 * sistema (F5-06, documento 4, capítulo 9) pueda responder «si corrió, cuándo,
 * cuánto tardó y cuántas alertas produjo» sin volver a correr nada.
 *
 * Es lo único que le faltaba a `ResumenDelProceso`: cuenta todo esto pero solo
 * en memoria durante la corrida. `EnviarAlertasDiarias` es quien mide cuánto
 * tardó —lo único que `ResumenDelProceso` no sabe— y quien llama aquí al
 * terminar.
 */
final class RegistroDeEjecuciones
{
    public static function registrar(Carbon $dia, ResumenDelProceso $resumen, int $duracionMs): EjecucionDeAlertas
    {
        return EjecucionDeAlertas::create([
            'fecha' => $dia->toDateString(),
            'ejecutada_en' => Carbon::now(),
            'duracion_ms' => $duracionMs,
            'empresas' => $resumen->empresas(),
            'alertas_nuevas' => $resumen->registradas(),
            'alertas_repetidas' => $resumen->repetidas(),
            'sin_destinatarios' => $resumen->sinDestinatarios(),
            'saldos_refrescados' => $resumen->saldosRefrescados(),
            'empresas_fallidas' => count($resumen->fallidas()),
            'detalle_fallidas' => $resumen->fallidas(),
            'exitosa' => $resumen->fallidas() === [],
        ]);
    }
}
