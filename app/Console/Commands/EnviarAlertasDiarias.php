<?php

namespace App\Console\Commands;

use App\Dominio\Alertas\ProcesoDiarioDeAlertas;
use App\Dominio\Alertas\RegistroDeEjecuciones;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * El comando del proceso diario de alertas.
 *
 * Es una cáscara a propósito: todo lo que decide está en
 * `ProcesoDiarioDeAlertas`, que es lo que se prueba. Un comando con lógica
 * dentro solo se puede probar arrancando un comando.
 *
 * `--fecha` existe para dos cosas legítimas y ninguna es saltarse el
 * calendario: reponer un día en que el servidor estuvo caído, y mirar en un
 * ambiente de pruebas qué saldría un día concreto. No hay riesgo de duplicar
 * nada al usarlo, porque la clave de idempotencia lleva justamente esa fecha.
 *
 * La duración se mide aquí y no dentro de `ProcesoDiarioDeAlertas` porque es
 * lo único que ese objeto no puede saber de sí mismo: un cronómetro no es una
 * regla de negocio. `RegistroDeEjecuciones` deja la constancia que pide la
 * salud del sistema del panel (F5-06, documento 4, capítulo 9).
 */
class EnviarAlertasDiarias extends Command
{
    protected $signature = 'alertas:diarias {--fecha= : El día que se evalúa, en formato Y-m-d. Por defecto, hoy.}';

    protected $description = 'Evalúa las reglas de alerta de cada empresa activa y encola los correos que corresponda';

    public function handle(ProcesoDiarioDeAlertas $proceso): int
    {
        $fecha = $this->option('fecha');
        $dia = is_string($fecha) && $fecha !== '' ? Carbon::parse($fecha) : Carbon::today();

        $inicio = hrtime(true);
        $resumen = $proceso->correr($dia);
        $duracionMs = (int) ((hrtime(true) - $inicio) / 1_000_000);

        RegistroDeEjecuciones::registrar($dia, $resumen, $duracionMs);

        $this->info($dia->toDateString().': '.$resumen->resumen());

        foreach ($resumen->fallidas() as $fallida) {
            $this->error($fallida['empresa'].': '.$fallida['motivo']);
        }

        // Una empresa con error no tumba el proceso, pero sí tiene que
        // notarse: el código de salida es lo que mira el programador de tareas
        // y lo que ve la salud del sistema del panel.
        return $resumen->fallidas() === [] ? self::SUCCESS : self::FAILURE;
    }
}
