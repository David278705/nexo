<?php

namespace App\Exports;

use App\Models\Terminacion;
use App\Terminaciones\ListadoDeTerminaciones;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * La exportación del listado de terminaciones.
 *
 * Recibe el mismo `ListadoDeTerminaciones` que armó la pantalla y le pide su
 * consulta sin paginar, para que el archivo traiga exactamente lo filtrado.
 *
 * Lleva documento, nombre completo y cargo, las tres columnas obligatorias de
 * toda exportación (documento 1, 11.2), y la categoría de rotación, que es lo
 * que convierte este archivo en la base del indicador de la sección 12.
 *
 * @implements WithMapping<Terminacion>
 */
class TerminacionesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly ListadoDeTerminaciones $listado) {}

    /**
     * @return Collection<int, Terminacion>
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
            'Fecha de salida',
            'Tipo de salida',
            'Motivo de la baja',
            'Rotación',
            'Observaciones',
        ];
    }

    /**
     * @param  Terminacion  $row
     * @return list<string>
     */
    public function map($row): array
    {
        $fila = ListadoDeTerminaciones::fila($row);

        return [
            (string) $fila['documento'],
            (string) $fila['nombre'],
            (string) $fila['cargo'],
            (string) $fila['salida'],
            (string) $fila['tipo'],
            (string) $fila['motivo'],
            (string) $fila['rotacion'],
            (string) $fila['observaciones'],
        ];
    }
}
