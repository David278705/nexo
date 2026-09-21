<?php

namespace App\Http\Controllers\Acceso;

use App\Autenticacion\LimitadorDeIntentos;
use App\Autenticacion\RegistroDeAccesos;
use App\Autorizacion\SeccionDelPanel;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entrar y salir del sistema.
 *
 * Las tres cosas que esta pantalla tiene que hacer bien, según la sección 5.1
 * del documento de diseño y la 7.1 del de arquitectura:
 *
 * 1. **Un solo mensaje de error, que no diga si el correo existe.** Contestar
 *    «ese correo no está registrado» le regala a cualquiera la lista de quién
 *    trabaja en la empresa.
 * 2. **Espera creciente tras varios fallos**, que la resuelve
 *    `LimitadorDeIntentos`.
 * 3. **Todo intento queda registrado**, el bueno y los malos.
 */
class SesionController extends Controller
{
    public function __construct(
        private readonly LimitadorDeIntentos $limitador,
        private readonly RegistroDeAccesos $registro,
    ) {}

    public function formulario(): Response
    {
        return Inertia::render('Acceso/IniciarSesion');
    }

    public function entrar(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'correo' => ['required', 'string', 'email'],
            'contrasena' => ['required', 'string'],
            'recordarme' => ['boolean'],
        ]);

        $correo = mb_strtolower(trim($datos['correo']));
        $ip = $peticion->ip();

        // El usuario se busca antes de comprobar nada para poder registrar el
        // intento con su empresa. Que exista no cambia ni una palabra de lo que
        // se le responde a quien está al otro lado.
        $usuario = User::query()->where('email', $correo)->first();

        $espera = $this->limitador->segundosDeEspera($correo, $ip);

        if ($espera > 0) {
            $this->registro->bloqueado($peticion, $correo, $usuario);

            throw ValidationException::withMessages([
                'correo' => $this->limitador->mensajeDeEspera($espera),
            ]);
        }

        $credenciales = ['email' => $correo, 'password' => $datos['contrasena']];

        if (! Auth::attempt($credenciales, (bool) ($datos['recordarme'] ?? false))) {
            $this->registro->fallido($peticion, $correo, $usuario);
            $espera = $this->limitador->registrarFallo($correo, $ip);

            throw ValidationException::withMessages([
                'correo' => $espera > 0
                    ? 'Correo o contraseña incorrectos. '.$this->limitador->mensajeDeEspera($espera)
                    : 'Correo o contraseña incorrectos.',
            ]);
        }

        /** @var User $autenticado */
        $autenticado = Auth::user();

        $peticion->session()->regenerate();
        $this->limitador->limpiar($correo, $ip);
        $this->registro->exitoso($peticion, $autenticado);

        $this->olvidarPretendidaInalcanzable($peticion, $autenticado);

        return redirect()->intended($this->destino($autenticado));
    }

    public function salir(Request $peticion): RedirectResponse
    {
        Auth::guard('web')->logout();

        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return redirect()->route('acceso');
    }

    /**
     * A dónde va cada quien después de entrar.
     *
     * El `owner` no tiene nada que hacer en el entorno del cliente —ahí recibe
     * 403 en todas las secciones— y su sitio es el panel del proveedor. Mandarlo
     * al inicio del cliente sería mandarlo a un 403 recién iniciada la sesión.
     */
    private function destino(User $usuario): string
    {
        return $usuario->esOwner()
            ? route(SeccionDelPanel::Inicio->nombreDeRuta())
            : route('inicio');
    }

    /**
     * Descarta la dirección que se intentó visitar antes de entrar, cuando
     * quien acaba de entrar no puede llegar a ella.
     *
     * `redirect()->intended()` prefiere esa dirección y solo cae en `destino()`
     * si no hay ninguna, así que el cuidado que `destino()` ya tenía —no mandar
     * al `owner` al entorno del cliente— se saltaba justo en el caso más común:
     * quien abre la raíz del sistema, recibe el desvío a la pantalla de acceso
     * y entra desde ahí. Esa visita deja `/` guardada, y el `owner` terminaba
     * en el 403 de `SoloCliente` recién iniciada la sesión, en vez de en su
     * panel.
     *
     * La regla es simétrica y por eso se escribe una sola vez: **la dirección
     * pretendida sobrevive solo si pertenece al mundo de quien entró.** Al
     * `owner`, el panel; a quien es de una empresa, todo lo que no sea el
     * panel. Al revés, cada uno aterriza en un 403 que no explica nada y que
     * parece un fallo del sistema, que es justamente como se reportó.
     */
    private function olvidarPretendidaInalcanzable(Request $peticion, User $usuario): void
    {
        $pretendida = $peticion->session()->get('url.intended');

        if (! is_string($pretendida)) {
            return;
        }

        if ($this->esDelPanel($pretendida) !== $usuario->esOwner()) {
            $peticion->session()->forget('url.intended');
        }
    }

    /**
     * Si una dirección cae dentro del panel del proveedor.
     *
     * Se mira solo la ruta, no el anfitrión ni la consulta: lo que decide es
     * el prefijo `/proveedor`, que es el mismo con el que el grupo de rutas
     * declara todo el panel.
     */
    private function esDelPanel(string $direccion): bool
    {
        $ruta = parse_url($direccion, PHP_URL_PATH);

        if (! is_string($ruta)) {
            return false;
        }

        $ruta = '/'.ltrim($ruta, '/');
        $prefijo = '/'.SeccionDelPanel::PREFIJO;

        return $ruta === $prefijo || str_starts_with($ruta, $prefijo.'/');
    }
}
