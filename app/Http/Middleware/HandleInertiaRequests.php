<?php

namespace App\Http\Middleware;

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
        ];
    }
}
