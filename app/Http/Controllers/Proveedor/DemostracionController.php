<?php

namespace App\Http\Controllers\Proveedor;

use App\Autorizacion\SeccionDelPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerarAccesoDePrueba;
use App\Models\AccesoDePrueba;
use App\Presentacion\Formato;
use App\Proveedor\AccesosDePrueba;
use App\Proveedor\AmbienteDeDemostracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pantalla «Demostración» del panel del proveedor (documento 4, capítulo
 * 10): el estado del ambiente ficticio, su botón de restaurar, y la
 * generación y el listado de accesos de prueba con vigencia limitada.
 *
 * **Ninguna acción de aquí toca la empresa de un cliente.** Las cuatro
 * escriben siempre sobre `AmbienteDeDemostracion::tenant()`, que es la única
 * empresa marcada `es_ambiente_demo`.
 */
class DemostracionController extends Controller
{
    public function index(Request $peticion, AmbienteDeDemostracion $ambiente, AccesosDePrueba $accesos): Response
    {
        $estado = $ambiente->estado();

        return Inertia::render('Proveedor/Demostracion', [
            'restauradoEn' => $estado['restauradoEn'] !== null ? Formato::fechaYHora($estado['restauradoEn']) : null,
            'personas' => $estado['personas'],
            'accesosActivos' => $accesos->activos()
                ->map(fn (AccesoDePrueba $acceso): array => $this->paraLaTabla($acceso))
                ->all(),
            /*
             * El enlace recién generado, para copiarlo. Viaja por la sesión y
             * no por una prop compartida porque solo tiene sentido justo
             * después de generarlo, igual que el informe de la carga inicial.
             */
            'enlaceGenerado' => $peticion->session()->get('enlaceGenerado'),
        ]);
    }

    /**
     * El botón «Restaurar ahora»: la misma rutina que corre cada noche el
     * comando `demostracion:restaurar`, disparada a mano.
     */
    public function restaurar(AmbienteDeDemostracion $ambiente): RedirectResponse
    {
        $ambiente->restaurar();

        return $this->volver()->with(
            'estado',
            'El ambiente de demostración quedó restaurado: vuelve a lucir con la planta de siempre.',
        );
    }

    public function generarAcceso(GenerarAccesoDePrueba $peticion, AccesosDePrueba $accesos): RedirectResponse
    {
        $token = $accesos->generar($peticion->nombreProspecto(), $peticion->diasDeVigencia());

        return $this->volver()->with('enlaceGenerado', url('/prueba/'.$token));
    }

    public function revocarAcceso(AccesoDePrueba $acceso, AccesosDePrueba $accesos): RedirectResponse
    {
        $accesos->revocar($acceso);

        return $this->volver()->with(
            'estado',
            'El acceso de '.$acceso->getAttribute('nombre_prospecto').' quedó revocado: el enlace ya no sirve.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paraLaTabla(AccesoDePrueba $acceso): array
    {
        return [
            'id' => $acceso->getKey(),
            'nombreProspecto' => $acceso->getAttribute('nombre_prospecto'),
            'generadoEn' => Formato::fecha($acceso->getAttribute('generado_en')),
            'expiraEn' => Formato::fecha($acceso->getAttribute('expira_en')),
            'diasDeVigencia' => $acceso->getAttribute('dias_de_vigencia'),
            'ultimoUso' => $acceso->getAttribute('ultimo_uso_en') !== null
                ? Formato::fechaYHora($acceso->getAttribute('ultimo_uso_en'))
                : null,
        ];
    }

    private function volver(): RedirectResponse
    {
        return redirect()->route(SeccionDelPanel::Demostracion->nombreDeRuta());
    }
}
