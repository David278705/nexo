<?php

namespace App\Http\Controllers\Proveedor;

use App\Autorizacion\SeccionDelPanel;
use App\Http\Controllers\Controller;
use App\Presentacion\Formato;
use App\Proveedor\PanelDeInicio;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las pantallas del panel del proveedor.
 *
 * Quedan aquí el inicio y las secciones que todavía no tienen contenido propio.
 * «Empresas» ya no pasa por aquí —tiene su listado y su ficha en
 * `EmpresaController`—, ni «Catálogos» —los tiene en `CatalogoController`—, ni
 * «Salud» —la tiene en `SaludController` desde F5-06—, pero las direcciones, la
 * puerta del rol y el segundo factor de las que faltan son los definitivos
 * desde ya.
 */
class PanelController extends Controller
{
    public function inicio(): Response
    {
        return Inertia::render('Proveedor/Inicio', [
            'fecha' => Formato::diaCompleto(Carbon::now()),
            ...PanelDeInicio::paraLaPantalla(),
        ]);
    }

    /**
     * Una sección todavía sin contenido.
     *
     * Ya no recibe ninguna acción: la única que había era «crear empresa», que
     * vivía aquí mientras «Empresas» no tenía listado. Ahora lo tiene, y el
     * botón está donde le corresponde.
     */
    public function seccion(SeccionDelPanel $seccion): Response
    {
        return Inertia::render('Proveedor/SeccionPendiente', [
            'titulo' => $seccion->etiqueta(),
            'descripcion' => $seccion->descripcion(),
        ]);
    }
}
