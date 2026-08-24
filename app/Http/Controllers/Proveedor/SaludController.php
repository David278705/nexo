<?php

namespace App\Http\Controllers\Proveedor;

use App\Http\Controllers\Controller;
use App\Proveedor\ColaDeTrabajos;
use App\Proveedor\SaludDelSistema;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pantalla «Salud del sistema» del panel del proveedor (documento 4,
 * capítulo 9): el proceso diario, la cola, los errores de aplicación y las
 * copias de seguridad.
 */
class SaludController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Proveedor/Salud', SaludDelSistema::paraLaPantalla());
    }

    /**
     * Reintenta un trabajo fallido de la cola, tal como pide el capítulo 9:
     * «con la posibilidad de reintentarlos».
     */
    public function reintentarTrabajo(string $uuid): RedirectResponse
    {
        ColaDeTrabajos::reintentar($uuid);

        return back()->with('estado', 'Se puso el trabajo de nuevo en la cola.');
    }
}
