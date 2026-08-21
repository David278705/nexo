<?php

namespace App\Http\Middleware;

use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Autorizacion\SeccionDelPanel;
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
            'secciones' => fn () => $this->secciones($request),
            // Los mensajes de una petición a la siguiente. El de recuperar
            // contraseña es el primero que lo usa.
            'estado' => fn () => $request->session()->get('estado'),
        ];
    }

    /**
     * Las secciones del panel del proveedor, para su barra lateral.
     *
     * Solo llegan al `owner`. Para cualquier otro usuario la prop es nula, y no
     * por prudencia decorativa: el panel es la única pantalla que las usa y a
     * ella no entra nadie más.
     *
     * A diferencia de los módulos del cliente, esta lista no es un permiso: al
     * panel se entra a todo o no se entra. Los dos niveles internos del equipo
     * —completo y soporte— son del capítulo 10 del documento 4 y llegan con la
     * sección «Equipo».
     *
     * @return list<array<string, mixed>>|null
     */
    private function secciones(Request $request): ?array
    {
        $usuario = $request->user();

        if (! $usuario instanceof User || ! $usuario->esOwner()) {
            return null;
        }

        return array_map(fn (SeccionDelPanel $seccion) => [
            'clave' => $seccion->value,
            'etiqueta' => $seccion->etiqueta(),
            'grupo' => $seccion->grupo(),
            'url' => $seccion->url(),
        ], SeccionDelPanel::cases());
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
