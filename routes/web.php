<?php

use App\Autorizacion\Modulo;
use App\Catalogos\Ubicaciones;
use App\Http\Controllers\Acceso\InvitacionController;
use App\Http\Controllers\Acceso\RecuperacionController;
use App\Http\Controllers\Acceso\RestablecimientoController;
use App\Http\Controllers\Acceso\SesionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

/*
 * Las cuatro pantallas de acceso son para quien no ha entrado. El middleware
 * `guest` manda al inicio a quien ya tiene sesión: llegar a «iniciar sesión»
 * estando dentro solo confunde.
 */
Route::middleware('guest')->group(function () {
    Route::get('/acceso', [SesionController::class, 'formulario'])->name('acceso');
    Route::post('/acceso', [SesionController::class, 'entrar'])->name('acceso.entrar');

    Route::get('/acceso/recuperar', [RecuperacionController::class, 'formulario'])
        ->name('acceso.recuperar');
    Route::post('/acceso/recuperar', [RecuperacionController::class, 'enviar'])
        ->name('acceso.recuperar.enviar');

    Route::get('/acceso/restablecer/{token}', [RestablecimientoController::class, 'formulario'])
        ->name('acceso.restablecer.formulario');
    Route::post('/acceso/restablecer', [RestablecimientoController::class, 'guardar'])
        ->name('acceso.restablecer');

    Route::get('/invitacion/{token}', [InvitacionController::class, 'formulario'])
        ->name('invitacion.formulario');
    Route::post('/invitacion/{token}', [InvitacionController::class, 'aceptar'])
        ->name('invitacion.aceptar');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [SesionController::class, 'salir'])->name('acceso.salir');

    /*
     * El saludo y la fecha de la sección 5.2. La fecha se arma en el servidor
     * para que salga en la zona horaria de la aplicación —`America/Bogota`— y
     * no en la del navegador de quien mira.
     */
    Route::get('/', fn () => Inertia::render('Inicio', [
        'fecha' => Str::ucfirst(now()->translatedFormat('l, j \d\e F \d\e Y')),
    ]))->name('inicio');

    /*
     * Las secciones del menú (documento 3, tabla 4.2). Todavía están vacías
     * —cada una llega en su tarea de la fase 1 y 2—, pero existen desde ya
     * para que la navegación del armazón sea real y no un menú que no lleva a
     * ninguna parte. Cada una declara su módulo, así que escribir la dirección
     * a mano sin permiso devuelve 403 igual que cuando tengan contenido.
     */
    foreach (Modulo::cases() as $modulo) {
        if ($modulo === Modulo::Inicio) {
            continue;
        }

        Route::get($modulo->url(), fn () => Inertia::render('SeccionPendiente', [
            'titulo' => $modulo->etiqueta(),
        ]))
            ->middleware('modulo:'.$modulo->value)
            ->name($modulo->nombreDeRuta());
    }
});

/*
 * El catálogo del sistema de diseño. No es una pantalla del producto —no lleva
 * datos ni permisos—, es donde se comprueba de un vistazo que los once
 * componentes siguen viéndose como el prototipo. Fuera de producción, porque a
 * un cliente no le sirve de nada.
 */
if (! app()->isProduction()) {
    Route::get('/sistema-de-diseno', fn () => Inertia::render(
        'SistemaDeDiseno',
        // Las ubicaciones van con datos del catálogo real: es la única forma de
        // comprobar que las listas encadenadas se comportan con los mil cien
        // municipios y no solo con tres de mentira.
        Ubicaciones::paraFormulario(),
    ))->name('sistema-de-diseno');
}
