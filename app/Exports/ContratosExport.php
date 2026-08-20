<?php

namespace App\Exports;

use App\Contratacion\ListadoDeContratos;
use App\Models\Vinculacion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * La exportación del listado de contratos.
 *
 * Igual que la de empleados: recibe el mismo `ListadoDeContratos` que armó la
 * pantalla y le pide su consulta sin paginar, para que el archivo traiga
 * exactamente lo filtrado y no una lista parecida.
 *
 * Lleva documento, nombre completo y cargo, que son las tres columnas
 * obligatorias de toda exportación del sistema (documento 1, 11.2).
 *
 * @implements WithMapping<Vinculacion>
 */
class ContratosExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly ListadoDeContratos $listado) {}

    /**
     * @return Collection<int, Vinculacion>
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
            'Tipo de contrato',
            'Inicio del periodo vigente',
            'Fecha de vencimiento',
            'Prórrogas acumuladas',
            'Fecha límite de preaviso',
            'Estado',
        ];
    }

    /**
     * @param  Vinculacion  $row
     * @return list<string>
     */
    public function map($row): array
    {
        $fila = ListadoDeContratos::fila($row);

        return [
            (string) $fila['documento'],
            (string) $fila['empleado'],
            (string) $fila['cargo'],
            (string) $fila['contrato'],
            (string) $fila['inicio'],
            (string) $fila['vence'],
            (string) $fila['prorrogas'],
            (string) $fila['preaviso'],
            (string) $fila['estado'],
        ];
    }
}
