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
 * De momento son el armazón y nada más: la barra lateral con las nueve
 * secciones, el encabezado y una pantalla por sección. El contenido llega en sus
 * tareas —`F1B-02` crear empresa, `F1B-03` listado y ficha, `F1B-04` catálogos, y
 * el resto en la fase 5—, pero las direcciones, la puerta del rol y el segundo
 * factor son los definitivos desde ya.
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

    public function seccion(SeccionDelPanel $seccion): Response
    {
        return Inertia::render('Proveedor/SeccionPendiente', [
            'titulo' => $seccion->etiqueta(),
            'descripcion' => $seccion->descripcion(),
        ]);
    }
}
