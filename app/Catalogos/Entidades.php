<?php

namespace App\Catalogos;

use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Catalogo;
use App\Models\Eps;
use App\Models\FondoPension;

/**
 * Las cinco listas de entidades del formulario de empleado —EPS, fondo de
 * pensión, ARL, caja de compensación y banco— tal como las consume un selector.
 *
 * Es a las entidades lo que `Ubicaciones` es a la geografía: el único sitio
 * donde se arman, para que el registro, la edición y la carga inicial ofrezcan
 * exactamente las mismas opciones.
 *
 * Solo se ofrece lo activo. Una EPS liquidada deja de aparecer aquí y sigue
 * leyéndose en los empleados que ya la tenían, que es justo lo que `Catalogo`
 * resuelve con su borrado lógico.
 */
class Entidades
{
    /**
     * @return array{eps: list<array{valor: int, texto: string}>, fondosPension: list<array{valor: int, texto: string}>, arls: list<array{valor: int, texto: string}>, cajasCompensacion: list<array{valor: int, texto: string}>, bancos: list<array{valor: int, texto: string}>}
     */
    public static function paraFormulario(): array
    {
        return [
            'eps' => self::opciones(Eps::class),
            'fondosPension' => self::opciones(FondoPension::class),
            'arls' => self::opciones(Arl::class),
            'cajasCompensacion' => self::opciones(CajaCompensacion::class),
            'bancos' => self::opciones(Banco::class),
        ];
    }

    /**
     * @param  class-string<Catalogo>  $catalogo
     * @return list<array{valor: int, texto: string}>
     */
    private static function opciones(string $catalogo): array
    {
        return $catalogo::query()
            ->paraElegir()
            ->get()
            ->map(fn (Catalogo $entidad): array => [
                'valor' => (int) $entidad->getKey(),
                'texto' => (string) $entidad->getAttribute('nombre'),
            ])
            ->values()
            ->all();
    }
}
