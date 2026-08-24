<?php

namespace App\Http\Controllers\Proveedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntrarAlEntornoDelCliente;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitaAlEntorno;
use App\Proveedor\AccesoAlEntorno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Entrar al entorno de un cliente y salir de él (documento 4, 4.4).
 *
 * Son dos direcciones y viven en dos sitios distintos a propósito:
 *
 * - **Entrar** está bajo el prefijo del panel, con su middleware y su segundo
 *   factor: es una acción del proveedor sobre un cliente.
 * - **Salir** está en el entorno del cliente, que es donde se está cuando hace
 *   falta. Si viviera bajo el prefijo del panel no se podría pedir, porque
 *   durante una visita el panel está cerrado, y la visita quedaría abierta para
 *   siempre en el registro.
 *
 * Aquí no se decide nada: quien abre y cierra la visita, escribe la constancia y
 * guarda el identificador en la sesión es `AccesoAlEntorno`.
 */
class EntornoDelClienteController extends Controller
{
    public function __construct(private readonly AccesoAlEntorno $acceso) {}

    /**
     * Abre la visita y deja al `owner` dentro del sistema del cliente.
     *
     * Se va al inicio del entorno del cliente y no a una pantalla del panel:
     * entrar es entrar. El aviso que lo acompaña dice en qué modo se entró,
     * porque la diferencia entre mirar y poder tocar es lo único que hay que
     * tener claro desde el primer segundo.
     */
    public function entrar(EntrarAlEntornoDelCliente $peticion, Tenant $empresa): RedirectResponse
    {
        /** @var User $owner */
        $owner = $peticion->user();

        $visita = $this->acceso->iniciar(
            $empresa,
            $owner,
            $peticion->motivo(),
            $peticion->conEscritura(),
        );

        return redirect('/')->with('estado', $this->confirmacionDeEntrada($empresa, $visita));
    }

    /**
     * Cierra la visita y devuelve al panel, a la ficha de la empresa que se
     * acaba de visitar: es donde se sigue trabajando con ese cliente.
     */
    public function salir(Request $peticion): RedirectResponse
    {
        $visita = $this->acceso->terminar();

        if (! $visita instanceof VisitaAlEntorno) {
            return redirect('/');
        }

        return redirect()
            ->route('proveedor.empresas.ficha', $visita->tenant_id)
            ->with('estado', 'Saliste del entorno del cliente. La visita quedó cerrada en su '
                .'auditoría, con la hora de entrada y la de salida.');
    }

    private function confirmacionDeEntrada(Tenant $empresa, VisitaAlEntorno $visita): string
    {
        $modo = $visita->permiteEscribir()
            ? 'Entraste con la escritura activada, y esa activación quedó registrada aparte.'
            : 'Entraste en modo consulta: aquí no puedes modificar nada.';

        return 'Estás dentro de '.$empresa->getAttribute('nombre').'. El acceso quedó registrado en '
            .'la auditoría de esta empresa. '.$modo;
    }
}
