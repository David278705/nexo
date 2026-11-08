<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarcarReclamoEnTramite;
use App\Models\User;
use App\Models\Vinculacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Poner y quitar la leyenda «reclamo en trámite» de un empleado (`PROD-14`).
 *
 * La marca vive en la persona y no en la vinculación: el reclamo es sobre los
 * datos del titular. La auditoría la escribe el observador de siempre, así que
 * quién la puso, cuándo y con qué motivo queda sin escribirlo aquí.
 */
class ReclamoEnTramiteController extends Controller
{
    public function marcar(MarcarReclamoEnTramite $peticion, Vinculacion $empleado): RedirectResponse
    {
        $empleado->persona->forceFill([
            'reclamo_en_tramite_desde' => Carbon::now(),
            'reclamo_en_tramite_motivo' => $peticion->validated('motivo'),
        ])->save();

        return redirect()
            ->route('empleados.ficha', $empleado)
            ->with('estado', 'Quedó registrada la leyenda «reclamo en trámite». Quítala cuando el reclamo se decida.');
    }

    public function quitar(Request $peticion, Vinculacion $empleado): RedirectResponse
    {
        $usuario = $peticion->user();
        abort_unless($usuario instanceof User && $usuario->esSuperadministrador(), 403);

        $empleado->persona->forceFill([
            'reclamo_en_tramite_desde' => null,
            'reclamo_en_tramite_motivo' => null,
        ])->save();

        return redirect()
            ->route('empleados.ficha', $empleado)
            ->with('estado', 'Se quitó la leyenda «reclamo en trámite».');
    }
}
