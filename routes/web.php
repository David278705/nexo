<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\SeccionDelPanel;
use App\Catalogos\Ubicaciones;
use App\Http\Controllers\Acceso\InvitacionController;
use App\Http\Controllers\Acceso\RecuperacionController;
use App\Http\Controllers\Acceso\RestablecimientoController;
use App\Http\Controllers\Acceso\SesionController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\Proveedor\EmpresaController;
use App\Http\Controllers\Proveedor\PanelController;
use App\Http\Controllers\Proveedor\SegundoFactorController;
use App\Presentacion\Formato;
use Illuminate\Support\Facades\Route;
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
        'fecha' => Formato::diaCompleto(now()),
    ]))->name('inicio');

    /*
     * Las secciones del menú (documento 3, tabla 4.2). Todavía están vacías
     * —cada una llega en su tarea de la fase 1 y 2—, pero existen desde ya
     * para que la navegación del armazón sea real y no un menú que no lleva a
     * ninguna parte. Cada una declara su módulo, así que escribir la dirección
     * a mano sin permiso devuelve 403 igual que cuando tengan contenido.
     */
    /*
     * Empleados (documento 3, sección 5.3). Es el primer módulo con contenido
     * real, así que sale del bucle de secciones pendientes de abajo.
     *
     * Las tres direcciones literales van antes que la de la ficha: si no,
     * `exportar` y `nuevo` se leerían como identificadores de empleado.
     */
    Route::middleware('modulo:'.Modulo::Empleados->value)->group(function () {
        Route::get('/empleados', [EmpleadoController::class, 'index'])
            ->name(Modulo::Empleados->nombreDeRuta());
        Route::get('/empleados/exportar', [EmpleadoController::class, 'exportar'])
            ->name('empleados.exportar');
        Route::get('/empleados/nuevo', [EmpleadoController::class, 'nuevo'])
            ->name('empleados.nuevo');
        Route::post('/empleados', [EmpleadoController::class, 'guardar'])
            ->name('empleados.guardar');
        Route::get('/empleados/{empleado}', [EmpleadoController::class, 'ficha'])
            ->whereNumber('empleado')
            ->name('empleados.ficha');
    });

    /*
     * Contratos (documento 3, sección 5.4). Un contrato es una vinculación, así
     * que el identificador del detalle es el mismo de la ficha del empleado.
     *
     * Como en empleados, la dirección literal de la exportación va antes que la
     * del detalle: si no, `exportar` se leería como un identificador.
     */
    Route::middleware('modulo:'.Modulo::Contratos->value)->group(function () {
        Route::get('/contratos', [ContratoController::class, 'index'])
            ->name(Modulo::Contratos->nombreDeRuta());
        Route::get('/contratos/exportar', [ContratoController::class, 'exportar'])
            ->name('contratos.exportar');
        Route::get('/contratos/{contrato}', [ContratoController::class, 'detalle'])
            ->whereNumber('contrato')
            ->name('contratos.detalle');
        /*
         * «Renovar contrato» es una dirección propia y un verbo propio, no un
         * campo más del formulario de edición: renovar es un hecho jurídico y
         * corregir la fecha de finalización es arreglar una digitación
         * (documento 1, 5.5). El sistema no deja que una se use por la otra.
         */
        Route::post('/contratos/{contrato}/renovar', [ContratoController::class, 'renovar'])
            ->whereNumber('contrato')
            ->name('contratos.renovar');
        /*
         * «Cambiar a término indefinido» también tiene su propia dirección y su
         * propio verbo. Es la salida que ofrece el bloqueo del tope de cuatro
         * años, y es irreversible: el cambio inverso no existe y por eso no hay
         * ninguna ruta que lo haga.
         */
        Route::post('/contratos/{contrato}/indefinido', [ContratoController::class, 'cambiarAIndefinido'])
            ->whereNumber('contrato')
            ->name('contratos.indefinido');
    });

    /*
     * Configuración (documento 3, sección 5.7). El módulo es solo del
     * superadministrador y eso ya lo decide `PoliticaDeModulos`: aquí basta
     * declararlo como cualquier otro.
     *
     * Guardar es `PUT` y no `POST`: cambiar los parámetros de la empresa es
     * reemplazar los tres valores que ya existen, no crear un hecho nuevo.
     */
    Route::middleware('modulo:'.Modulo::Configuracion->value)->group(function () {
        Route::get('/configuracion', [ConfiguracionController::class, 'parametros'])
            ->name(Modulo::Configuracion->nombreDeRuta());
        Route::put('/configuracion/parametros', [ConfiguracionController::class, 'guardarParametros'])
            ->name('configuracion.parametros');
    });

    foreach (Modulo::cases() as $modulo) {
        if (in_array($modulo, [Modulo::Inicio, Modulo::Empleados, Modulo::Contratos, Modulo::Configuracion], true)) {
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
 * El panel del proveedor (documento 4, capítulos 1 y 2). Es una aplicación
 * aparte de la que ve el cliente: vive bajo su propio prefijo, no comparte ni
 * el armazón ni el menú, y entra solo el rol `owner`.
 *
 * El orden de los tres middleware es el orden de las tres preguntas, y no se
 * puede cambiar:
 *
 * 1. `auth` — ¿hay sesión?
 * 2. `proveedor` — ¿es el `owner`? Si no, 403. Va antes del segundo factor para
 *    que a un usuario de empresa se le responda 403 y no se le desvíe a activar
 *    un segundo factor que no le corresponde.
 * 3. `segundo-factor` — ¿ya escribió su código? Solo envuelve las nueve
 *    secciones; las cuatro direcciones de activar y verificar quedan fuera,
 *    porque son las que hay que poder ver sin haberlo verificado.
 */
Route::middleware(['auth', 'proveedor'])
    ->prefix(SeccionDelPanel::PREFIJO)
    ->group(function () {
        Route::get('/segundo-factor', [SegundoFactorController::class, 'activar'])
            ->name('proveedor.segundo-factor');
        Route::post('/segundo-factor', [SegundoFactorController::class, 'confirmar'])
            ->name('proveedor.segundo-factor.confirmar');

        Route::get('/verificacion', [SegundoFactorController::class, 'verificar'])
            ->name('proveedor.verificacion');
        Route::post('/verificacion', [SegundoFactorController::class, 'comprobar'])
            ->name('proveedor.verificacion.comprobar');

        Route::middleware('segundo-factor')->group(function () {
            Route::get('/', [PanelController::class, 'inicio'])
                ->name(SeccionDelPanel::Inicio->nombreDeRuta());

            /*
             * Empresas (documento 4, capítulo 4): el listado, crear, la ficha y
             * lo que cuelga de ella.
             *
             * `/empresas/nueva` va **antes** que `/empresas/{empresa}`: si no,
             * `nueva` se leería como el identificador de una empresa. Por eso la
             * ficha exige además que el suyo sea un número.
             */
            Route::get('/empresas', [EmpresaController::class, 'index'])
                ->name(SeccionDelPanel::Empresas->nombreDeRuta());
            Route::get('/empresas/nueva', [EmpresaController::class, 'nueva'])
                ->name('proveedor.empresas.nueva');
            Route::post('/empresas', [EmpresaController::class, 'crear'])
                ->name('proveedor.empresas.crear');
            Route::get('/empresas/{empresa}/resumen', [EmpresaController::class, 'resumen'])
                ->name('proveedor.empresas.resumen');
            Route::get('/empresas/{empresa}/certificado', [EmpresaController::class, 'certificado'])
                ->name('proveedor.empresas.certificado');
            Route::get('/empresas/{empresa}', [EmpresaController::class, 'ficha'])
                ->whereNumber('empresa')
                ->name('proveedor.empresas.ficha');

            /*
             * Editar los parámetros de una empresa desde su ficha (4.3). Es
             * `PUT` por lo mismo que en el entorno del cliente: se reemplazan
             * los tres valores que ya existen, no se crea un hecho nuevo.
             */
            Route::put('/empresas/{empresa}/parametros', [EmpresaController::class, 'guardarParametros'])
                ->whereNumber('empresa')
                ->name('proveedor.empresas.parametros');

            /*
             * Las dos acciones de la pestaña «Usuarios». Ninguna de las dos
             * enseña una contraseña: una reenvía el enlace de la invitación y la
             * otra manda el de restablecerla.
             */
            Route::post('/empresas/{empresa}/usuarios/{usuario}/invitacion', [EmpresaController::class, 'reenviarInvitacion'])
                ->whereNumber(['empresa', 'usuario'])
                ->name('proveedor.empresas.invitacion');
            Route::post('/empresas/{empresa}/usuarios/{usuario}/contrasena', [EmpresaController::class, 'restablecerContrasena'])
                ->whereNumber(['empresa', 'usuario'])
                ->name('proveedor.empresas.contrasena');

            /*
             * Las ocho secciones restantes del capítulo 2. Existen desde ya,
             * como en el armazón del cliente, para que el menú del panel se
             * pueda recorrer entero: la dirección, el nombre de ruta y el
             * resaltado son los definitivos y solo falta el contenido.
             */
            foreach (SeccionDelPanel::pendientes() as $seccion) {
                Route::get('/'.$seccion->value, fn () => app(PanelController::class)->seccion($seccion))
                    ->name($seccion->nombreDeRuta());
            }
        });
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
