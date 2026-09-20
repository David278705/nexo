<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Los catálogos globales del sistema, todos de una vez.
 *
 * Es el seeder que se corre al montar un entorno nuevo y el que corren las
 * pruebas que necesitan geografía o festivos. No toca ninguna empresa: estas
 * tablas son iguales para todos los clientes.
 *
 * Todos los seeders de catálogo escriben con el query builder y no con
 * Eloquent, a propósito. El observador de auditoría se engancha a los eventos
 * de Eloquent de **todos** los modelos, así que cargar mil cien municipios con
 * `create()` dejaría mil cien líneas de auditoría de algo que no hizo ningún
 * usuario. Cuando una ciudad se cambie desde el panel del proveedor sí pasará
 * por el modelo y sí quedará auditada, que es cuando importa.
 */
class CatalogosSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PaisesSeeder::class,
            UbicacionesSeeder::class,
            FestivosSeeder::class,
            EntidadesSeeder::class,
        ]);
    }
}
