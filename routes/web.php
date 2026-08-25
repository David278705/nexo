<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\SeccionDelPanel;
use App\Catalogos\Ubicaciones;
use App\Http\Controllers\Acceso\AccesoDePruebaController;
use App\Http\Controllers\Acceso\InvitacionController;
use App\Http\Controllers\Acceso\RecuperacionController;
use App\Http\Controllers\Acceso\RestablecimientoController;
use App\Http\Controllers\Acceso\SesionController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\IncapacidadController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\LicenciaController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\Proveedor\CargaInicialController;
use App\Http\Controllers\Proveedor\CatalogoController;
use App\Http\Controllers\Proveedor\ConsolaDeAlertasController;
use App\Http\Controllers\Proveedor\DemostracionController;
use App\Http\Controllers\Proveedor\EmpresaController;
use App\Http\Controllers\Proveedor\EntornoDelClienteController;
use App\Http\Controllers\Proveedor\PanelController;
use App\Http\Controllers\Proveedor\SaludController;
use App\Http\Controllers\Proveedor\SegundoFactorController;
use App\Http\Controllers\Proveedor\SuscripcionController;
use App\Http\Controllers\ServicioSuspendidoController;
use App\Http\Controllers\TerminacionController;
use App\Http\Controllers\VacacionController;
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

/*
 * El enlace de un acceso de prueba (documento 4, capítulo 10 y 12.1). No va en
 * el grupo `guest`: a diferencia de las cuatro pantallas de arriba, entrar por
 * aquí es válido tenga sesión o no quien lo abra —el controlador cierra la que
 * hubiera antes de abrir la del ambiente de demostración—.
 */
Route::get('/prueba/{token}', [AccesoDePruebaController::class, 'entrar'])
    ->name('demostracion.entrar');

