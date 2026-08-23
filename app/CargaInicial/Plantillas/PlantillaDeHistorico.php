<?php

namespace App\CargaInicial\Plantillas;

use App\CargaInicial\CatalogosDeCarga;
use App\CargaInicial\Columna;
use App\CargaInicial\FilaTraducida;
use App\CargaInicial\PlantaDeLaEmpresa;
use App\CargaInicial\Plantilla;

/**
 * Lo que comparten las cuatro plantillas de histórico —incapacidades,
 * vacaciones, licencias y movimientos—: que se vinculan por número de documento
 * con la planta ya cargada.
 *
 * Es la dependencia entre plantillas del documento 2, 6.3, vista desde la fila:
 * un histórico de alguien que no está en la plantilla de empleados no se puede
 * cargar, porque no hay a quién colgárselo. El error se escribe una sola vez
 * aquí para que las cuatro digan lo mismo.
 *
 * **Solo cuentan los empleados activos**, que es la regla absoluta de la
 * sección 6.1 del documento 1 y la misma que aplican los cinco formularios de
 * novedad. La plantilla de empleados carga «únicamente las personas vinculadas
 * hoy», así que un documento inactivo aquí solo puede venir de un archivo
 * equivocado.
 */
abstract class PlantillaDeHistorico extends Plantilla
{
    public function completar(FilaTraducida $fila, CatalogosDeCarga $catalogos, PlantaDeLaEmpresa $planta): void
    {
        $documento = $fila->valor('numero_documento');

        if (! is_string($documento)) {
            return;
        }

        $vinculacion = $planta->vinculacionActiva($documento);

        if ($vinculacion === null) {
            $fila->error(
                'Número de documento',
                "No hay ningún empleado activo con el documento {$documento}. Cárguelo primero en la plantilla «Empleados actuales».",
                'vinculacion_id',
            );

            return;
        }

        $fila->poner('vinculacion_id', $vinculacion->getKey());
    }

    protected function documento(): Columna
    {
        return Columna::texto('Número de documento', 'numero_documento');
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglaDelDocumento(): array
    {
        return ['numero_documento' => ['required', 'string']];
    }

    protected function equivalencias(): array
    {
        return ['vinculacion_id' => 'Número de documento'];
    }
}
