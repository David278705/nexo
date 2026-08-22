<?php

namespace App\Contratacion;

use App\Models\Persona;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ordenar un listado de vinculaciones por datos de la persona.
 *
 * Documento y nombre viven en `personas`, así que se ordenan por subconsulta en
 * vez de por `join`: un `join` duplicaría filas si mañana la relación dejara de
 * ser una a una y, sobre todo, obligaría a calificar cada columna con su tabla
 * en el resto de la consulta.
 *
 * Lo usan los dos listados que enseñan personas —empleados y contratos—, que
 * ordenan el nombre exactamente igual: por primer apellido y luego por primer
 * nombre, que es el orden en que se lee una lista de gente.
 *
 * Los listados de novedades ordenan por lo mismo, pero su tabla de partida no
 * es `vinculaciones` sino la de la novedad, así que hay un salto más y para eso
 * está el segundo par de métodos. La única diferencia entre los cinco es el
 * nombre de esa tabla, y por eso viaja como argumento en vez de copiarse la
 * subconsulta en cada listado.
 */
trait OrdenaPorEmpleado
{
    /**
     * @param  Builder<Vinculacion>  $consulta
     */
    protected static function porNombreDeLaPersona(Builder $consulta, string $sentido): void
    {
        $consulta
            ->orderBy(self::dePersona('primer_apellido'), $sentido)
            ->orderBy(self::dePersona('primer_nombre'), $sentido);
    }

    /**
     * @return Builder<Persona>
     */
    protected static function dePersona(string $columna): Builder
    {
        return Persona::query()
            ->select($columna)
            ->whereColumn('personas.id', 'vinculaciones.persona_id');
    }

    /**
     * El mismo orden, para un listado cuya tabla de partida es la de la novedad
     * —incapacidades, vacaciones, licencias, movimientos o terminaciones— y no
     * `vinculaciones`.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $consulta
     */
    protected static function porNombreDeLaPersonaDeLaNovedad(Builder $consulta, string $sentido, string $tabla): void
    {
        $consulta
            ->orderBy(self::dePersonaDeLaNovedad('primer_apellido', $tabla), $sentido)
            ->orderBy(self::dePersonaDeLaNovedad('primer_nombre', $tabla), $sentido);
    }

    /**
     * El nombre de una persona a través de la vinculación de la novedad.
     *
     * Es un salto más que `dePersona()`: allí la tabla de partida ya es
     * `vinculaciones`, y aquí es la de la novedad, así que la subconsulta pasa
     * por las dos con un `join` en vez de un `whereColumn` directo.
     *
     * `$tabla` es siempre un literal escrito en el listado que llama, nunca algo
     * que venga de la dirección.
     *
     * @return Builder<Persona>
     */
    protected static function dePersonaDeLaNovedad(string $columna, string $tabla): Builder
    {
        return Persona::query()
            ->select($columna)
            ->join('vinculaciones', 'vinculaciones.persona_id', '=', 'personas.id')
            ->whereColumn('vinculaciones.id', $tabla.'.vinculacion_id');
    }
}
