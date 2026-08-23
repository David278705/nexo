<?php

namespace App\Contratacion;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filtrar un listado por el empleado y por su cargo (documento 1, 11.3).
 *
 * Es el gemelo de `OrdenaPorEmpleado`: allí se ordena por los datos de la
 * persona, aquí se filtra por ellos. Los dos existen por el mismo motivo —el
 * nombre y el documento viven en `personas` y el cargo en `vinculaciones`, así
 * que ningún listado de novedades los tiene en su propia tabla— y los dos
 * distinguen los mismos dos casos: el listado que parte de `vinculaciones` y el
 * que parte de la tabla de la novedad, que tiene un salto más.
 *
 * **El «Empleado» del documento 1 es una caja de búsqueda y no un selector.**
 * Una empresa de doscientas personas produce un desplegable de doscientas
 * líneas, y quien registra una novedad conoce el nombre o la cédula de quien la
 * pide. Es además la misma caja del listado de empleados, así que se busca igual
 * en los cuatro sitios: por nombre completo o por documento, sin importar
 * mayúsculas ni tildes de más.
 *
 * La comparación del nombre se escribe **una sola vez**, en `coincideConLaPersona()`.
 * Dos copias de un `concat_ws` se separan solas el día que aparezca un tercer
 * apellido.
 *
 * Aquí no se escribe ni un `where('tenant_id', ...)`: la búsqueda entra por
 * `whereHas`, así que la persona pasa por el filtro de empresa de su propio
 * trait.
 */
trait FiltraPorEmpleado
{
    /**
     * La búsqueda de un listado cuya tabla de partida es `vinculaciones`
     * —empleados y contratos—.
     *
     * @param  Builder<covariant Model>  $consulta
     */
    protected static function buscarPorNombreODocumento(Builder $consulta, string $termino): void
    {
        $consulta->whereHas(
            'persona',
            fn (Builder $persona) => self::coincideConLaPersona($persona, $termino)
        );
    }

    /**
     * La misma búsqueda, para un listado cuya tabla de partida es la de la
     * novedad —incapacidades, vacaciones, licencias, movimientos o
     * terminaciones—: un salto más, por la vinculación.
     *
     * @param  Builder<covariant Model>  $consulta
     */
    protected static function buscarAlEmpleadoDeLaNovedad(Builder $consulta, string $termino): void
    {
        $consulta->whereHas(
            'vinculacion.persona',
            fn (Builder $persona) => self::coincideConLaPersona($persona, $termino)
        );
    }

    /**
     * El cargo del empleado de la novedad.
     *
     * Es el cargo **vigente** de la vinculación y no el que tenía el día de la
     * novedad, que ninguna de las cinco tablas guarda. Es también el que enseña
     * la columna «Cargo» de la tabla y el que va a la exportación, así que
     * filtrar por «Analista de compras» devuelve exactamente las filas que
     * dicen «Analista de compras».
     *
     * @param  Builder<covariant Model>  $consulta
     */
    protected static function filtrarPorCargoDeLaNovedad(Builder $consulta, string $cargo): void
    {
        $consulta->whereHas(
            'vinculacion',
            fn (Builder $vinculacion) => $vinculacion->where('cargo', $cargo)
        );
    }

    /**
     * Que una persona case con lo escrito en la caja de búsqueda.
     *
     * El nombre se compara concatenado para que «Juan Mejía» encuentre a quien
     * tiene el apellido en otra columna, y con `ilike` para que las mayúsculas
     * no importen.
     *
     * @param  Builder<covariant Model>  $persona
     */
    private static function coincideConLaPersona(Builder $persona, string $termino): void
    {
        $patron = '%'.$termino.'%';

        $persona->where(function (Builder $campos) use ($patron): void {
            $campos
                ->whereRaw(
                    "concat_ws(' ', primer_nombre, segundo_nombre, primer_apellido, segundo_apellido) ilike ?",
                    [$patron]
                )
                ->orWhere('numero_documento', 'ilike', $patron);
        });
    }
}
