<?php

namespace App\Exports;

use App\Contratacion\ListadoDeEmpleados;
use App\Models\Vinculacion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * La exportación del listado de empleados.
 *
 * **Trae exactamente lo filtrado**, ni un registro más: recibe el mismo
 * `ListadoDeEmpleados` que armó la pantalla y le pide su consulta sin paginar.
 * Si la exportación construyera su propia consulta, el día que cambie un filtro
 * habría que acordarse de cambiarlo en dos sitios, y el que se olvide manda al
 * cliente un archivo que no coincide con lo que vio.
 *
 * Las columnas incluyen documento, nombre completo y cargo, que son las tres
 * obligatorias de toda exportación del sistema (documento 3, sección 6.1).
 *
 * @implements WithMapping<Vinculacion>
 */
class EmpleadosExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly ListadoDeEmpleados $listado) {}

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
            // Las tres primeras son obligatorias en toda exportación del
            // sistema (documento 3, 6.1) y no se mueven: celular y correo van
            // al final.
            'Documento',
            'Nombre completo',
            'Cargo',
            'Tipo de contrato',
            'Fecha de ingreso',
            'Celular',
            'Correo electrónico',
            'Estado',
        ];
    }

    /**
     * @param  Vinculacion  $row
     * @return list<string|null>
     */
    public function map($row): array
    {
        $fila = ListadoDeEmpleados::fila($row);

        return [
            (string) $fila['documento'],
            (string) $fila['nombre'],
            (string) $fila['cargo'],
            (string) $fila['contrato'],
            (string) $fila['ingreso'],
            (string) $fila['celular'],
            (string) $fila['correo'],
            (string) $fila['estado'],
        ];
    }
}
