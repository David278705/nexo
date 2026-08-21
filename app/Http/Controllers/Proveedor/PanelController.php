<?php

namespace App\Http\Controllers\Proveedor;

use App\Autorizacion\SeccionDelPanel;
use App\Http\Controllers\Controller;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las pantallas del panel del proveedor.
 *
 * Quedan aquí el inicio y las secciones que todavía no tienen contenido propio,
 * que son las de la fase 5. «Empresas» ya no pasa por aquí —tiene su listado y
 * su ficha en `EmpresaController`— ni «Catálogos» —los tiene en
 * `CatalogoController`—, pero las direcciones, la puerta del rol y el segundo
 * factor de las que faltan son los definitivos desde ya.
 *
 * Ninguna pantalla de aquí inventa indicadores. El capítulo 3 del documento 4
 * describe un inicio con clientes en riesgo, ingreso recurrente y correos del
 * mes; ni las suscripciones ni la consola de envíos existen todavía, y un número
 * de mentira en el panel del negocio es peor que un hueco declarado.
 */
class PanelController extends Controller
{
    public function inicio(): Response
    {
        return Inertia::render('Proveedor/Inicio', [
            'fecha' => Formato::diaCompleto(Carbon::now()),
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
