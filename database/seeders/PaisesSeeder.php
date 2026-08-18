<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * La lista de países del campo «país de nacimiento».
 *
 * Se puede correr las veces que haga falta: actualiza por código ISO en vez de
 * insertar de nuevo, así que volver a cargarlo tras corregir un nombre no
 * duplica nada ni pierde los países que ya se eligieron en un empleado.
 */
class PaisesSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, string> $paises */
        $paises = require database_path('data/paises.php');

        $ahora = now();

        $filas = [];

        foreach ($paises as $codigo => $nombre) {
            $filas[] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::table('paises')->upsert($filas, ['codigo'], ['nombre', 'updated_at']);
    }
}
