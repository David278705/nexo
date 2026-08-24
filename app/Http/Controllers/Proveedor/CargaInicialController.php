<?php

namespace App\Http\Controllers\Proveedor;

use App\CargaInicial\ArchivoIlegible;
use App\CargaInicial\CargaRechazada;
use App\CargaInicial\EjecucionDeCarga;
use App\CargaInicial\ResultadoDeLaCarga;
use App\CargaInicial\ResultadoDeSimulacion;
use App\CargaInicial\SimulacionDeCarga;
use App\Http\Controllers\Controller;
use App\Http\Requests\CargarPlantilla;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\PuestaEnMarcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;

/**
 * La pantalla de carga inicial del panel del proveedor (documento 2, 6.1).
 *
 * Los seis pasos del flujo técnico, y quién hace cada uno:
 *
 * 1. Entrar a la pantalla de esa empresa — `index()`, y solo el `owner` llega
 *    aquí: el grupo de rutas entero pasa por `auth`, `proveedor` y el segundo
 *    factor.
 * 2. Subir la plantilla y procesarla en modo simulación — `simular()`.
 * 3. Enseñar el resultado con el motivo de cada error y su número de fila — el
 *    informe que la simulación devuelve, que este controlador solo deja pasar.
 * 4. Corregir y repetir, o confirmar — `cargar()`.
 * 5 y 6. La transacción y la constancia — `EjecucionDeCarga`, no este
 *    controlador.
 *
 * **Ninguna acción de aquí escribe en la empresa por su cuenta ni sale del
 * contexto de la elegida.** Las dos entran con `ContextoTenant::paraTenant()`,
 * que es el único camino por el que el `owner` atraviesa empresas: no se salta
 * el aislamiento, se pone dentro de una empresa a la vez.
 *
 * **El archivo no se guarda en ninguna parte.** Vive lo que dura la petición, en
 * el temporal que abre PHP para cualquier subida, y se lee desde ahí. Es el
 * principio del documento 1, 13.1, y lo que obliga a que el archivo se vuelva a
 * mandar al confirmar.
 */
class CargaInicialController extends Controller
{
    public function index(Request $peticion, PuestaEnMarcha $pantalla): RespuestaInertia
    {
        /** @var array<string, mixed>|null $informe */
        $informe = $peticion->session()->get('informe');

        return Inertia::render('Proveedor/PuestaEnMarcha', $pantalla->paraLaPantalla(
            empresa: $peticion->query('empresa') === null ? null : (int) $peticion->query('empresa'),
            plantilla: $peticion->query('plantilla') === null ? null : (string) $peticion->query('plantilla'),
            informe: $informe,
        ));
    }

    /**
     * El paso 2: valida el archivo entero sin escribir nada.
     *
     * Es la acción que el proveedor repite tantas veces como haga falta con el
     * cliente al lado. Por eso vuelve siempre a la misma pantalla, con la
     * empresa y la plantilla ya elegidas: se corrige el archivo, se vuelve a
     * subir y se compara con el informe anterior.
     */
    public function simular(CargarPlantilla $peticion, ContextoTenant $contexto, SimulacionDeCarga $simulacion): RedirectResponse
    {
        $empresa = $peticion->empresa();
        $plantilla = $peticion->plantilla();

        try {
            $resultado = $contexto->paraTenant(
                $empresa,
                fn (): ResultadoDeSimulacion => $simulacion->ejecutar($plantilla, $peticion->rutaDelArchivo()),
            );
        } catch (ArchivoIlegible $ilegible) {
            return $this->volver($empresa, $plantilla->clave())
                ->withErrors(['archivo' => $ilegible->getMessage()]);
        }

        return $this->volver($empresa, $plantilla->clave())
            ->with('informe', $resultado->paraPantalla());
    }

    /**
     * El paso 4: confirma la carga.
     *
     * No recibe el informe que se enseñó, recibe el archivo otra vez, y
     * `EjecucionDeCarga` lo vuelve a validar antes de escribir. Entre validar y
     * confirmar pasan minutos y en esos minutos alguien pudo desactivar una EPS
     * o subir otro archivo; lo que entra es lo que se acaba de comprobar.
     *
     * Los dos rechazos posibles vuelven a la pantalla con su motivo: el archivo
     * todavía con errores —y con el informe, para que se vea dónde— y el
     * histórico que llega antes que la planta.
     */
    public function cargar(CargarPlantilla $peticion, ContextoTenant $contexto, EjecucionDeCarga $ejecucion): RedirectResponse
    {
        $empresa = $peticion->empresa();
        $plantilla = $peticion->plantilla();
        $archivo = $peticion->archivo();

        try {
            $resultado = $contexto->paraTenant(
                $empresa,
                fn (): ResultadoDeLaCarga => $ejecucion->ejecutar(
                    $plantilla,
                    $peticion->rutaDelArchivo(),
                    $archivo->getClientOriginalName(),
                    $peticion->user(),
                ),
            );
        } catch (ArchivoIlegible $ilegible) {
            return $this->volver($empresa, $plantilla->clave())
                ->withErrors(['archivo' => $ilegible->getMessage()]);
        } catch (CargaRechazada $rechazada) {
            return $this->volver($empresa, $plantilla->clave())
                ->with('informe', $rechazada->simulacion?->paraPantalla())
                ->withErrors(['archivo' => $rechazada->getMessage()]);
        }

        return $this->volver($empresa, $plantilla->clave())
            ->with('estado', $resultado->mensaje());
    }

    /**
     * Vuelve a la pantalla con la empresa y la plantilla en la dirección.
     *
     * Van en la dirección y no en el navegador por lo mismo que la pestaña de
     * los catálogos: una puesta en marcha son hasta seis cargas seguidas sobre
     * la misma empresa, y volver a elegirla después de cada una es donde
     * aparecen las cargas hechas a la empresa equivocada.
     */
    private function volver(Tenant $empresa, string $plantilla): RedirectResponse
    {
        return redirect()->route('proveedor.puesta-en-marcha', [
            'empresa' => $empresa->getKey(),
            'plantilla' => $plantilla,
        ]);
    }
}
