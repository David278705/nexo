<?php

namespace App\Inicio\Indicadores;

use App\Models\Movimiento;
use App\Movimientos\TipoDeMovimiento;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * El bloque «Movimientos» del panel de inicio (documento 1, 12.1, bloque 7).
 *
 * Un solo indicador: el número de aumentos y promociones del mes. El reparto
 * entre los dos tipos es una nota que descompone esa misma cifra, no un
 * indicador nuevo —el documento 12.2 descartó expresamente «la proporción de
 * promociones internas sobre el total», que sí sería una cuenta nueva—.
 */
class IndicadoresDeMovimientos
{
    /**
     * @return array{titulo: string, tarjetas: list<array<string, mixed>>, distribuciones: list<array<string, mixed>>}
     */
    public static function paraLaEmpresa(Carbon $hoy): array
    {
        $movimientos = Movimiento::query()
            ->whereBetween('fecha_efectividad', [$hoy->copy()->startOfMonth(), $hoy])
            ->get();

        $aumentos = $movimientos->where('tipo', TipoDeMovimiento::AumentoSalarial)->count();
        $promociones = $movimientos->where('tipo', TipoDeMovimiento::Promocion)->count();

        return [
            'titulo' => 'Movimientos',
            'tarjetas' => [
                [
                    'valor' => $movimientos->count(),
                    'etiqueta' => 'Aumentos y promociones en '.Formato::mes($hoy),
                    'nota' => ($aumentos === 1 ? '1 aumento' : $aumentos.' aumentos')
                        .' · '.($promociones === 1 ? '1 promoción' : $promociones.' promociones'),
                ],
            ],
            'distribuciones' => [],
        ];
    }
}
