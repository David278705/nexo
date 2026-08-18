<?php

namespace App\Http\Middleware;

use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * La plantilla Blade raíz. Es el único Blade de la interfaz.
     */
    protected $rootView = 'app';

    /**
     * Props compartidas con todas las páginas.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'nombreAplicacion' => config('app.name'),
            'usuario' => $this->usuario($request),
            // Los mensajes de una petición a la siguiente. El de recuperar
            // contraseña es el primero que lo usa.
            'estado' => fn () => $request->session()->get('estado'),
        ];
    }

    /**
     * El usuario autenticado, su empresa y los módulos a los que puede entrar.
     *
     * Los módulos se comparten para que el menú pinte solo lo accesible. No son
     * el control de acceso —ese es el middleware `modulo:`—, pero salen de la
     * misma política, así que menú y servidor nunca dicen cosas distintas.
     *
     * @return array<string, mixed>|null
     */
    private function usuario(Request $request): ?array
    {
        $usuario = $request->user();

        if (! $usuario instanceof User) {
            return null;
        }

        $permitidos = app(PoliticaDeModulos::class)->modulosPermitidos($usuario);

        return [
            'nombre' => $usuario->name,
            'correo' => $usuario->email,
            'rol' => $usuario->rol->value,
            'empresa' => $usuario->tenant?->nombre,
            'nit' => $usuario->tenant?->nit,
            'modulos' => array_map(fn (Modulo $modulo) => [
                'clave' => $modulo->value,
                'etiqueta' => $modulo->etiqueta(),
                'grupo' => $modulo->grupo(),
                'url' => $modulo->url(),
            ], $permitidos),
        ];
    }
}
