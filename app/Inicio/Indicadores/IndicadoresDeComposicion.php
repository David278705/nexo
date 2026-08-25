<?php

namespace App\Inicio\Indicadores;

use App\Contratacion\Genero;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * El bloque «Composición» del panel de inicio (documento 1, 12.1, bloque 6).
 *
 * Los cuatro indicadores del documento, completos: distribución por cargo,
 * por género, por rango de edad y por rango de antigüedad, más la antigüedad
 * **promedio** como tarjeta aparte —es un solo número, no un reparto—.
 *
 * Los cortes de edad y de antigüedad son la decisión **D.4** de
 * `docs/06-decisiones-pendientes.md`, resuelta el 2026-08-25 tomando la
 * propuesta que el propio documento ya traía escrita: no eran una regla legal
 * que inventar, eran los tramos de un gráfico. Viven aquí, en dos constantes,
 * y no en `config/` porque —a diferencia de los umbrales de `config/alertas.php`—
 * no son un número que Angela vaya a querer ajustar sin desplegar código: son
 * la forma del gráfico, y cambiarla es una decisión de producto, no de
 * operación diaria.
 */
class IndicadoresDeComposicion
{
    /**
     * Los tramos de edad de D.4, en años cumplidos. `null` como límite
     * superior es «55 o más».
     *
     * @var list<array{etiqueta: string, desde: int, hasta: int|null}>
     */
    private const RANGOS_DE_EDAD = [
        ['etiqueta' => 'Menos de 25', 'desde' => 0, 'hasta' => 24],
        ['etiqueta' => '25 a 34', 'desde' => 25, 'hasta' => 34],
        ['etiqueta' => '35 a 44', 'desde' => 35, 'hasta' => 44],
        ['etiqueta' => '45 a 54', 'desde' => 45, 'hasta' => 54],
        ['etiqueta' => '55 o más', 'desde' => 55, 'hasta' => null],
    ];

    /**
     * Los tramos de antigüedad de D.4, en meses cumplidos desde el ingreso.
     *
     * @var list<array{etiqueta: string, desde: int, hasta: int|null}>
     */
    private const RANGOS_DE_ANTIGUEDAD = [
        ['etiqueta' => 'Menos de 6 meses', 'desde' => 0, 'hasta' => 5],
        ['etiqueta' => '6 meses a 1 año', 'desde' => 6, 'hasta' => 11],
        ['etiqueta' => '1 a 3 años', 'desde' => 12, 'hasta' => 35],
        ['etiqueta' => '3 a 5 años', 'desde' => 36, 'hasta' => 59],
        ['etiqueta' => 'Más de 5 años', 'desde' => 60, 'hasta' => null],
    ];

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
                ['titulo' => 'Distribución por rango de edad', 'items' => self::distribucionPorEdad($hoy)],
                ['titulo' => 'Distribución por rango de antigüedad', 'items' => self::distribucionPorAntiguedad($hoy)],
            ],
            'avisos' => [],
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
     * @return list<array{etiqueta: string, cuantos: int}>
     */
    private static function distribucionPorEdad(Carbon $hoy): array
    {
        $edades = Vinculacion::query()
            ->activas()
            ->join('personas', 'personas.id', '=', 'vinculaciones.persona_id')
            ->pluck('personas.fecha_nacimiento')
            ->map(fn (mixed $nacimiento): int => intdiv(
                MotorDeCalculo::mesesCumplidos(Carbon::parse($nacimiento), $hoy),
                12,
            ));

        return self::distribucionPorRango($edades, self::RANGOS_DE_EDAD);
    }

    /**
     * @return list<array{etiqueta: string, cuantos: int}>
     */
    private static function distribucionPorAntiguedad(Carbon $hoy): array
    {
        $meses = Vinculacion::query()
            ->activas()
            ->pluck('fecha_ingreso')
            ->map(fn (mixed $ingreso): int => MotorDeCalculo::mesesCumplidos(Carbon::parse($ingreso), $hoy));

        return self::distribucionPorRango($meses, self::RANGOS_DE_ANTIGUEDAD);
    }

    /**
     * Cuenta una colección de números —edades en años, antigüedad en meses—
     * dentro de los tramos de D.4. Es la misma pregunta para las dos
     * distribuciones, y una sola función evita que sus reglas de borde —el
     * `hasta` nulo del último tramo— se escriban dos veces y se separen solas.
     *
     * @param  Collection<int, int>  $valores
     * @param  list<array{etiqueta: string, desde: int, hasta: int|null}>  $rangos
     * @return list<array{etiqueta: string, cuantos: int}>
     */
    private static function distribucionPorRango(Collection $valores, array $rangos): array
    {
        return array_map(
            fn (array $rango): array => [
                'etiqueta' => $rango['etiqueta'],
                'cuantos' => $valores->filter(
                    fn (int $valor): bool => $valor >= $rango['desde']
                        && ($rango['hasta'] === null || $valor <= $rango['hasta']),
                )->count(),
            ],
            $rangos,
        );
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
