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

    /**
     * El nombre con el que el cliente conoce esa plantilla.
     *
     * Un registro de carga guarda la clave, no el nombre, y quien lee el
     * historial del panel espera «Histórico de incapacidades» y no
     * «incapacidades». Devuelve la clave tal cual si alguna vez deja de existir:
     * un historial es una constancia y no puede dejar de enseñarse porque el
     * catálogo cambiara después.
     */
    public function nombreDe(string $clave): string
    {
        foreach ($this->todas() as $plantilla) {
            if ($plantilla->clave() === $clave) {
                return $plantilla->nombre();
            }
        }

        return $clave;
    }

    /**
     * Las seis plantillas tal como las consume un selector, en el orden de
     * carga.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public function paraElegir(): array
    {
        return array_map(
            fn (Plantilla $plantilla): array => [
                'valor' => $plantilla->clave(),
                'texto' => $plantilla->nombre(),
            ],
            $this->todas(),
        );
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
