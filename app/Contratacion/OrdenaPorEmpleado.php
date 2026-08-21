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
}
