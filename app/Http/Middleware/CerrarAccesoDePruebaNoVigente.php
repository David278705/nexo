<?php

namespace App\Http\Middleware;

use App\Models\AccesoDePrueba;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de quien entró con un acceso de prueba que ya no está
 * vigente (documento 4, capítulo 10).
 *
 * `AccesoDePruebaController` comprueba la vigencia una sola vez, al entrar. Una
 * visita puede durar más que el acceso mismo —el prospecto la deja abierta
 * varios días, o David lo revoca mientras está dentro—, así que hace falta
 * comprobarlo en cada petición y no solo esa vez.
 *
 * **Se comprueba contra la fila y no contra una fecha copiada a la sesión.** La
 * primera versión guardaba `expira_en` al entrar y solo miraba eso, así que
 * revocar cortaba el enlace para quien todavía no había entrado y no hacía nada
 * con la visita ya abierta: el panel decía «revocado» y el prospecto seguía
 * dentro hasta el vencimiento. Lo que se guarda ahora es el identificador, y las
 * dos mitades de `AccesoDePrueba::estaVigente()` —ni revocado, ni vencido— se
 * responden con el mismo método en los dos sitios.
 *
 * Una sesión de un usuario normal nunca trae esa clave, así que para el resto
 * del sistema este middleware no hace nada y no consulta nada.
 */
class CerrarAccesoDePruebaNoVigente
{
    /**
     * La marca que `AccesoDePruebaController` deja en la sesión al entrar.
     */
    public const CLAVE = 'acceso_de_prueba';

    public const VENCIDO = 'El acceso de demostración venció. '
        .'Pide uno nuevo si quieres seguir explorando.';

    public const REVOCADO = 'El acceso de demostración se cerró. '
        .'Pide uno nuevo a quien te lo compartió si quieres seguir explorando.';

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $identificador = $peticion->session()->get(self::CLAVE);

        if (! is_int($identificador)) {
            return $siguiente($peticion);
        }

        $acceso = AccesoDePrueba::query()->find($identificador);

        if ($acceso instanceof AccesoDePrueba && $acceso->estaVigente()) {
            return $siguiente($peticion);
        }

        return $this->cerrar($peticion, $acceso);
    }

    /**
     * Una fila que ya no está —borrada a mano en la base de datos— se trata
     * como revocada: lo que hay que decir es que el acceso se cerró, no
     * inventar un vencimiento que nadie puede comprobar.
     */
    private function cerrar(Request $peticion, ?AccesoDePrueba $acceso): Response
    {
        Auth::guard('web')->logout();
        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        $vencido = $acceso instanceof AccesoDePrueba
            && $acceso->getAttribute('revocado_en') === null;

        return redirect()->route('acceso')->with(
            'estado',
            $vencido ? self::VENCIDO : self::REVOCADO,
        );
    }
}
