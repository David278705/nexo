<?php

namespace App\Http\Controllers\Proveedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarCiudad;
use App\Http\Requests\GuardarEntidad;
use App\Http\Requests\GuardarFestivo;
use App\Models\Catalogo;
use App\Models\Ciudad;
use App\Models\Festivo;
use App\Proveedor\CatalogosDelSistema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;

/**
 * Los catálogos del sistema, desde el panel del proveedor (documento 4,
 * capítulo 8).
 *
 * Una sola pantalla con cinco pestañas y siete acciones de escritura. Aquí no se
 * valida ni se decide nada: la validación son los tres `FormRequest` y lo que
 * cada pestaña enseña lo arma `CatalogosDelSistema`. Este controlador traduce la
 * petición en props y devuelve a la pestaña de la que se venía.
 *
 * **Ninguna acción borra.** Desactivar y activar son la misma acción con el
 * valor contrario, y quitar un festivo es un borrado lógico desde que esta
 * tarea le puso `deleted_at` a la tabla. Todo queda además en la auditoría, que
 * la escribe sola el observador: son datos que ven todos los clientes a la vez y
 * hay que poder responder quién cambió qué.
 */
class CatalogoController extends Controller
{
    public function index(Request $peticion): RespuestaInertia
    {
        $catalogos = CatalogosDelSistema::desde(
            pestana: $peticion->query('pestana') === null ? null : (string) $peticion->query('pestana'),
            entidad: $peticion->query('entidad') === null ? null : (string) $peticion->query('entidad'),
            departamento: $peticion->query('departamento') === null ? null : (int) $peticion->query('departamento'),
            anio: $peticion->query('anio') === null ? null : (int) $peticion->query('anio'),
        );

        return Inertia::render('Proveedor/Catalogos', $catalogos->paraLaPantalla());
    }

    /**
     * Da de alta una entidad en una de las cinco listas.
     *
     * Es el «Hecho cuando» de la tarea: agregar una EPS no requiere desplegar
     * código. Nace activa, así que se ofrece en el formulario de empleado de
     * todas las empresas en la petición siguiente.
     */
    public function crearEntidad(GuardarEntidad $peticion, string $catalogo): RedirectResponse
    {
        $modelo = CatalogosDelSistema::modeloDe($catalogo);
        $entidad = $modelo::create($peticion->entidad());

        return $this->volver(
            ['pestana' => 'entidades', 'entidad' => $catalogo],
            CatalogosDelSistema::confirmacionDeAlta((string) $entidad->getAttribute('nombre')),
        );
    }

    public function editarEntidad(GuardarEntidad $peticion, string $catalogo, int $entidad): RedirectResponse
    {
        $registro = $this->entidad($catalogo, $entidad);
        $registro->update($peticion->entidad());

        return $this->volver(
            ['pestana' => 'entidades', 'entidad' => $catalogo],
            CatalogosDelSistema::confirmacionDeEdicion((string) $registro->getAttribute('nombre')),
        );
    }

    /**
     * Desactiva la entidad, o la vuelve a activar.
     *
     * No hay ruta para borrarla y no es un olvido: hay empleados apuntando a
     * ella, y un catálogo que se borra deja fichas enseñando un hueco donde
     * había una EPS.
     */
    public function alternarEntidad(string $catalogo, int $entidad): RedirectResponse
    {
        $registro = $this->entidad($catalogo, $entidad);
        $activo = ! ($registro->getAttribute('activo') === true);

        $registro->update(['activo' => $activo]);

        return $this->volver(
            ['pestana' => 'entidades', 'entidad' => $catalogo],
            CatalogosDelSistema::confirmacionDeEstado((string) $registro->getAttribute('nombre'), $activo),
        );
    }

    public function crearCiudad(GuardarCiudad $peticion): RedirectResponse
    {
        $ciudad = Ciudad::create($peticion->ciudad());

        return $this->volver(
            ['pestana' => 'ciudades', 'departamento' => $ciudad->getAttribute('departamento_id')],
            CatalogosDelSistema::confirmacionDeAlta((string) $ciudad->getAttribute('nombre')),
        );
    }

    public function editarCiudad(GuardarCiudad $peticion, Ciudad $ciudad): RedirectResponse
    {
        $ciudad->update($peticion->ciudad());

        return $this->volver(
            ['pestana' => 'ciudades', 'departamento' => $ciudad->getAttribute('departamento_id')],
            CatalogosDelSistema::confirmacionDeEdicion((string) $ciudad->getAttribute('nombre')),
        );
    }

    public function alternarCiudad(Ciudad $ciudad): RedirectResponse
    {
        $activo = ! ($ciudad->getAttribute('activo') === true);

        $ciudad->update(['activo' => $activo]);

        return $this->volver(
            ['pestana' => 'ciudades', 'departamento' => $ciudad->getAttribute('departamento_id')],
            CatalogosDelSistema::confirmacionDeEstado((string) $ciudad->getAttribute('nombre'), $activo),
        );
    }

    /**
     * Agrega un festivo al calendario.
     *
     * La escritura la hace el modelo y no este método: la fecha es única y el
     * borrado es lógico, así que cargar una fecha que ya estuvo tiene que
     * revivir la fila anterior en vez de chocar con el índice.
     */
    public function crearFestivo(GuardarFestivo $peticion): RedirectResponse
    {
        $fecha = $peticion->fecha();

        Festivo::registrar($fecha, $peticion->nombre());

        return $this->volver(
            ['pestana' => 'festivos', 'anio' => $fecha->year],
            CatalogosDelSistema::confirmacionDeFestivo($fecha, $peticion->nombre()),
        );
    }

    /**
     * Quita un festivo mal cargado. Borrado lógico, como todo en el sistema.
     */
    public function quitarFestivo(Festivo $festivo): RedirectResponse
    {
        $fecha = $festivo->fecha;

        $festivo->delete();

        return $this->volver(
            ['pestana' => 'festivos', 'anio' => $fecha->year],
            CatalogosDelSistema::confirmacionDeFestivoQuitado($fecha),
        );
    }

    /**
     * La entidad de esa lista, o 404.
     *
     * El identificador y la lista llegan por separado en la dirección, así que
     * sin esta búsqueda acotada bastaría cambiar un número a mano para editar un
     * banco desde la dirección de las EPS.
     */
    private function entidad(string $catalogo, int $entidad): Catalogo
    {
        $modelo = CatalogosDelSistema::modeloDe($catalogo);

        /** @var Catalogo $registro */
        $registro = $modelo::query()->findOrFail($entidad);

        return $registro;
    }

    /**
     * Vuelve a la pestaña desde la que se guardó, con la confirmación.
     *
     * Guardar y aparecer en otra pestaña obligaría a navegar de nuevo hasta
     * donde se estaba después de cada alta, y estas listas se cargan de a
     * varias seguidas.
     *
     * @param  array<string, mixed>  $donde
     */
    private function volver(array $donde, string $confirmacion): RedirectResponse
    {
        return redirect()
            ->route('proveedor.catalogos', $donde)
            ->with('estado', $confirmacion);
    }
}
