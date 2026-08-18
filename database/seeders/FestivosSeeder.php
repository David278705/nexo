<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * El calendario de festivos de los años cargados en `database/data/festivos.php`.
 *
 * Cargar el año siguiente es una tarea anual del proveedor: se agregan las
 * fechas al archivo de datos, se verifican una a una y se vuelve a correr este
 * seeder. Los años que ya estaban no se tocan.
 */
class FestivosSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<int, array<string, string>> $calendario */
        $calendario = require database_path('data/festivos.php');

        $ahora = now();

        $filas = [];

        foreach ($calendario as $anio => $festivos) {
            foreach ($festivos as $fecha => $nombre) {
                $filas[] = [
                    'fecha' => $fecha,
                    'nombre' => $nombre,
                    'anio' => $anio,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        DB::table('festivos')->upsert($filas, ['fecha'], ['nombre', 'anio', 'updated_at']);
    }
}
