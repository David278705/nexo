<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolverTenant;
use App\Http\Middleware\VerificarModulo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ResolverTenant va antes que nada que consulte datos de negocio, y
        // después de la sesión, que es de donde sale el usuario autenticado.
        //
        // Se saca `SubstituteBindings` de su sitio y se vuelve a poner detrás a
        // propósito: la resolución de `{empleado}` en la dirección es una
        // consulta a una tabla de negocio, y si corriera antes lo haría sin
        // empresa en contexto. Toda ficha respondería 404, incluida la propia.
        $middleware->web(
            remove: [SubstituteBindings::class],
            append: [
                ResolverTenant::class,
                SubstituteBindings::class,
                HandleInertiaRequests::class,
            ],
        );

        // Se declara en cada ruta de módulo: `->middleware('modulo:vacaciones')`.
        $middleware->alias([
            'modulo' => VerificarModulo::class,
        ]);

        // Las dos direcciones del acceso. Sin esto, Laravel busca la ruta
        // `login`, que en este proyecto se llama `acceso` y está en español.
        $middleware->redirectGuestsTo('/acceso');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
