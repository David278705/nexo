<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Los catálogos globales van primero: no dependen de ninguna empresa y
        // todo lo demás —empleados, novedades, cálculos— sí depende de ellos.
        //
        // `WithoutModelEvents` se mudó de aquí a `CatalogosSeeder`, donde hace
        // falta, porque desde aquí silenciaba también lo que viniera después.
        // Y lo que viene después necesita justamente esos eventos: de ellos
        // cuelgan el `tenant_id` que pone `PerteneceATenant` y el periodo
        // inicial que abre `ObservadorDeVinculacion`.
        $this->call(CatalogosSeeder::class);

        // En una máquina de desarrollo, además, las cuentas y la empresa con
        // las que se entra a trabajar. **En cualquier otro entorno esto no
        // corre**: crea contraseñas conocidas, y el propio seeder lo impide.
        //
        // Aquí estaba antes el `Test User` de Laravel, que se quitó porque
        // engañaba: `tenant_id` nulo y rol `admin` es un usuario de empresa sin
        // empresa, así que no servía para entrar a ninguna parte.
        if (app()->environment('local')) {
            $this->call(DesarrolloSeeder::class);
        }
    }
}
