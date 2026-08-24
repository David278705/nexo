<?php

namespace App\Console\Commands;

use App\CargaInicial\ArchivoIlegible;
use App\CargaInicial\CargaRechazada;
use App\CargaInicial\CatalogoDePlantillas;
use App\CargaInicial\EjecucionDeCarga;
use App\CargaInicial\ResultadoDeLaCarga;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Ejecuta de verdad una plantilla de carga inicial (documento 2, 6.1, pasos 4
 * a 6).
 *
 * Es la gemela de `carga:simular` y la diferencia es toda: esta escribe. Por eso
 * pide confirmación antes de arrancar y por eso vuelve a validar el archivo ella
 * misma —`EjecucionDeCarga` no se fía de ningún informe anterior— en vez de
 * recibir el resultado de la simulación que se corrió hace un rato.
 *
 * Existe además de la pantalla porque la carga es trabajo de puesta en marcha y
 * a veces se hace con el servidor delante: un archivo de mil filas por
 * formulario web es una petición larga y frágil, y por consola no lo es.
 */
class EjecutarCarga extends Command
{
    protected $signature = 'carga:ejecutar
        {empresa : El identificador o el NIT de la empresa a la que se va a cargar}
        {plantilla : Cuál de las seis plantillas es el archivo}
        {archivo : La ruta del archivo de Excel que entregó el cliente}';

    protected $description = 'Carga una plantilla ya validada dentro de una transacción: o entra todo o no entra nada';

    public function handle(
        ContextoTenant $contexto,
        CatalogoDePlantillas $catalogo,
        EjecucionDeCarga $ejecucion,
    ): int {
        $empresa = Tenant::query()->find((int) $this->argument('empresa'))
            ?? Tenant::query()->where('nit', (string) $this->argument('empresa'))->first();

        if (! $empresa instanceof Tenant) {
            $this->error('No hay ninguna empresa con ese identificador ni con ese NIT.');

            return self::FAILURE;
        }

        try {
            $plantilla = $catalogo->de((string) $this->argument('plantilla'));
        } catch (InvalidArgumentException $desconocida) {
            $this->error($desconocida->getMessage());

            return self::FAILURE;
        }

        $ruta = (string) $this->argument('archivo');

        $this->line('Empresa: '.$empresa->getAttribute('nombre'));
        $this->line('Plantilla: '.$plantilla->nombre());

        if (! $this->confirm('Esto escribe en la base de datos. ¿Ejecutar la carga?', false)) {
            $this->line('No se hizo nada.');

            return self::SUCCESS;
        }

        try {
            $resultado = $contexto->paraTenant(
                $empresa,
                fn (): ResultadoDeLaCarga => $ejecucion->ejecutar($plantilla, $ruta, basename($ruta)),
            );
        } catch (ArchivoIlegible $ilegible) {
            $this->error($ilegible->getMessage());

            return self::FAILURE;
        } catch (CargaRechazada $rechazada) {
            return $this->rechazo($rechazada);
        }

        $this->info($resultado->mensaje());

        return self::SUCCESS;
    }

    private function rechazo(CargaRechazada $rechazada): int
    {
        $this->error($rechazada->getMessage());

        foreach ($rechazada->simulacion->errores ?? [] as $error) {
            $this->line('  '.$error->comoTexto());
        }

        return self::FAILURE;
    }
}
