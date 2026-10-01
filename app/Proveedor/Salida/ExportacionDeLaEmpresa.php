<?php

namespace App\Proveedor\Salida;

use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\CreacionDeEmpresa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Toda la información de una empresa, en un archivo que se le puede entregar
 * (documento 6, A.4, paso 1: «exportación completa en formato utilizable»).
 *
 * Un ZIP con un CSV por tabla, su certificado de Cámara de Comercio y un
 * `LEEME.txt` que explica qué hay y cómo leerlo. CSV y no Excel a propósito:
 * se escribe fila por fila sin cargar nada en memoria —la auditoría de una
 * empresa con años de uso son decenas de miles de filas— y lo abre cualquier
 * programa, hoy y dentro de veinte años, que es lo que dura su obligación de
 * conservar los registros del SG-SST.
 *
 * Separado por punto y coma y en UTF-8 con BOM: así lo abre bien Excel con la
 * configuración regional de Colombia, que usa la coma para los decimales.
 *
 * **El filtro por `tenant_id` se escribe aquí a mano, y es la excepción
 * deliberada a la regla del proyecto.** Esta clase recorre tablas crudas con el
 * query builder, donde el global scope no existe; en producción la protege RLS,
 * pero en local la base se conecta como superusuario y RLS no aplica. Una
 * exportación no puede llevarse una fila de otra empresa según con qué rol se
 * conecte: por eso el filtro explícito, además del contexto de la empresa.
 */
final class ExportacionDeLaEmpresa
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    /**
     * Arma el archivo y deja la constancia de la entrega en la empresa.
     *
     * @return string La ruta del ZIP, que quien lo descarga borra al terminar.
     */
    public function generar(Tenant $empresa): string
    {
        $carpeta = sys_get_temp_dir().'/nexo-exportacion-'.bin2hex(random_bytes(8));
        mkdir($carpeta);

        $zip = new ZipArchive;
        $rutaDelZip = $carpeta.'.zip';

        if ($zip->open($rutaDelZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de la exportación.');
        }

        $conteo = $this->contexto->paraTenant($empresa, function () use ($empresa, $zip, $carpeta): array {
            $conteo = [];

            foreach (TablasDeLaEmpresa::DEL_CLIENTE as $tabla) {
                $ruta = "{$carpeta}/{$tabla}.csv";
                $conteo[$tabla] = $this->escribirCsv($tabla, (int) $empresa->getKey(), $ruta);
                $zip->addFile($ruta, "{$tabla}.csv");
            }

            // La constancia de la entrega, dentro del contexto para que su
            // línea de auditoría quede en la auditoría de la propia empresa.
            $empresa->forceFill(['datos_exportados_en' => Carbon::now()])->save();

            return $conteo;
        });

        $certificado = $this->agregarCertificado($empresa, $zip);

        $zip->addFromString('LEEME.txt', $this->leeme($empresa, $conteo, $certificado));
        $zip->close();

        array_map('unlink', glob("{$carpeta}/*.csv") ?: []);
        rmdir($carpeta);

        return $rutaDelZip;
    }

    private function escribirCsv(string $tabla, int $empresaId, string $ruta): int
    {
        $columnas = array_values(array_diff(
            Schema::getColumnListing($tabla),
            TablasDeLaEmpresa::COLUMNAS_SECRETAS[$tabla] ?? [],
        ));

        $archivo = fopen($ruta, 'w');

        if ($archivo === false) {
            throw new RuntimeException("No se pudo escribir la tabla {$tabla} de la exportación.");
        }

        fwrite($archivo, "\xEF\xBB\xBF");
        fputcsv($archivo, $columnas, ';', '"', '');

        $filas = 0;

        foreach (DB::table($tabla)->where('tenant_id', $empresaId)->select($columnas)->lazyById(500) as $fila) {
            fputcsv($archivo, array_map(fn (mixed $valor) => $this->celda($valor), (array) $fila), ';', '"', '');
            $filas++;
        }

        fclose($archivo);

        return $filas;
    }

    private function celda(mixed $valor): string
    {
        return match (true) {
            $valor === null => '',
            is_bool($valor) => $valor ? 'true' : 'false',
            is_array($valor), is_object($valor) => (string) json_encode($valor, JSON_UNESCAPED_UNICODE),
            default => (string) $valor,
        };
    }

    private function agregarCertificado(Tenant $empresa, ZipArchive $zip): bool
    {
        if (! $empresa->tieneCertificadoDeCamara()) {
            return false;
        }

        $contenido = Storage::disk(CreacionDeEmpresa::discoDeCertificados())
            ->get((string) $empresa->getAttribute('certificado_camara_ruta'));

        if ($contenido === null) {
            return false;
        }

        $zip->addFromString('certificado-camara-de-comercio/'.$empresa->getAttribute('certificado_camara_nombre'), $contenido);

        return true;
    }

    /**
     * @param  array<string, int>  $conteo
     */
    private function leeme(Tenant $empresa, array $conteo, bool $certificado): string
    {
        $lineas = [
            'INFORMACIÓN COMPLETA DE '.mb_strtoupper((string) $empresa->getAttribute('nombre')),
            'NIT: '.$empresa->getAttribute('nit'),
            'Exportada el: '.Carbon::now()->timezone('America/Bogota')->format('Y-m-d H:i').' (hora de Bogotá)',
            '',
            'Cada archivo .csv es una tabla, con una fila por registro y los nombres de las columnas en la',
            'primera fila. Están separados por punto y coma (;) y en UTF-8, para que Excel los abra bien con',
            'la configuración regional de Colombia. Las fechas van en formato año-mes-día.',
            '',
            'Los registros se relacionan por su columna «id»: por ejemplo, «persona_id» en vinculaciones.csv',
            'es el «id» de la persona en personas.csv.',
            '',
            'Archivos y número de registros:',
        ];

        foreach ($conteo as $tabla => $filas) {
            $lineas[] = sprintf('  %-32s %d', "{$tabla}.csv", $filas);
        }

        $lineas[] = '';
        $lineas[] = $certificado
            ? 'También va el certificado de Cámara de Comercio, en su carpeta.'
            : 'No había certificado de Cámara de Comercio cargado.';
        $lineas[] = '';
        $lineas[] = 'Lo que no va, y por qué:';
        $lineas[] = '  Contraseñas y secretos de acceso de los usuarios: no se entregan a nadie.';

        foreach (TablasDeLaEmpresa::SOLO_ELIMINAR as $tabla => $motivo) {
            $lineas[] = "  {$tabla}: {$motivo}";
        }

        return implode("\r\n", $lineas)."\r\n";
    }
}
