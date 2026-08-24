<?php

namespace App\Console\Commands;

use App\Proveedor\AmbienteDeDemostracion;
use Illuminate\Console\Command;

/**
 * El comando del proceso nocturno de restauración del ambiente de
 * demostración (documento 4, capítulo 10, F5-03).
 *
 * Es una cáscara a propósito, igual que `alertas:diarias`: toda la decisión
 * de qué se borra y qué se vuelve a escribir está en
 * `AmbienteDeDemostracion`, que es lo que se prueba. El botón «Restaurar
 * ahora» del panel llama al mismo método.
 */
class RestaurarAmbienteDeDemostracion extends Command
{
    protected $signature = 'demostracion:restaurar';

    protected $description = 'Vacía y vuelve a poblar el ambiente de demostración con la misma planta de siempre';

    public function handle(AmbienteDeDemostracion $ambiente): int
    {
        $tenant = $ambiente->restaurar();

        $this->info('Ambiente de demostración restaurado: '.$tenant->getAttribute('nombre'));

        return self::SUCCESS;
    }
}