Route::middleware('auth')->group(function () {
    Route::post('/salir', [SesionController::class, 'salir'])->name('acceso.salir');

    /*
     * La pantalla de un servicio suspendido (documento 4, capítulo 6). Es la
     * única dirección que `BloquearEmpresaSuspendida` deja pasar, junto con
     * cerrar sesión; a quien no esté suspendido lo devuelve al inicio, porque
     * una pantalla que cuenta algo que ya no es cierto es peor que ninguna.
     */
    Route::get('/suspendido', [ServicioSuspendidoController::class, 'mostrar'])
        ->name('suspendido');

    /*
     * Salir del entorno de un cliente (documento 4, 4.4).
     *
     * Vive aquí, en el entorno del cliente, y no bajo el prefijo del panel: es
     * donde se está cuando hace falta, y durante una visita el panel está
     * cerrado. Es de las dos únicas direcciones que `EntornoDeSoloLectura` deja
     * pasar aunque no sean de lectura —la otra es cerrar sesión—, porque las dos
     * son de salida y ninguna toca datos del cliente.
     */
    Route::post('/entorno/salir', [EntornoDelClienteController::class, 'salir'])
        ->name('entorno.salir');

    /*
     * El panel de inicio (documento 3, sección 5.2): el saludo, la fecha y el
     * bloque de lo que exige atención.
     *
     * No lleva middleware de módulo porque `Inicio` es de todo usuario de una
     * empresa. Quien decide qué tarjetas se pintan es `BloqueDeAccion`, con la
     * misma política que responde en la ruta de destino de cada una.
     *
     * Sí lleva `cliente`: es la única sección sin `modulo:`, así que es la
     * única por la que un `owner` podría colarse sin recibir 403. Las demás ya
     * lo bloquean solas porque `PoliticaDeModulos` le niega todos los módulos.
     */
    Route::get('/', [InicioController::class, 'index'])->middleware('cliente')->name('inicio');

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
     * Incapacidades (documento 1, 6.3). El primer módulo de novedades: el
     * flujo compartido de `ModalDeNovedad` (F2-03) es lo que hace que solo
     * haga falta una dirección para guardar, sin un paso previo de «nuevo»
     * como en empleados.
     *
     * Como en empleados y contratos, la dirección literal de la exportación va
     * antes de cualquiera que pudiera confundirse con un identificador.
     */
    Route::middleware('modulo:'.Modulo::Incapacidades->value)->group(function () {
        Route::get('/incapacidades', [IncapacidadController::class, 'index'])
            ->name(Modulo::Incapacidades->nombreDeRuta());
        Route::get('/incapacidades/exportar', [IncapacidadController::class, 'exportar'])
            ->name('incapacidades.exportar');
        Route::post('/incapacidades', [IncapacidadController::class, 'guardar'])
            ->name('incapacidades.guardar');
        /*
         * Editar y eliminar (F2-10, documento 1, 6.2). Las tres novedades del
         * capítulo 6 llevan el mismo par de direcciones y los mismos verbos:
         * `PUT` porque se reemplaza el registro que ya existe, y `DELETE`
         * porque se elimina —lógicamente, la regla 4 del proyecto—, no porque
         * se cree un hecho nuevo.
         *
         * No llevan `whereNumber` por accidente: sin él, `exportar` se leería
         * como identificador.
         */
        Route::put('/incapacidades/{incapacidad}', [IncapacidadController::class, 'editar'])
            ->whereNumber('incapacidad')
            ->name('incapacidades.editar');
        Route::delete('/incapacidades/{incapacidad}', [IncapacidadController::class, 'eliminar'])
            ->whereNumber('incapacidad')
            ->name('incapacidades.eliminar');
    });

    /*
     * Vacaciones (documento 1, 6.4). El segundo módulo de novedades, y el
     * primero que descuenta del saldo: `guardar()` solo crea la fila, y
     * `SaldoDeVacaciones::al()` la resta la próxima vez que alguien pida el
     * saldo. La ruta de exportar va antes de cualquiera que pudiera
     * confundirse con un identificador.
     */
    Route::middleware('modulo:'.Modulo::Vacaciones->value)->group(function () {
        Route::get('/vacaciones', [VacacionController::class, 'index'])
            ->name(Modulo::Vacaciones->nombreDeRuta());
        Route::get('/vacaciones/exportar', [VacacionController::class, 'exportar'])
            ->name('vacaciones.exportar');
        Route::post('/vacaciones', [VacacionController::class, 'guardar'])
            ->name('vacaciones.guardar');
        // Editar y eliminar (F2-10): el mismo par que en incapacidades.
        Route::put('/vacaciones/{vacacion}', [VacacionController::class, 'editar'])
            ->whereNumber('vacacion')
            ->name('vacaciones.editar');
        Route::delete('/vacaciones/{vacacion}', [VacacionController::class, 'eliminar'])
            ->whereNumber('vacacion')
            ->name('vacaciones.eliminar');
    });

    /*
     * Licencias, ausencias y suspensiones (documento 1, 6.5). El tercer
     * módulo de novedades: un solo tipo con un selector de tres opciones. La
     * ruta de exportar va antes de cualquiera que pudiera confundirse con un
     * identificador.
     */
    Route::middleware('modulo:'.Modulo::Licencias->value)->group(function () {
        Route::get('/licencias', [LicenciaController::class, 'index'])
            ->name(Modulo::Licencias->nombreDeRuta());
        Route::get('/licencias/exportar', [LicenciaController::class, 'exportar'])
            ->name('licencias.exportar');
        Route::post('/licencias', [LicenciaController::class, 'guardar'])
            ->name('licencias.guardar');
        // Editar y eliminar (F2-10): el mismo par que en incapacidades.
        Route::put('/licencias/{licencia}', [LicenciaController::class, 'editar'])
            ->whereNumber('licencia')
            ->name('licencias.editar');
        Route::delete('/licencias/{licencia}', [LicenciaController::class, 'eliminar'])
            ->whereNumber('licencia')
            ->name('licencias.eliminar');
    });

    /*
     * Aumentos y promociones (documento 1, 8.1). No es una novedad del capítulo
     * 6, pero comparte su flujo de registro; lo que no comparte es el efecto:
     * `guardar()` escribe el histórico y el registro principal del empleado en
     * una sola transacción. La ruta de exportar va antes de cualquiera que
     * pudiera confundirse con un identificador.
     */
    Route::middleware('modulo:'.Modulo::Movimientos->value)->group(function () {
        Route::get('/movimientos', [MovimientoController::class, 'index'])
            ->name(Modulo::Movimientos->nombreDeRuta());
        Route::get('/movimientos/exportar', [MovimientoController::class, 'exportar'])
            ->name('movimientos.exportar');
        Route::post('/movimientos', [MovimientoController::class, 'guardar'])
            ->name('movimientos.guardar');
        /*
         * Editar y eliminar (REV-03). El mismo par de verbos que las tres
         * novedades del capítulo 6, aunque lo que hacen no sea lo mismo:
         * eliminar aquí no quita una fila, devuelve al empleado el cargo y el
         * salario que tenía antes de ella. Solo se admite sobre el movimiento
         * más reciente del empleado, y eso lo comprueba el controlador.
         */
        Route::put('/movimientos/{movimiento}', [MovimientoController::class, 'editar'])
            ->whereNumber('movimiento')
            ->name('movimientos.editar');
        Route::delete('/movimientos/{movimiento}', [MovimientoController::class, 'eliminar'])
            ->whereNumber('movimiento')
            ->name('movimientos.eliminar');
    });

    /*
     * Terminaciones (documento 1, capítulo 7). El último módulo del flujo
     * compartido, y el que cierra el ciclo: `guardar()` registra la salida y
     * pasa al empleado a inactivo en una sola transacción. La ruta de exportar
     * va antes de cualquiera que pudiera confundirse con un identificador.
     */
    Route::middleware('modulo:'.Modulo::Terminaciones->value)->group(function () {
        Route::get('/terminaciones', [TerminacionController::class, 'index'])
            ->name(Modulo::Terminaciones->nombreDeRuta());
        Route::get('/terminaciones/exportar', [TerminacionController::class, 'exportar'])
            ->name('terminaciones.exportar');
        Route::post('/terminaciones', [TerminacionController::class, 'guardar'])
            ->name('terminaciones.guardar');
        /*
         * Editar y eliminar (REV-03). Eliminar aquí revive al empleado: vuelve
         * a activo y vuelve a recibir alertas. No hay guardia de «el más
         * reciente» porque una vinculación tiene una sola terminación viva.
         */
        Route::put('/terminaciones/{terminacion}', [TerminacionController::class, 'editar'])
            ->whereNumber('terminacion')
            ->name('terminaciones.editar');
        Route::delete('/terminaciones/{terminacion}', [TerminacionController::class, 'eliminar'])
            ->whereNumber('terminacion')
            ->name('terminaciones.eliminar');
    });

    /*
     * El panel de alertas (documento 2, sección 5.5). Una sola dirección y de
     * solo lectura, como la auditoría: es el registro de lo que el sistema
     * envió, y eso no se edita ni se borra desde una pantalla.
     *
     * No hay ruta de detalle: el asunto, el cuerpo y los destinatarios viajan
     * con la fila y el detalle se abre en un modal, sin pedir nada más.
     */
    Route::middleware('modulo:'.Modulo::Alertas->value)->group(function () {
        Route::get('/alertas', [AlertaController::class, 'index'])
            ->name(Modulo::Alertas->nombreDeRuta());
    });

    /*
     * Auditoría (REV-04, documento 1, 8.2). Una sola dirección y de solo
     * lectura, como Alertas: es el registro de lo que ya se hizo, y eso no
     * se corrige desde una pantalla.
     */
    Route::middleware('modulo:'.Modulo::Auditoria->value)->group(function () {
        Route::get('/auditoria', [AuditoriaController::class, 'index'])
            ->name(Modulo::Auditoria->nombreDeRuta());
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
 * 4. `fuera-de-visita` — ¿está dentro del entorno de un cliente? Si lo está, el
 *    panel no se pide: se sale primero (documento 4, 4.4). Va con las nueve
 *    secciones, dentro del segundo factor.
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

        Route::middleware(['segundo-factor', 'fuera-de-visita'])->group(function () {
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
             * La bitácora interna de la pestaña «Bitácora» (4.3). Se escribe y
             * se quita; no se edita: una nota es un registro de lo que pasó, y
             * lo que se escribió en agosto tiene que seguir diciendo en febrero
             * lo que se escribió en agosto.
             */
            Route::post('/empresas/{empresa}/notas', [EmpresaController::class, 'agregarNota'])
                ->whereNumber('empresa')
                ->name('proveedor.empresas.notas');
            Route::delete('/empresas/{empresa}/notas/{nota}', [EmpresaController::class, 'eliminarNota'])
                ->whereNumber(['empresa', 'nota'])
                ->name('proveedor.empresas.notas.eliminar');

            /*
             * Entrar al entorno de esa empresa (4.4). Es la acción con más
             * consecuencias del panel entero y por eso es un `POST` con motivo
             * obligatorio: abre una visita, deja constancia en la auditoría del
             * cliente y cambia la empresa en contexto de quien la ejecuta.
             */
            Route::post('/empresas/{empresa}/entorno', [EntornoDelClienteController::class, 'entrar'])
                ->whereNumber('empresa')
                ->name('proveedor.empresas.entorno');

            /*
             * Catálogos (documento 4, capítulo 8). Una pantalla con cinco
             * pestañas y siete acciones de escritura.
             *
             * Ninguna de las siete es un `DELETE` salvo la de los festivos, y
             * esa es un borrado lógico: en las entidades y en las ciudades,
             * «desactivar» es la acción y por eso se llama `estado` y no
             * `eliminar`. Un catálogo del que un empleado depende no se borra.
             *
             * Las direcciones llevan la lista y el identificador por separado,
             * así que el controlador comprueba que el segundo sea de la primera.
             */
            Route::get('/catalogos', [CatalogoController::class, 'index'])
                ->name(SeccionDelPanel::Catalogos->nombreDeRuta());

            Route::post('/catalogos/entidades/{catalogo}', [CatalogoController::class, 'crearEntidad'])
                ->name('proveedor.catalogos.entidades.crear');
            Route::put('/catalogos/entidades/{catalogo}/{entidad}', [CatalogoController::class, 'editarEntidad'])
                ->whereNumber('entidad')
                ->name('proveedor.catalogos.entidades.editar');
            Route::post('/catalogos/entidades/{catalogo}/{entidad}/estado', [CatalogoController::class, 'alternarEntidad'])
                ->whereNumber('entidad')
                ->name('proveedor.catalogos.entidades.estado');

            Route::post('/catalogos/ciudades', [CatalogoController::class, 'crearCiudad'])
                ->name('proveedor.catalogos.ciudades.crear');
            Route::put('/catalogos/ciudades/{ciudad}', [CatalogoController::class, 'editarCiudad'])
                ->whereNumber('ciudad')
                ->name('proveedor.catalogos.ciudades.editar');
            Route::post('/catalogos/ciudades/{ciudad}/estado', [CatalogoController::class, 'alternarCiudad'])
                ->whereNumber('ciudad')
                ->name('proveedor.catalogos.ciudades.estado');

            Route::post('/catalogos/festivos', [CatalogoController::class, 'crearFestivo'])
                ->name('proveedor.catalogos.festivos.crear');
            Route::delete('/catalogos/festivos/{festivo}', [CatalogoController::class, 'quitarFestivo'])
                ->whereNumber('festivo')
                ->name('proveedor.catalogos.festivos.quitar');

            /*
             * Puesta en marcha (documento 4, capítulo 5): el asistente de carga
             * inicial y el historial de cargas.
             *
             * Son tres direcciones y la diferencia entre las dos últimas es la
             * única que importa de toda la pantalla: `simular` no escribe nada y
             * `cargar` escribe. Por eso no son la misma ruta con una casilla de
             * «esta vez sí»: una casilla mal marcada es una carga que entra sin
             * que nadie la haya confirmado.
             */
            Route::get('/'.SeccionDelPanel::PuestaEnMarcha->value, [CargaInicialController::class, 'index'])
                ->name(SeccionDelPanel::PuestaEnMarcha->nombreDeRuta());
            Route::post('/'.SeccionDelPanel::PuestaEnMarcha->value.'/simular', [CargaInicialController::class, 'simular'])
                ->name('proveedor.puesta-en-marcha.simular');
            Route::post('/'.SeccionDelPanel::PuestaEnMarcha->value.'/cargar', [CargaInicialController::class, 'cargar'])
                ->name('proveedor.puesta-en-marcha.cargar');

            /*
             * Suscripciones y cobros (documento 4, capítulo 6).
             *
             * La división viene del prototipo: esta pantalla es la del negocio
             * entero —el ingreso recurrente, la mora y el catálogo de planes—,
             * así que de aquí salen solo las dos acciones que son de todos los
             * clientes a la vez (el catálogo) y la de abrirle suscripción a una
             * empresa. Las cuatro que son de un cliente concreto —cambiar de
             * plan, registrar un pago, anularlo, suspender y reactivar— se
             * disparan desde la ficha de su empresa y vuelven allí, aunque su
             * dirección viva bajo este prefijo: el identificador es el de la
             * suscripción y no el de la empresa.
             *
             * Suspender y reactivar son `POST` y no `PUT`: no reemplazan un
             * valor, registran un hecho con su motivo y su fecha.
             */
            Route::get('/'.SeccionDelPanel::Suscripciones->value, [SuscripcionController::class, 'index'])
                ->name(SeccionDelPanel::Suscripciones->nombreDeRuta());

            Route::post('/'.SeccionDelPanel::Suscripciones->value.'/planes', [SuscripcionController::class, 'crearPlan'])
                ->name('proveedor.suscripciones.planes.crear');
            Route::put('/'.SeccionDelPanel::Suscripciones->value.'/planes/{plan}', [SuscripcionController::class, 'editarPlan'])
                ->whereNumber('plan')
                ->name('proveedor.suscripciones.planes.editar');
            Route::post('/'.SeccionDelPanel::Suscripciones->value.'/planes/{plan}/estado', [SuscripcionController::class, 'alternarPlan'])
                ->whereNumber('plan')
                ->name('proveedor.suscripciones.planes.estado');

            Route::post('/'.SeccionDelPanel::Suscripciones->value, [SuscripcionController::class, 'registrar'])
                ->name('proveedor.suscripciones.registrar');

            Route::put('/'.SeccionDelPanel::Suscripciones->value.'/{suscripcion}/plan', [SuscripcionController::class, 'cambiarDePlan'])
                ->whereNumber('suscripcion')
                ->name('proveedor.suscripciones.plan');
            Route::post('/'.SeccionDelPanel::Suscripciones->value.'/{suscripcion}/pagos', [SuscripcionController::class, 'registrarPago'])
                ->whereNumber('suscripcion')
                ->name('proveedor.suscripciones.pagos');
            Route::post('/'.SeccionDelPanel::Suscripciones->value.'/{suscripcion}/pagos/{pago}/anular', [SuscripcionController::class, 'anularPago'])
                ->whereNumber(['suscripcion', 'pago'])
                ->name('proveedor.suscripciones.pagos.anular');
            Route::post('/'.SeccionDelPanel::Suscripciones->value.'/{suscripcion}/suspender', [SuscripcionController::class, 'suspender'])
                ->whereNumber('suscripcion')
                ->name('proveedor.suscripciones.suspender');
            Route::post('/'.SeccionDelPanel::Suscripciones->value.'/{suscripcion}/reactivar', [SuscripcionController::class, 'reactivar'])
                ->whereNumber('suscripcion')
                ->name('proveedor.suscripciones.reactivar');

            /*
             * Demostración (documento 4, capítulo 10): el estado del ambiente
             * ficticio, su botón de restaurar y los accesos de prueba con
             * vigencia limitada.
             */
            Route::get('/'.SeccionDelPanel::Demostracion->value, [DemostracionController::class, 'index'])
                ->name(SeccionDelPanel::Demostracion->nombreDeRuta());
            Route::post('/'.SeccionDelPanel::Demostracion->value.'/restaurar', [DemostracionController::class, 'restaurar'])
                ->name('proveedor.demostracion.restaurar');
            Route::post('/'.SeccionDelPanel::Demostracion->value.'/accesos', [DemostracionController::class, 'generarAcceso'])
                ->name('proveedor.demostracion.accesos.generar');
            Route::post('/'.SeccionDelPanel::Demostracion->value.'/accesos/{acceso}/revocar', [DemostracionController::class, 'revocarAcceso'])
                ->whereNumber('acceso')
                ->name('proveedor.demostracion.accesos.revocar');

            /*
             * La consola de alertas (documento 4, capítulo 7): todo lo enviado,
             * de todas las empresas, con reenvío puntual.
             *
             * La empresa va delante en la dirección del reenvío, no solo la
             * alerta: es lo que deja entrar en su contexto antes de buscarla, y
             * lo que hace que reenviar la de una empresa con el identificador de
             * otra responda 404 en vez de tocar lo que no debía.
             */
            Route::get('/'.SeccionDelPanel::Alertas->value, [ConsolaDeAlertasController::class, 'index'])
                ->name(SeccionDelPanel::Alertas->nombreDeRuta());
            Route::post('/'.SeccionDelPanel::Alertas->value.'/{empresa}/{alerta}/reenviar', [ConsolaDeAlertasController::class, 'reenviar'])
                ->whereNumber(['empresa', 'alerta'])
                ->name('proveedor.alertas.reenviar');

            /*
             * Salud del sistema (documento 4, capítulo 9): el historial del
             * proceso diario, la cola, los errores de aplicación y las
             * copias de seguridad.
             */
            Route::get('/'.SeccionDelPanel::Salud->value, [SaludController::class, 'index'])
                ->name(SeccionDelPanel::Salud->nombreDeRuta());
            Route::post('/'.SeccionDelPanel::Salud->value.'/cola/{uuid}/reintentar', [SaludController::class, 'reintentarTrabajo'])
                ->name('proveedor.salud.cola.reintentar');

            /*
             * Las secciones restantes del capítulo 2. Existen desde ya, como
             * en el armazón del cliente, para que el menú del panel se pueda
             * recorrer entero: la dirección, el nombre de ruta y el
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
