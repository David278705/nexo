<?php

namespace App\Exports;

use App\Models\Vacacion;
use App\Vacaciones\ListadoDeVacaciones;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * La exportación del listado de vacaciones.
 *
 * Recibe el mismo `ListadoDeVacaciones` que armó la pantalla y le pide su
 * consulta sin paginar, para que el archivo traiga exactamente lo filtrado.
 *
 * Lleva documento, nombre completo y cargo, las tres columnas obligatorias de
 * toda exportación (documento 1, 11.2).
 *
 * @implements WithMapping<Vacacion>
 */
class VacacionesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly ListadoDeVacaciones $listado) {}

    /**
     * @return Collection<int, Vacacion>
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
            'Día inicial de vacaciones',
            'Día final de vacaciones',
            'Días',
        ];
    }

    /**
     * @param  Vacacion  $row
     * @return list<string|int>
     */
    public function map($row): array
    {
        $fila = ListadoDeVacaciones::fila($row);

        return [
            (string) $fila['documento'],
            (string) $fila['nombre'],
            (string) $fila['cargo'],
            (string) $fila['inicio'],
            (string) $fila['fin'],
            (int) $fila['dias'],
        ];
    }
}
