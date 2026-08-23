<?php

namespace App\CargaInicial;

use App\CargaInicial\Plantillas\PlantillaDeEmpleados;
use App\CargaInicial\Plantillas\PlantillaDeIncapacidades;
use App\CargaInicial\Plantillas\PlantillaDeLicencias;
use App\CargaInicial\Plantillas\PlantillaDeMovimientos;
use App\CargaInicial\Plantillas\PlantillaDePerfilSociodemografico;
use App\CargaInicial\Plantillas\PlantillaDeVacaciones;
use InvalidArgumentException;

/**
 * Las seis plantillas de `plantillas/`, **en el orden en que se cargan**.
 *
 * El orden no es decorativo: es la dependencia del documento 2, 6.3. Primero
 * empleados y solo después las cinco que se vinculan por número de documento.
 * Que la lista esté escrita en ese orden es lo que permitirá a la ejecución de
 * la carga comprobarlo sin una segunda lista que mantener al día.
 */
class CatalogoDePlantillas
{
    /**
     * @return list<Plantilla>
     */
    public function todas(): array
    {
        return [
            new PlantillaDeEmpleados,
            new PlantillaDePerfilSociodemografico,
            new PlantillaDeIncapacidades,
            new PlantillaDeVacaciones,
            new PlantillaDeLicencias,
            new PlantillaDeMovimientos,
        ];
    }

    /**
     * @return list<string>
     */
    public function claves(): array
    {
        return array_map(fn (Plantilla $plantilla): string => $plantilla->clave(), $this->todas());
    }

    public function de(string $clave): Plantilla
    {
        foreach ($this->todas() as $plantilla) {
            if ($plantilla->clave() === $clave) {
                return $plantilla;
            }
        }

        throw new InvalidArgumentException(
            "No existe la plantilla «{$clave}». Las que hay son: ".implode(', ', $this->claves()).'.'
        );
    }
}
