<?php

namespace App\Console\Commands;

use App\Models\Pais;
use App\Models\Plan;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\FestivosSeeder;
use Database\Seeders\PlanesSeeder;
use Illuminate\Console\Command;

/**
 * Deja los catálogos globales listos en un servidor nuevo (`PROD-20`).
 *
 * **El problema que resuelve:** el arranque migraba la base pero nunca cargaba
 * los catálogos —países, departamentos, municipios, EPS, fondos, ARL, cajas,
 * bancos y festivos—, y la guía de despliegue tampoco lo pedía. En una base
 * nueva de Railway no se habría podido crear ni una empresa: no habría ciudad
 * que elegir.
 *
 * Lo corre `docker/arrancar.sh` en cada arranque del servicio web, y por eso
 * distingue tres cosas:
 *
 * - **Los catálogos se cargan una sola vez**, cuando la base está vacía. Después
 *   son del proveedor, que los edita desde el panel: volver a cargarlos en cada
 *   despliegue haría reaparecer con su nombre original una EPS que alguien
 *   renombró, porque los seeders identifican cada fila por su nombre.
 * - **Los planes**, igual que los catálogos: una sola vez (`PROD-24`).
 * - **Los festivos se cargan siempre.** El año nuevo entra agregándolo a
 *   `database/data/festivos.php` y desplegando, sin otro paso. Su seeder solo
 *   actualiza nombre y año de una fecha que ya existía, así que no pisa nada.
 */
class InicializarCatalogos extends Command
{
    protected $signature = 'catalogos:inicializar';

    protected $description = 'Carga los catálogos globales si la base está vacía, y los festivos siempre';

    public function handle(): int
    {
        // Los planes de la propuesta de precios (`PROD-24`), solo si no hay
        // ninguno: después son del proveedor y se editan en el panel.
        if (Plan::query()->withTrashed()->doesntExist()) {
            $this->call('db:seed', ['--class' => PlanesSeeder::class, '--force' => true]);
            $this->info('Planes cargados por primera vez.');
        }

        if (Pais::query()->doesntExist()) {
            $this->call('db:seed', ['--class' => CatalogosSeeder::class, '--force' => true]);
            $this->info('Catálogos cargados por primera vez.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => FestivosSeeder::class, '--force' => true]);
        $this->info('Los catálogos ya existían: solo se actualizaron los festivos.');

        return self::SUCCESS;
    }
}
