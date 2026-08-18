<?php

namespace App\Catalogos;

use App\Models\Ciudad;
use App\Models\Departamento;
use App\Models\Pais;

/**
 * Lo que necesita un formulario para resolver la regla 5.1.1: país siempre de
 * lista, y departamento y ciudad de listas encadenadas solo si el país es
 * Colombia.
 *
 * Vive aquí y no en cada controlador porque son tres pantallas las que lo
 * piden —registro de empleado, edición y carga inicial— y las tres tienen que
 * ofrecer exactamente las mismas listas.
 *
 * Las ciudades viajan **anidadas dentro de su departamento**, no en una lista
 * plana con una columna de departamento. Es lo que hace que el encadenamiento
 * sea un filtro en el navegador y no una petición al servidor por cada
 * departamento que el usuario prueba: son mil cien municipios, no un millón.
 */
class Ubicaciones
{
    /**
     * Los países, para el primer selector.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function paises(): array
    {
        return Pais::query()
            ->paraElegir()
            ->get(['codigo', 'nombre'])
            ->map(fn (Pais $pais): array => [
                'valor' => (string) $pais->getAttribute('codigo'),
                'texto' => (string) $pais->getAttribute('nombre'),
            ])
            ->all();
    }

    /**
     * Los departamentos de Colombia con sus ciudades dentro.
     *
     * @return list<array{valor: int, texto: string, ciudades: list<array{valor: int, texto: string}>}>
     */
    public static function departamentosConCiudades(): array
    {
        $departamentos = Departamento::query()
            ->paraElegir()
            ->with(['ciudades' => fn ($consulta) => $consulta->where('activo', true)->orderBy('nombre')])
            ->get();

        return $departamentos
            ->map(fn (Departamento $departamento): array => [
                'valor' => (int) $departamento->getKey(),
                'texto' => (string) $departamento->getAttribute('nombre'),
                'ciudades' => $departamento->ciudades
                    ->map(fn (Ciudad $ciudad): array => [
                        'valor' => (int) $ciudad->getKey(),
                        'texto' => (string) $ciudad->getAttribute('nombre'),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Las dos listas juntas, que es como las recibe el componente
     * `SelectorUbicacion`.
     *
     * @return array{paises: list<array{valor: string, texto: string}>, departamentos: list<array{valor: int, texto: string, ciudades: list<array{valor: int, texto: string}>}>, codigoDeColombia: string}
     */
    public static function paraFormulario(): array
    {
        return [
            'paises' => self::paises(),
            'departamentos' => self::departamentosConCiudades(),

            // El componente no puede llevar «CO» escrito dentro: la regla dice
            // «si el país es Colombia», y cuál es Colombia lo decide el
            // servidor, en un solo sitio.
            'codigoDeColombia' => Pais::COLOMBIA,
        ];
    }
}
