<?php

namespace App\Http\Controllers;

use App\Autorizacion\Modulo;
use App\Exports\IncapacidadesExport;
use App\Http\Requests\EditarIncapacidad;
use App\Http\Requests\RegistrarIncapacidad;
use App\Incapacidades\ListadoDeIncapacidades;
use App\Incapacidades\OrigenIncapacidad;
use App\Models\Incapacidad;
use App\Novedades\DetalleDeNovedad;
use App\Novedades\PeriodoDigitado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El primer módulo de novedades (documento 1, 6.3).
 *
 * El acceso lo verifica el middleware `modulo:incapacidades`, que es también
 * el único permiso que exige el código de diagnóstico: quien llega aquí ya lo
 * tiene, así que la fila no recorta nada.
 */
class IncapacidadController extends Controller
{
    public function index(Request $peticion): RespuestaInertia
    {
        $listado = ListadoDeIncapacidades::desde($peticion);

        return Inertia::render('Incapacidades/Index', [
            ...$listado->paraLaPantalla(
                'incapacidades',
                fn (Incapacidad $incapacidad): array => ListadoDeIncapacidades::fila($incapacidad),
            ),
            'opciones' => [
                'origenes' => OrigenIncapacidad::paraElegir(),
                'eps' => ListadoDeIncapacidades::opcionesDeEps(),
                'tamanos' => ListadoDeIncapacidades::TAMANOS_DE_PAGINA,
            ],
            /*
             * Los días de la incapacidad que se está registrando, calculados
             * por el motor y ya escritos como «3 días». El modal los pide de
             * nuevo con una recarga parcial cada vez que cambia una fecha; aquí
             * no se resta nada.
             */
            'diasDeIncapacidad' => Inertia::optional(fn (): ?string => PeriodoDigitado::diasCorridos($peticion)),
            /*
             * La incapacidad que se está editando o que se va a eliminar
             * (F2-10). La pantalla la pide al pulsar Editar o Eliminar.
             */
            DetalleDeNovedad::PROP => DetalleDeNovedad::prop($peticion, Incapacidad::class),
        ]);
    }

    /**
     * El archivo con lo que está filtrado en pantalla.
     */
    public function exportar(Request $peticion): BinaryFileResponse
    {
        $listado = ListadoDeIncapacidades::desde($peticion);

        return Excel::download(
            new IncapacidadesExport($listado),
            'incapacidades-'.Carbon::now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * Registra la incapacidad (documento 1, 6.3).
     *
     * El empleado y las reglas de formato las comprueba `RegistrarIncapacidad`.
     * Aquí no se valida ni se calcula nada: se guarda y se vuelve al listado,
     * que es donde el usuario va a registrar la siguiente o a comprobar que
     * esta quedó.
     */
    public function guardar(RegistrarIncapacidad $peticion): RedirectResponse
    {
        $vinculacion = $peticion->vinculacion();

        $vinculacion->incapacidades()->create([
            'fecha_inicio' => $peticion->validated('fecha_inicio'),
            'fecha_fin' => $peticion->validated('fecha_fin'),
            'codigo_diagnostico' => $peticion->validated('codigo_diagnostico'),
            'es_prorroga' => $peticion->esProrroga(),
            'eps_id' => $peticion->validated('eps_id'),
            'origen' => $peticion->validated('origen'),
        ]);

        // El texto literal del documento 3, sección 6.3.
        return redirect()
            ->route(Modulo::Incapacidades->nombreDeRuta())
            ->with('estado', 'Incapacidad registrada.');
    }

    /**
     * Corrige una incapacidad ya registrada (documento 1, 6.2): «cualquier
     * registro de novedad puede modificarse, sin importar su antigüedad».
     *
     * Los días no se guardan en ninguna columna, así que corregir una fecha
     * los recalcula solo. El empleado no se toca: lo explica
     * `EditarIncapacidad`.
     */
    public function editar(EditarIncapacidad $peticion, Incapacidad $incapacidad): RedirectResponse
    {
        $incapacidad->update([
            'fecha_inicio' => $peticion->validated('fecha_inicio'),
            'fecha_fin' => $peticion->validated('fecha_fin'),
            'codigo_diagnostico' => $peticion->validated('codigo_diagnostico'),
            'es_prorroga' => $peticion->esProrroga(),
            'eps_id' => $peticion->validated('eps_id'),
            'origen' => $peticion->validated('origen'),
        ]);

        return redirect()
            ->route(Modulo::Incapacidades->nombreDeRuta())
            ->with('estado', 'Incapacidad actualizada.');
    }

    /**
     * Elimina la incapacidad (documento 1, 6.2). Es borrado lógico —la regla 4
     * del proyecto— y la auditoría lo registra sola.
     */
    public function eliminar(Incapacidad $incapacidad): RedirectResponse
    {
        $incapacidad->delete();

        return redirect()
            ->route(Modulo::Incapacidades->nombreDeRuta())
            ->with('estado', 'Incapacidad eliminada.');
    }
}
