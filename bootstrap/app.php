<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolverTenant;
use App\Http\Middleware\VerificarModulo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ResolverTenant va antes que nada que consulte datos de negocio, y
        // después de la sesión, que es de donde sale el usuario autenticado.
        $middleware->web(append: [
            ResolverTenant::class,
            HandleInertiaRequests::class,
        ]);

        // Se declara en cada ruta de módulo: `->middleware('modulo:vacaciones')`.
        $middleware->alias([
            'modulo' => VerificarModulo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
