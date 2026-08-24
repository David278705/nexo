<?php

namespace App\Http\Controllers\Acceso;

use App\Http\Controllers\Controller;
use App\Models\AccesoDePrueba;
use App\Proveedor\AccesosDePrueba;
use App\Proveedor\AmbienteDeDemostracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * El enlace de un acceso de prueba con vigencia limitada (documento 4,
 * capítulo 10 y 12.1). A diferencia de una invitación, no pide contraseña: el
 * prospecto entra directo al ambiente de demostración con la cuenta que
 * comparten todos los accesos de prueba.
 *
 * **No es de un solo uso.** Un acceso de prueba dura varios días y el enlace
 * sirve todas las veces que haga falta mientras no venza ni se revoque; lo
 * que se registra en cada entrada es solo `ultimo_uso_en`.
 *
 * **La vigencia se comprueba aquí y de nuevo en cada petición.** Aquí decide
 * si el enlace todavía sirve para entrar; `CerrarAccesoDePruebaExpirado` es
 * quien cierra la sesión que ya entró el día en que el acceso vence a la
 * mitad de una visita.
 */
class AccesoDePruebaController extends Controller
{
    /**
     * Igual para el vencido, el revocado y el inventado: quien lo lee no
     * tiene que distinguirlos.
     */
    public const MENSAJE_NO_VIGENTE = 'Este enlace de demostración ya no es válido. '
        .'Pide uno nuevo a quien te lo compartió.';

    public function __construct(
        private readonly AccesosDePrueba $accesos,
        private readonly AmbienteDeDemostracion $ambiente,
    ) {}

    public function entrar(Request $peticion, string $token): RedirectResponse
    {
        $acceso = $this->accesos->vigente($token);

        if (! $acceso instanceof AccesoDePrueba) {
            return redirect()->route('acceso')->with('estado', self::MENSAJE_NO_VIGENTE);
        }

        Auth::guard('web')->logout();
        $peticion->session()->invalidate();

        Auth::login($this->ambiente->usuarioDeDemostracion());
        $peticion->session()->regenerate();
        $peticion->session()->put(
            'acceso_de_prueba_expira_en',
            $acceso->getAttribute('expira_en')->toIso8601String(),
        );

        $this->accesos->registrarUso($acceso);

        return redirect()->route('inicio');
    }
}
