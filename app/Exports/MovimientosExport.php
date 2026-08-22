<?php

namespace App\Exports;

use App\Models\Movimiento;
use App\Movimientos\ListadoDeMovimientos;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * La exportación del listado de aumentos y promociones.
 *
 * Recibe el mismo `ListadoDeMovimientos` que armó la pantalla y le pide su
 * consulta sin paginar, para que el archivo traiga exactamente lo filtrado.
 *
 * Lleva documento, nombre completo y cargo, las tres columnas obligatorias de
 * toda exportación (documento 1, 11.2), y es el reporte «Movimientos
 * salariales» del catálogo de la sección 11.4: «con salario y cargo anterior y
 * nuevo».
 *
 * @implements WithMapping<Movimiento>
 */
class MovimientosExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly ListadoDeMovimientos $listado) {}

    /**
     * @return Collection<int, Movimiento>
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
            'Tipo de movimiento',
            'Cargo anterior',
            'Cargo nuevo',
            'Salario anterior',
            'Salario nuevo',
            'Efectivo desde',
        ];
    }

    /**
     * @param  Movimiento  $row
     * @return list<string>
     */
    public function map($row): array
    {
        $fila = ListadoDeMovimientos::fila($row);

        return [
            (string) $fila['documento'],
            (string) $fila['nombre'],
            (string) $fila['cargo'],
            (string) $fila['tipo'],
            (string) $fila['cargoAnterior'],
            (string) $fila['cargoNuevo'],
            (string) $fila['salarioAnterior'],
            (string) $fila['salarioNuevo'],
            (string) $fila['efectivo'],
        ];
    }
}
