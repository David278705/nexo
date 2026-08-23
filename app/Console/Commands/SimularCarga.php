<?php

namespace App\Console\Commands;

use App\CargaInicial\ArchivoIlegible;
use App\CargaInicial\CatalogoDePlantillas;
use App\CargaInicial\ResultadoDeSimulacion;
use App\CargaInicial\SimulacionDeCarga;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Pasa una plantilla por el modo simulación y enseña el informe (documento 2,
 * 6.1, pasos 2 y 3).
 *
 * Es lo que el proveedor corre con el archivo que le entregó el cliente, antes
 * de que exista ninguna escritura. **No escribe nada, ni siquiera un registro
 * de que se ejecutó**: eso llega con la ejecución de la carga, que es la que
 * tiene algo que registrar.
 *
 * Va en el contexto de una empresa porque las plantillas de histórico se
 * vinculan con la planta ya cargada de esa empresa, y esa planta la resuelve el
 * trait `PerteneceATenant`. Sin contexto no habría con qué comparar un
 * documento y todas las filas dirían que la persona no existe.
 */
class SimularCarga extends Command
{
    protected $signature = 'carga:simular
        {empresa : El identificador o el NIT de la empresa a la que se va a cargar}
        {plantilla : Cuál de las seis plantillas es el archivo}
        {archivo : La ruta del archivo de Excel que entregó el cliente}';

    protected $description = 'Valida una plantilla de carga inicial fila por fila, sin escribir nada';

    public function handle(
        ContextoTenant $contexto,
        CatalogoDePlantillas $catalogo,
        SimulacionDeCarga $simulacion,
    ): int {
        $empresa = self::empresa((string) $this->argument('empresa'));

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

        try {
            $resultado = $contexto->paraTenant(
                $empresa,
                fn (): ResultadoDeSimulacion => $simulacion->ejecutar($plantilla, (string) $this->argument('archivo')),
            );
        } catch (ArchivoIlegible $ilegible) {
            $this->error($ilegible->getMessage());

            return self::FAILURE;
        }

        return $this->informe($resultado);
    }

    private function informe(ResultadoDeSimulacion $resultado): int
    {
        $this->line('Plantilla: '.$resultado->plantilla->nombre());
        $this->line('Filas leídas: '.$resultado->filasLeidas());
        $this->line('Filas correctas: '.$resultado->filasCorrectas());

        if (! $resultado->hayErrores()) {
            $this->info('El archivo está limpio. Nada se ha guardado: esto era la simulación.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Filas con error: '.$resultado->filasConError());

        foreach ($resultado->errores as $error) {
            $this->line('  '.$error->comoTexto());
        }

        $this->newLine();
        $this->line('No se guardó nada. Corrija el archivo con el cliente y vuelva a pasarlo.');

        return self::FAILURE;
    }

    /**
     * La empresa, buscada por identificador o por NIT, igual que en
     * `alertas:prueba`: son las dos formas que se tienen a mano.
     */
    private static function empresa(string $referencia): ?Tenant
    {
        if (ctype_digit($referencia)) {
            $porId = Tenant::query()->find((int) $referencia);

            if ($porId instanceof Tenant) {
                return $porId;
            }
        }

        return Tenant::query()->where('nit', $referencia)->first();
    }
}
