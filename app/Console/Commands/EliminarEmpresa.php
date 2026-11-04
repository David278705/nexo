<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Presentacion\Formato;
use App\Proveedor\Salida\EliminacionDeLaEmpresa;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Elimina para siempre los datos de una empresa que se fue (documento 6, A.4).
 *
 * Es un comando y no un botón a propósito: nadie lo ejecuta de un clic por
 * error. Además de los candados de `EliminacionDeLaEmpresa`, pide escribir el
 * NIT para confirmar, y al terminar imprime la constancia de eliminación que se
 * le remite al cliente (A.4, paso 4).
 */
class EliminarEmpresa extends Command
{
    protected $signature = 'empresa:eliminar {nit : El NIT de la empresa, tal como está registrado}';

    protected $description = 'Elimina para siempre los datos de una empresa que se fue, ya entregados (A.4)';

    public function handle(EliminacionDeLaEmpresa $eliminacion): int
    {
        $nit = trim((string) $this->argument('nit'));
        $empresa = Tenant::query()->where('nit', $nit)->first();

        if (! $empresa instanceof Tenant) {
            $this->error("No hay ninguna empresa con el NIT {$nit}.");

            return self::FAILURE;
        }

        $impedimento = $eliminacion->impedimento($empresa);

        if ($impedimento !== null) {
            $this->error('No se puede eliminar: '.$impedimento);

            return self::FAILURE;
        }

        $nombre = (string) $empresa->getAttribute('nombre');
        $exportada = $empresa->getAttribute('datos_exportados_en');

        $this->warn("Vas a eliminar para siempre los datos de {$nombre} (NIT {$nit}).");
        $this->line('Su información se le entregó el '.Formato::fecha($exportada).'. Esto no se puede deshacer.');

        $confirmacion = trim((string) $this->ask('Para confirmar, escribe el NIT de la empresa'));

        if ($confirmacion !== $nit) {
            $this->error('El NIT no coincide. No se eliminó nada.');

            return self::FAILURE;
        }

        $conteo = $eliminacion->eliminar($empresa);

        $this->newLine();
        $this->line($this->constancia($nombre, $nit, $exportada, $conteo));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $conteo
     */
    private function constancia(string $nombre, string $nit, mixed $exportada, array $conteo): string
    {
        $lineas = [
            'CONSTANCIA DE ELIMINACIÓN DE DATOS',
            "Empresa: {$nombre}",
            "NIT: {$nit}",
            'Información entregada el: '.Formato::fecha($exportada),
            'Datos eliminados el: '.Carbon::now()->timezone('America/Bogota')->format('Y-m-d H:i').' (hora de Bogotá)',
            '',
            'Registros eliminados de la base de datos de producción:',
        ];

        foreach ($conteo as $tabla => $filas) {
            $lineas[] = sprintf('  %-28s %d', $tabla, $filas);
        }

        $lineas[] = '';
        $lineas[] = 'Se conservan: la aceptación de los documentos legales, el registro de alertas sin datos personales, '
            .'las suscripciones y los pagos, y las constancias de la entrega y de esta eliminación.';
        $lineas[] = 'Esta constancia quedó guardada en el sistema.';
        $lineas[] = 'Las copias de seguridad automáticas que todavía la contengan se eliminan solas al cumplirse su periodo de retención.';

        return implode(PHP_EOL, $lineas);
    }
}
