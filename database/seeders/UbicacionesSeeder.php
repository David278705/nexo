<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Los 32 departamentos de Colombia más Bogotá D.C., con sus municipios.
 *
 * Es lo que hace funcionar las listas encadenadas de la regla 5.1.1: primero se
 * elige el departamento y después su ciudad. Por eso los municipios se insertan
 * colgados de su departamento y no en una lista plana.
 *
 * Como el de países, se puede correr de nuevo sin duplicar: el departamento se
 * reconoce por su código DANE y el municipio por su nombre dentro de él.
 */
class UbicacionesSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{codigo: string, nombre: string, municipios: list<string>}> $departamentos */
        $departamentos = require database_path('data/ubicaciones.php');

        $ahora = now();

        foreach ($departamentos as $departamento) {
            DB::table('departamentos')->upsert([
                [
                    'codigo_dane' => $departamento['codigo'],
                    'nombre' => $departamento['nombre'],
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ],
            ], ['codigo_dane'], ['nombre', 'updated_at']);

            $id = DB::table('departamentos')
                ->where('codigo_dane', $departamento['codigo'])
                ->value('id');

            $municipios = [];

            foreach ($departamento['municipios'] as $municipio) {
                $municipios[] = [
                    'departamento_id' => $id,
                    'nombre' => $municipio,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            DB::table('ciudades')->upsert(
                $municipios,
                ['departamento_id', 'nombre'],
                ['updated_at'],
            );
        }
    }
}
