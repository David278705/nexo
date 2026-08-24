<?php

namespace App\Proveedor;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * «Trabajos en cola pendientes y fallidos, con la posibilidad de
 * reintentarlos» (documento 4, capítulo 9).
 *
 * `jobs` y `failed_jobs` ya existen desde el primer día del proyecto —son el
 * andamiaje estándar de Laravel para `QUEUE_CONNECTION=database`— y nadie las
 * había leído todavía. No hace falta ninguna tabla nueva: solo consultarlas.
 *
 * El reintento delega en `queue:retry`, el comando nativo de Laravel: es lo
 * que ya sabe mover una fila de `failed_jobs` de vuelta a `jobs` sin
 * reinventar ese mecanismo.
 */
final class ColaDeTrabajos
{
    public static function pendientes(): int
    {
        return (int) DB::table('jobs')->count();
    }

    /**
     * @return list<array{uuid: string, cola: string, excepcion: string, falloEn: string}>
     */
    public static function fallidos(): array
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->get()
            ->map(fn (object $fila): array => [
                'uuid' => (string) $fila->uuid,
                'cola' => (string) $fila->queue,
                // La primera línea de la traza, que es la que dice qué pasó;
                // el resto es ruido para una tarjeta del panel.
                'excepcion' => (string) strtok((string) $fila->exception, "\n"),
                'falloEn' => (string) $fila->failed_at,
            ])
            ->values()
            ->all();
    }

    public static function fallidosTotal(): int
    {
        return (int) DB::table('failed_jobs')->count();
    }

    /**
     * Reintenta un trabajo fallido. Si el identificador no existe, el propio
     * comando de Laravel no hace nada: no hay excepción que atrapar aquí.
     */
    public static function reintentar(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
    }
}
