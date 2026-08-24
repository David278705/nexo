<?php

namespace App\Proveedor;

use App\Models\ErrorDeAplicacion;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Deja constancia de un error de la aplicación, para la salud del sistema del
 * panel (F5-06, documento 4, capítulo 9): «errores de la aplicación de las
 * últimas 24 horas».
 *
 * La llama el `reportable()` de `bootstrap/app.php`, que Laravel invoca
 * después de descartar lo que nunca fue un error de verdad —una validación,
 * un 404, credenciales equivocadas—: esa lista la decide el propio framework
 * antes de llegar aquí, así que esta tabla no se llena de ruido.
 *
 * **Nunca puede tumbar el reporte de un error por fallar ella misma**: si la
 * base de datos no responde, guardar el error sobre el error sería peor que
 * no guardarlo, y por eso todo el cuerpo va en su propio `try`.
 */
final class RegistroDeErrores
{
    public static function registrar(Throwable $error): void
    {
        try {
            ErrorDeAplicacion::create([
                'ocurrido_en' => Carbon::now(),
                'clase' => $error::class,
                'mensaje' => $error->getMessage(),
                'archivo' => $error->getFile(),
                'linea' => $error->getLine(),
                'contexto' => app()->runningInConsole() ? 'consola' : 'web',
            ]);
        } catch (Throwable) {
            // Sin salida: es la última línea antes de que el error se pierda
            // de verdad, y escribir aquí solo lo escondería de nuevo.
        }
    }
}
