<?php

use App\Autorizacion\SeccionDelPanel;
use App\Http\Middleware\BloquearEmpresaSuspendida;
use App\Http\Middleware\CerrarAccesoDePruebaExpirado;
use App\Http\Middleware\EntornoDeSoloLectura;
use App\Http\Middleware\FueraDeUnaVisita;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolverTenant;
use App\Http\Middleware\SegundoFactorVerificado;
use App\Http\Middleware\SoloProveedor;
use App\Http\Middleware\VerificarModulo;
use App\Presentacion\TextoDeError;
use App\Proveedor\RegistroDeErrores;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
                // Antes de resolver el tenant: si la sesión es la de un acceso
                // de prueba ya vencido, la cierra y no hay usuario del que
                // resolver nada.
                CerrarAccesoDePruebaExpirado::class,
                ResolverTenant::class,
                SubstituteBindings::class,
                // Después de resolver el tenant, porque pregunta por la
                // empresa en contexto: si su servicio está suspendido
                // (documento 4, capítulo 6), lo único que se puede pedir es la
                // pantalla que lo explica y cerrar sesión.
                BloquearEmpresaSuspendida::class,
                // La segunda condición no negociable del documento 4, 4.4: si
                // quien pide es el proveedor dentro del entorno de un cliente,
                // solo pasan las peticiones de lectura. Va en la pila y no en
                // cada ruta para que ningún módulo futuro se quede fuera.
                EntornoDeSoloLectura::class,
                HandleInertiaRequests::class,
            ],
        );

        // `modulo` se declara en cada ruta del cliente:
        // `->middleware('modulo:vacaciones')`. Los otros dos son la puerta del
        // panel del proveedor y siempre van en ese orden: primero quién es,
        // después qué le falta.
        $middleware->alias([
            'modulo' => VerificarModulo::class,
            'proveedor' => SoloProveedor::class,
            'segundo-factor' => SegundoFactorVerificado::class,
            // O se está en el panel, o se está dentro del entorno de un
            // cliente (documento 4, 4.4), nunca en los dos a la vez.
            'fuera-de-visita' => FueraDeUnaVisita::class,
        ]);

        // Las dos direcciones del acceso. Sin esto, Laravel busca la ruta
        // `login`, que en este proyecto se llama `acceso` y está en español.
        $middleware->redirectGuestsTo('/acceso');

        // A dónde va quien ya tiene sesión y vuelve a pedir la pantalla de
        // acceso. El `owner` va al panel del proveedor: en el entorno del
        // cliente recibe 403 en todas las secciones, así que mandarlo a la raíz
        // sería mandarlo a un 403.
        $middleware->redirectUsersTo(
            fn (Request $peticion) => $peticion->user()?->esOwner() === true
                ? '/'.SeccionDelPanel::PREFIJO
                : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La salud del sistema del panel del proveedor (F5-06, documento 4,
        // capítulo 9) pide «errores de la aplicación de las últimas 24
        // horas». Laravel ya descarta antes de llegar aquí lo que nunca fue
        // un error de negocio —validación, 404, autorización—, así que lo
        // que entra a `RegistroDeErrores` es justo lo que hay que mirar.
        $exceptions->reportable(function (Throwable $error): void {
            RegistroDeErrores::registrar($error);
        });

        // Una autorización denegada sin `FormRequest::authorize()` ni política
        // trae el mensaje por defecto de Laravel, en inglés: «This action is
        // unauthorized.». Se cambia aquí y no en `TextoDeError`, porque
        // `Handler::prepareException()` ya convirtió la excepción en
        // `AccessDeniedHttpException` para cuando `respond()` la recibe más
        // abajo, y a esa altura ya no queda un `AuthorizationException` que
        // distinguir de un `abort(403, 'motivo de negocio')`.
        $exceptions->map(AuthorizationException::class, fn (AuthorizationException $error): HttpException => new HttpException(
            403, TextoDeError::SIN_PERMISO, $error
        ));

        // El documento 3, 6.3 escribe un texto para «Sin permiso», «Sesión
        // expirada» y «Error inesperado»: sin esto, esas tres respuestas las
        // contestaba la página de Laravel —en inglés la de una autorización
        // sin motivo de negocio, con traza de depuración la de un error
        // real—, que es justo el «mensaje escrito para programadores» que
        // prohíbe F6-02. Se deja fuera el entorno local para no perder la
        // traza mientras se programa; en pruebas sí se aplica, porque si no,
        // no habría cómo comprobar que el texto correcto es el que de verdad
        // se muestra.
        $exceptions->respond(function (Response $respuesta, Throwable $error, Request $peticion) {
            $estado = $respuesta->getStatusCode();

            // El código de estado ya es 419 aquí sea cual sea la excepción de
            // origen: `prepareException()` convierte `TokenMismatchException`
            // en un `HttpException` antes de que `respond()` la reciba.
            if ($estado === 419) {
                return redirect()->route('acceso')->with('estado', TextoDeError::SESION_EXPIRADA);
            }

            if (app()->environment('local')) {
                return $respuesta;
            }

            if ($estado === 403) {
                return Inertia::render('Error', [
                    'status' => 403,
                    'mensaje' => TextoDeError::paraSinPermiso($error),
                ])->toResponse($peticion)->setStatusCode(403);
            }

            if ($estado === 500) {
                return Inertia::render('Error', [
                    'status' => 500,
                    'mensaje' => TextoDeError::ERROR_INESPERADO,
                ])->toResponse($peticion)->setStatusCode(500);
            }

            return $respuesta;
        });
    })->create();
