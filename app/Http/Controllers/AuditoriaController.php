<?php

namespace App\Http\Controllers;

use App\Auditoria\ListadoDeAuditoria;
use App\Models\Auditoria;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;

/**
 * La pantalla de auditoría (REV-04, documento 1, 8.2).
 *
 * Es de solo lectura, como el panel de alertas: ni una ruta de escritura,
 * porque lo que se enseña es lo que el sistema ya hizo, y eso no se corrige
 * desde una pantalla (documento 2, 7.2). El acceso lo verifica el middleware
 * `modulo:auditoria`.
 */
class AuditoriaController extends Controller
{
    public function index(Request $peticion): RespuestaInertia
    {
        $listado = ListadoDeAuditoria::desde($peticion);

        return Inertia::render('Auditoria/Index', [
            ...$listado->paraLaPantalla(
                'auditoria',
                fn (Auditoria $linea): array => ListadoDeAuditoria::fila($linea),
            ),
            'opciones' => [
                'usuarios' => ListadoDeAuditoria::usuarios(),
                'modulos' => ListadoDeAuditoria::modulos(),
                'acciones' => ListadoDeAuditoria::acciones(),
                'tamanos' => ListadoDeAuditoria::TAMANOS_DE_PAGINA,
            ],
        ]);
    }
}
