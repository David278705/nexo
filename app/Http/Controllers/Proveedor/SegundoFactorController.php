<?php

namespace App\Http\Controllers\Proveedor;

use App\Autenticacion\LimitadorDeIntentos;
use App\Autenticacion\SegundoFactor;
use App\Autenticacion\SegundoFactorDeLaSesion;
use App\Autorizacion\SeccionDelPanel;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Activar el segundo factor y escribir el código de cada sesión.
 *
 * Las cuatro direcciones viven detrás de `SoloProveedor` pero **delante** de
 * `SegundoFactorVerificado`: son justamente las que hay que poder ver sin
 * haberlo verificado todavía. Cualquier otra dirección del panel desvía aquí.
 *
 * Dos decisiones que conviene no perder:
 *
 * - **No se enseña un código QR.** Dibujarlo pediría una dependencia nueva para
 *   el mismo dato que ya va en el enlace `otpauth://` y en la clave escrita en
 *   grupos de cuatro, que es lo que todas las aplicaciones aceptan a mano.
 * - **El secreto se guarda al enseñarlo, no al confirmarlo.** Si se guardara al
 *   confirmar habría que llevarlo y traerlo por el formulario, y un secreto que
 *   viaja de vuelta desde el navegador es un secreto que el navegador puede
 *   cambiar. La fecha de confirmación es la que decide si está activo.
 */
class SegundoFactorController extends Controller
{
    public function __construct(
        private readonly SegundoFactor $segundoFactor,
        private readonly SegundoFactorDeLaSesion $sesion,
        private readonly LimitadorDeIntentos $limitador,
    ) {}

    /**
     * La pantalla de activación, con la clave que se registra en la aplicación.
     */
    public function activar(Request $peticion): Response|RedirectResponse
    {
        $usuario = $this->usuario($peticion);

        if ($usuario->tieneSegundoFactor()) {
            return redirect()->route(SeccionDelPanel::Inicio->nombreDeRuta());
        }

        $secreto = $usuario->segundo_factor_secreto;

        // Se reutiliza el secreto ya emitido si la pantalla se recarga: emitir
        // uno nuevo en cada visita invalidaría el que la persona acaba de
        // registrar en su teléfono.
        if ($secreto === null || $secreto === '') {
            $secreto = $this->segundoFactor->secretoNuevo();
            $usuario->segundo_factor_secreto = $secreto;
            $usuario->save();
        }

        return Inertia::render('Proveedor/ActivarSegundoFactor', [
            'clave' => $this->segundoFactor->secretoParaEscribirAMano($secreto),
            'enlace' => $this->segundoFactor->enlaceDeAplicacion(
                $usuario->email,
                $secreto,
                (string) config('app.name'),
            ),
        ]);
    }

    /**
     * Confirmar la activación escribiendo el primer código.
     */
    public function confirmar(Request $peticion): RedirectResponse
    {
        $usuario = $this->usuario($peticion);

        if ($usuario->tieneSegundoFactor()) {
            return redirect()->route(SeccionDelPanel::Inicio->nombreDeRuta());
        }

        $secreto = $usuario->segundo_factor_secreto;

        // Sin secreto emitido no hay nada que confirmar: se vuelve a la pantalla
        // de activación, que lo emite.
        if ($secreto === null || $secreto === '') {
            return redirect()->route('proveedor.segundo-factor');
        }

        if (! $this->segundoFactor->verificar($secreto, $this->codigo($peticion))) {
            throw ValidationException::withMessages([
                'codigo' => 'Ese código no corresponde. Escribe el que muestra la aplicación en este momento.',
            ]);
        }

        $usuario->segundo_factor_confirmado_en = Carbon::now();
        $usuario->save();

        // Quien acaba de demostrar que tiene la aplicación no tiene que escribir
        // el código otra vez para entrar.
        $this->sesion->marcarVerificada($usuario);

        return redirect()->route(SeccionDelPanel::Inicio->nombreDeRuta());
    }

    /**
     * La pantalla del código de cada sesión.
     */
    public function verificar(Request $peticion): Response|RedirectResponse
    {
        $usuario = $this->usuario($peticion);

        if (! $usuario->tieneSegundoFactor()) {
            return redirect()->route('proveedor.segundo-factor');
        }

        if ($this->sesion->verificada($usuario)) {
            return redirect()->route(SeccionDelPanel::Inicio->nombreDeRuta());
        }

        return Inertia::render('Proveedor/VerificarSegundoFactor');
    }

    /**
     * Comprobar el código de la sesión.
     *
     * Los intentos se cuentan aparte de los de la contraseña —el ámbito
     * `SEGUNDO_FACTOR` de `LimitadorDeIntentos`— y por un motivo concreto: si
     * compartieran la cuenta, entrar bien con la contraseña la pondría a cero y
     * quien tuviera la contraseña podría probar códigos sin límite.
     */
    public function comprobar(Request $peticion): RedirectResponse
    {
        $usuario = $this->usuario($peticion);

        if (! $usuario->tieneSegundoFactor()) {
            return redirect()->route('proveedor.segundo-factor');
        }

        $ip = $peticion->ip();
        $espera = $this->limitador->segundosDeEspera(
            $usuario->email,
            $ip,
            LimitadorDeIntentos::SEGUNDO_FACTOR,
        );

        if ($espera > 0) {
            throw ValidationException::withMessages([
                'codigo' => $this->limitador->mensajeDeEspera($espera),
            ]);
        }

        $secreto = (string) $usuario->segundo_factor_secreto;

        if (! $this->segundoFactor->verificar($secreto, $this->codigo($peticion))) {
            $espera = $this->limitador->registrarFallo(
                $usuario->email,
                $ip,
                LimitadorDeIntentos::SEGUNDO_FACTOR,
            );

            throw ValidationException::withMessages([
                'codigo' => $espera > 0
                    ? 'Ese código no es correcto. '.$this->limitador->mensajeDeEspera($espera)
                    : 'Ese código no es correcto. Escribe el que muestra la aplicación en este momento.',
            ]);
        }

        $this->limitador->limpiar($usuario->email, $ip, LimitadorDeIntentos::SEGUNDO_FACTOR);
        $this->sesion->marcarVerificada($usuario);

        return redirect()->intended(route(SeccionDelPanel::Inicio->nombreDeRuta()));
    }

    /**
     * El código escrito, ya validado como campo del formulario.
     *
     * La comprobación de que **es el código correcto** la hace `SegundoFactor`;
     * aquí solo se exige que venga algo escrito y que quepa en un código, para
     * no pasarle al verificador el contenido entero de una petición.
     */
    private function codigo(Request $peticion): string
    {
        /** @var array{codigo: string} $datos */
        $datos = $peticion->validate([
            'codigo' => ['required', 'string', 'max:20'],
        ]);

        return $datos['codigo'];
    }

    /**
     * El usuario autenticado. `SoloProveedor` ya garantizó que existe y que es
     * el `owner`; esto es lo que se lo dice al analizador estático.
     */
    private function usuario(Request $peticion): User
    {
        $usuario = $peticion->user();

        if (! $usuario instanceof User) {
            abort(403);
        }

        return $usuario;
    }
}
