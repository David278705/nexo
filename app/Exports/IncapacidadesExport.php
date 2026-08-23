<?php

namespace App\Exports;

use App\Incapacidades\ListadoDeIncapacidades;
use App\Models\Incapacidad;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * La exportación del listado de incapacidades.
 *
 * Recibe el mismo `ListadoDeIncapacidades` que armó la pantalla y le pide su
 * consulta sin paginar, para que el archivo traiga exactamente lo filtrado.
 *
 * Lleva documento, nombre completo y cargo, las tres columnas obligatorias de
 * toda exportación (documento 1, 11.2), más el código de diagnóstico: quien
 * puede exportar ya pasó por `modulo:incapacidades`, el mismo permiso que
 * protege verlo en pantalla.
 *
 * Lleva también la EPS, que el reporte de incapacidades del documento 1, 11.4
 * pide junto al origen, el diagnóstico y los días.
 *
 * @implements WithMapping<Incapacidad>
 */
class IncapacidadesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly ListadoDeIncapacidades $listado) {}

    /**
     * @return Collection<int, Incapacidad>
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
            'Fecha de inicio',
            'Fecha de terminación',
            'Días',
            'Código de diagnóstico',
            'Origen',
            'Prórroga',
            'EPS',
        ];
    }

    /**
     * @param  Incapacidad  $row
     * @return list<string|int>
     */
    public function map($row): array
    {
        $fila = ListadoDeIncapacidades::fila($row);

        return [
            (string) $fila['documento'],
            (string) $fila['nombre'],
            (string) $fila['cargo'],
            (string) $fila['inicio'],
            (string) $fila['fin'],
            (int) $fila['dias'],
            (string) $fila['diagnostico'],
            (string) $fila['origen'],
            (string) $fila['prorroga'],
            (string) $fila['eps'],
        ];
    }
}
