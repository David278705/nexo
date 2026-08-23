<?php

namespace App\Inicio\Indicadores;

use App\Contratacion\Genero;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * El bloque «Composición» del panel de inicio (documento 1, 12.1, bloque 6).
 *
 * De los cuatro indicadores del documento, dos están listos —distribución por
 * cargo y por género— y dos están **bloqueados por D.4**, que el documento 6
 * marca expresamente como pendiente y bloqueante: los cortes de los rangos de
 * edad y de antigüedad. Sin ellos no hay en qué grupo contar a nadie, y
 * cualquier corte que se inventara aquí sería exactamente lo que la regla del
 * proyecto prohíbe. La antigüedad **promedio** sí se puede calcular —es un
 * solo número, no un reparto en rangos— y no depende de D.4.
 *
 * Es el mismo patrón que F1B-03 usó con las pestañas del panel del proveedor
 * que dependían de una decisión sin cerrar: la parte que se puede construir se
 * construye entera, y la que no dice de qué depende.
 */
class IndicadoresDeComposicion
{
    /**
     * @return array{titulo: string, tarjetas: list<array<string, mixed>>, distribuciones: list<array<string, mixed>>, avisos: list<string>}
     */
    public static function paraLaEmpresa(Carbon $hoy): array
    {
        return [
            'titulo' => 'Composición',
            'tarjetas' => [
                [
                    'valor' => self::antiguedadPromedio($hoy),
                    'etiqueta' => 'Antigüedad promedio de la planta',
                ],
            ],
            'distribuciones' => [
                ['titulo' => 'Distribución por cargo', 'items' => self::distribucionPorCargo()],
                ['titulo' => 'Distribución por género', 'items' => self::distribucionPorGenero()],
            ],
            // Los rangos de edad y de antigüedad no se calculan: D.4 sigue sin
            // resolver los cortes.
            'avisos' => [
                'Distribución por rango de edad y por rangos de antigüedad: pendiente de la decisión D.4 '
                    .'del documento de decisiones pendientes — faltan los cortes de cada rango.',
            ],
        ];
    }

    /**
     * @return list<array{etiqueta: string, cuantos: int}>
     */
    private static function distribucionPorCargo(): array
    {
        return Vinculacion::query()
            ->activas()
            ->selectRaw('cargo, count(*) as cuantos')
            ->groupBy('cargo')
            ->orderByDesc('cuantos')
            ->get()
            ->map(fn (Vinculacion $fila): array => [
                'etiqueta' => (string) $fila->getAttribute('cargo'),
                'cuantos' => (int) $fila->getAttribute('cuantos'),
            ])
            ->all();
    }

    /**
     * @return list<array{etiqueta: string, cuantos: int}>
     */
    private static function distribucionPorGenero(): array
    {
        $porGenero = Vinculacion::query()
            ->activas()
            ->join('personas', 'personas.id', '=', 'vinculaciones.persona_id')
            ->selectRaw('personas.genero, count(*) as cuantos')
            ->groupBy('personas.genero')
            ->pluck('cuantos', 'genero');

        $lista = array_map(
            fn (Genero $genero): array => [
                'etiqueta' => $genero->etiqueta(),
                'cuantos' => (int) ($porGenero[$genero->value] ?? 0),
            ],
            Genero::cases(),
        );

        // Lo que quede sin clasificar es quien todavía no tiene género
        // digitado: la columna admite nulos (F1-04). Se suma sin filtrar por
        // clave para no depender de si el motor de base de datos devuelve el
        // grupo nulo como `null` o como cadena vacía.
        $sinDato = (int) $porGenero->sum() - array_sum(array_column($lista, 'cuantos'));

        if ($sinDato > 0) {
            $lista[] = ['etiqueta' => 'Sin dato', 'cuantos' => $sinDato];
        }

        return $lista;
    }

    /**
     * La antigüedad promedio de la planta activa, en meses cumplidos y escrita
     * como se lee en una frase.
     *
     * Es aritmética de fechas —`MotorDeCalculo::mesesCumplidos()` por persona,
     * promediada—, no una consulta que recorra el histórico de nadie: cada
     * vinculación aporta un solo número, sin tocar vacaciones, licencias ni
     * incapacidades, así que doscientos empleados no cuestan más que doscientas
     * restas de fechas.
     */
    private static function antiguedadPromedio(Carbon $hoy): string
    {
        $ingresos = Vinculacion::query()->activas()->pluck('fecha_ingreso');

        if ($ingresos->isEmpty()) {
            return Formato::VACIO;
        }

        $promedio = $ingresos
            ->map(fn (Carbon $ingreso): int => MotorDeCalculo::mesesCumplidos($ingreso, $hoy))
            ->avg();

        return Formato::tiempoTranscurrido((int) round($promedio));
    }
}
