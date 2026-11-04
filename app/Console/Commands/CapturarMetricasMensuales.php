<?php

namespace App\Console\Commands;

use App\Models\MetricaMensual;
use App\Models\Tenant;
use App\Proveedor\EstadoDeEmpresa;
use App\Proveedor\PanelDeSuscripciones;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Guarda la foto de cierre de un mes para las dos cifras de negocio del
 * inicio del panel que piden variación (F5-06, documento 4, 3.2): empresas
 * activas e ingreso recurrente.
 *
 * Corre el día 1 de cada mes a primera hora (`routes/console.php`). Como nada
 * cambia entre las 00:00 y la hora en que arranca, el total leído en ese
 * momento es el mismo que hubiera tenido a las 23:59 del día anterior, y por
 * eso la fila se guarda con la clave del mes que **acaba de cerrar**, no del
 * que empieza.
 *
 * `mes` es único, así que reponer el comando a mano un mismo mes no duplica
 * la fila: es la misma idempotencia por índice que ya usa `alertas_enviadas`.
 */
class CapturarMetricasMensuales extends Command
{
    protected $signature = 'metricas:capturar-mes';

    protected $description = 'Guarda la foto del mes que acaba de cerrar para la variación del inicio del panel';

    public function handle(): int
    {
        $mes = Carbon::now()->subMonthNoOverflow()->format('Y-m');

        if (MetricaMensual::query()->where('mes', $mes)->exists()) {
            $this->info($mes.': ya estaba capturado.');

            return self::SUCCESS;
        }

        $empresasActivas = Tenant::query()
            ->deClientes()
            ->where('estado', EstadoDeEmpresa::Activa->value)
            ->count();

        MetricaMensual::create([
            'mes' => $mes,
            'empresas_activas' => $empresasActivas,
            'ingreso_recurrente' => PanelDeSuscripciones::ingresoRecurrenteMensual(),
            'capturado_en' => Carbon::now(),
        ]);

        $this->info($mes.': '.$empresasActivas.' empresas activas capturadas.');

        return self::SUCCESS;
    }
}
