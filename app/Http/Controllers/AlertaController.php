<?php

namespace App\Http\Controllers;

use App\Alertas\AlertasDelUsuario;
use App\Alertas\ListadoDeAlertas;
use App\Models\AlertaEnviada;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;

/**
 * El panel de alertas (documento 2, sección 5.5).
 *
 * Es dos cosas a la vez y las dos las pide la misma sección:
 *
 * 1. **El registro de envíos consultable.** A quién se le mandó, cuándo, con
 *    qué asunto y con qué resultado, incluidos los intentos y el motivo del
 *    fallo. Es la respuesta a «a mí nunca me llegó», del lado del cliente; la
 *    consola del proveedor, que ve todas las empresas y puede reenviar, es F5-05.
 * 2. **El canal alterno.** «El correo no debería ser el único camino»: el aviso
 *    entero se lee aquí, con el mismo texto que salió.
 *
 * Es de solo lectura y no tiene ni una ruta de escritura, como la auditoría: lo
 * que se enseña es lo que el sistema hizo, y eso no se corrige desde una
 * pantalla.
 *
 * El acceso lo verifica el middleware `modulo:alertas`; qué filas trae, los
 * módulos que el usuario ya tenía (`AlertasDelUsuario`).
 */
class AlertaController extends Controller
{
    public function index(Request $peticion, AlertasDelUsuario $suyas): RespuestaInertia
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User);

        $listado = ListadoDeAlertas::desde($peticion, $suyas->tiposVisiblesPara($usuario));

        return Inertia::render('Alertas/Index', [
            ...$listado->paraLaPantalla(
                'alertas',
                fn (AlertaEnviada $alerta): array => ListadoDeAlertas::fila($alerta),
            ),
            'opciones' => [
                'tipos' => $suyas->opcionesDeTipoPara($usuario),
                'estados' => ListadoDeAlertas::estados(),
                'tamanos' => ListadoDeAlertas::TAMANOS_DE_PAGINA,
            ],
        ]);
    }
}
