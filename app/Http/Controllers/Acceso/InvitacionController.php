<?php

namespace App\Http\Controllers\Acceso;

use App\Autenticacion\Invitaciones;
use App\Autenticacion\RegistroDeAccesos;
use App\Autenticacion\ReglasDeContrasena;
use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Http\Controllers\Controller;
use App\Models\Invitacion;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El primer ingreso de un usuario creado por el superadministrador: define su
 * contraseña y ve a qué módulos tiene acceso.
 *
 * Lo del resumen de módulos no es adorno: es la primera vez que esa persona
 * sabe qué va a poder hacer, y evita la llamada de «entré y no veo vacaciones».
 */
class InvitacionController extends Controller
{
    /**
     * Lo que se responde cuando el enlace ya no sirve. Igual para el vencido,
     * el ya usado y el inventado: quien lo lee no tiene que distinguirlos, y
     * distinguirlos por fuera solo ayudaría a quien prueba tokens a ciegas.
     */
    public const MENSAJE_NO_VIGENTE = 'Esta invitación ya no es válida. Pídele a quien te invitó que te envíe una nueva.';

    public function __construct(
        private readonly Invitaciones $invitaciones,
        private readonly PoliticaDeModulos $politica,
        private readonly ContextoTenant $contexto,
        private readonly RegistroDeAccesos $registro,
    ) {}

    public function formulario(string $token): Response|RedirectResponse
    {
        $invitacion = $this->invitaciones->vigente($token);

        if (! $invitacion instanceof Invitacion) {
            return redirect()->route('acceso')->with('estado', self::MENSAJE_NO_VIGENTE);
        }

        /** @var User $invitado */
        $invitado = $invitacion->usuario()->firstOrFail();

        return Inertia::render('Acceso/AceptarInvitacion', [
            'token' => $token,
            'nombre' => $invitado->name,
            'correo' => $invitado->email,
            'empresa' => $invitado->tenant?->nombre,
            'modulos' => $this->modulosDe($invitado),
        ]);
    }

    public function aceptar(Request $peticion, string $token): RedirectResponse
    {
        $invitacion = $this->invitaciones->vigente($token);

        if (! $invitacion instanceof Invitacion) {
            return redirect()->route('acceso')->with('estado', self::MENSAJE_NO_VIGENTE);
        }

        $datos = $peticion->validate([
            'contrasena' => ReglasDeContrasena::reglas(),
        ]);

        $usuario = $this->invitaciones->aceptar($invitacion, $datos['contrasena']);

        Auth::login($usuario);
        $peticion->session()->regenerate();
        $this->registro->exitoso($peticion, $usuario);

        return redirect()->to($usuario->paginaDeInicio());
    }

    /**
     * Los módulos del invitado, en etiquetas para pantalla.
     *
     * La consulta de `user_modulos` pasa por el aislamiento, y aquí no hay
     * sesión iniciada todavía: sin empresa en contexto el global scope falla
     * cerrado y la lista saldría vacía. `paraTenant()` es la única forma
     * admitida de fijarla fuera del middleware, y la deja como estaba al salir.
     *
     * @return list<string>
     */
    private function modulosDe(User $invitado): array
    {
        $empresa = $invitado->tenant;

        if ($empresa === null) {
            return [];
        }

        $permitidos = $this->contexto->paraTenant(
            $empresa,
            fn () => $this->politica->modulosPermitidos($invitado),
        );

        return array_map(fn (Modulo $modulo) => $modulo->etiqueta(), $permitidos);
    }
}
