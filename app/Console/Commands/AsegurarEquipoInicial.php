<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Crea las cuentas del equipo del proveedor que pide `NEXO_EQUIPO_INICIAL`, la
 * primera vez (`PROD-30`).
 *
 * Railway no ofrece una consola dentro del contenedor desde aquí, así que el
 * primer owner no se puede crear corriendo `equipo:invitar` a mano. Este comando
 * lo corre `docker/arrancar.sh` en cada arranque del servicio web: invita a cada
 * persona de la lista **solo si todavía no tiene cuenta**, así que reiniciar no
 * reparte invitaciones nuevas. El enlace sale por correo y en el log del
 * despliegue.
 *
 * El formato es `correo|Nombre`, separados por punto y coma:
 * `ana@nexo.co|Ana Gómez;luis@nexo.co|Luis Pérez`.
 */
class AsegurarEquipoInicial extends Command
{
    protected $signature = 'equipo:asegurar-inicial';

    protected $description = 'Invita a las personas de NEXO_EQUIPO_INICIAL que todavía no tienen cuenta';

    public function handle(): int
    {
        $lista = trim((string) config('panel.equipo_inicial', ''));

        if ($lista === '') {
            return self::SUCCESS;
        }

        foreach (explode(';', $lista) as $entrada) {
            [$correo, $nombre] = array_pad(array_map('trim', explode('|', $entrada, 2)), 2, '');
            $correo = mb_strtolower($correo);

            if ($correo === '') {
                continue;
            }

            if (User::withTrashed()->where('email', $correo)->exists()) {
                $this->line("{$correo} ya tiene cuenta: no se vuelve a invitar.");

                continue;
            }

            $this->call('equipo:invitar', ['correo' => $correo, 'nombre' => $nombre !== '' ? $nombre : $correo]);
        }

        return self::SUCCESS;
    }
}
