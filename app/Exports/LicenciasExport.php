<?php

namespace App\Exports;

use App\Licencias\ListadoDeLicencias;
use App\Models\Licencia;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * La exportación del listado de licencias, ausencias y suspensiones.
 *
 * Recibe el mismo `ListadoDeLicencias` que armó la pantalla y le pide su
 * consulta sin paginar, para que el archivo traiga exactamente lo filtrado.
 *
 * Lleva documento, nombre completo y cargo, las tres columnas obligatorias de
 * toda exportación (documento 1, 11.2).
 *
 * @implements WithMapping<Licencia>
 */
class LicenciasExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly ListadoDeLicencias $listado) {}

    /**
     * @return Collection<int, Licencia>
     */
    public function collection(): Collection
    {
        return $this->listado->consulta()->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Documento',
            'Nombre completo',
            'Cargo',
            'Tipo de novedad',
            'Fecha inicial',
            'Fecha final',
            'Días',
        ];
    }

    /**
     * @param  Licencia  $row
     * @return list<string|int>
     */
    public function map($row): array
    {
        $fila = ListadoDeLicencias::fila($row);

        return [
            (string) $fila['documento'],
            (string) $fila['nombre'],
            (string) $fila['cargo'],
            (string) $fila['tipo'],
            (string) $fila['inicio'],
            (string) $fila['fin'],
            (int) $fila['dias'],
        ];
    }
}
