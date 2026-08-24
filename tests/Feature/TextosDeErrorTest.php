<?php

use App\Presentacion\TextoDeError;
use App\Proveedor\AccesoAlEntorno;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

/**
 * Los tres textos del documento 3, 6.3 que antes de F6-02 contestaba
 * Laravel y no el sistema: «Sin permiso», «Sesión expirada» y «Error
 * inesperado». `bootstrap/app.php` es quien decide cuál se muestra; estas
 * rutas de prueba solo fuerzan cada situación.
 *
 * Van en el grupo `web` para pasar por la misma pila de middleware que
 * cualquier ruta real, incluida `HandleInertiaRequests`. El primer segmento
 * es «prueba-de-error» y no «prueba» a propósito: `routes/web.php` ya tiene
 * `/prueba/{token}` para el acceso de demostración, y colisionar con ella
 * mandaría estas peticiones a otro controlador.
 */
function registrarRutaDeError(string $caso, Closure $accion): void
{
    Route::middleware('web')->get("/prueba-de-error/{$caso}", $accion);
}

it('un 403 sin mensaje de negocio muestra el texto genérico del documento 3', function () {
    registrarRutaDeError('403-generico', fn () => abort(403));

    $this->get('/prueba-de-error/403-generico')
        ->assertForbidden()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Error')
            ->where('status', 403)
            ->where('mensaje', TextoDeError::SIN_PERMISO));
});

it('un 403 con mensaje de negocio muestra ese mensaje y no el genérico', function () {
    registrarRutaDeError('403-negocio', fn () => abort(403, AccesoAlEntorno::SOLO_LECTURA));

    $this->get('/prueba-de-error/403-negocio')
        ->assertForbidden()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Error')
            ->where('mensaje', AccesoAlEntorno::SOLO_LECTURA));
});

it('una autorización denegada muestra el texto genérico y no el de Laravel en inglés', function () {
    registrarRutaDeError('403-autorizacion', function (): never {
        throw new AuthorizationException;
    });

    $this->get('/prueba-de-error/403-autorizacion')
        ->assertForbidden()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Error')
            ->where('mensaje', TextoDeError::SIN_PERMISO));
});

it('un error real no muestra su detalle interno ni una traza de depuración', function () {
    registrarRutaDeError('500', function (): never {
        throw new RuntimeException('Detalle interno que nadie debe leer');
    });

    $this->get('/prueba-de-error/500')
        ->assertServerError()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Error')
            ->where('status', 500)
            ->where('mensaje', TextoDeError::ERROR_INESPERADO))
        ->assertDontSee('Detalle interno que nadie debe leer');
});

it('una sesión vencida vuelve a la pantalla de acceso con su propio texto', function () {
    registrarRutaDeError('419', function (): never {
        throw new TokenMismatchException;
    });

    $this->get('/prueba-de-error/419')->assertRedirect(route('acceso'));

    expect(session('estado'))->toBe(TextoDeError::SESION_EXPIRADA);
});
