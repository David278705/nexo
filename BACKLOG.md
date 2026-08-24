# Backlog

Tareas en orden de ejecución. **Se toma siempre la primera `[ ]`**, nunca otra.

`[ ]` pendiente · `[x]` hecha · `[!]` bloqueada, con el motivo al lado

Cada tarea trae **Hecho cuando**, que es lo que hay que poder demostrar para marcarla `[x]`, y
**Fuente**, que son los únicos documentos que hay que leer para resolverla.

---

## Fase 0 — Cimientos

### [x] F0-01 · Arrancar el proyecto

Laravel 12 con el starter kit de Vue (Inertia 2 + Vue 3 + Tailwind). PostgreSQL configurado.
Pest instalado. Pint y PHPStan (larastan) instalados con su configuración.
Script `composer verificar` que corre, en orden: `pint --test`, `phpstan analyse`, `pest`,
`npm run build`, `vitest run`.
**Hecho cuando:** `composer verificar` corre entero y termina en verde con el proyecto vacío.
**Fuente:** `docs/02-arquitectura-y-datos.md` cap. 1

### [x] F0-02 · Configuración base

`config/reglas_laborales.php` con todas las constantes legales, cada una con su norma citada en
comentario y la fecha de verificación. Zona horaria `America/Bogota`. Locale `es`.
**Hecho cuando:** existe el archivo con las nueve constantes y una prueba lee cada una.
**Fuente:** `.claude/rules/20-calculo-y-reglas-legales.md`

### [x] F0-03 · Multiempresa, capa 1

Tabla `tenants` con datos y parámetros. Trait `PerteneceATenant` con global scope y relleno
automático. Middleware que resuelve el tenant desde el usuario autenticado, una sola vez por petición.
Helper `tenant_actual()`.
**Hecho cuando:** un modelo con el trait filtra solo por el trait, sin `where` manual, y una prueba
lo demuestra.
**Fuente:** `.claude/rules/10-multiempresa.md`

### [x] F0-04 · Multiempresa, capa 2 (RLS)

Políticas de Row Level Security en PostgreSQL. Variable de sesión fijada por el middleware.
Migración de ejemplo que muestre el patrón a repetir en cada tabla de negocio.
**Hecho cuando:** una consulta SQL directa sin el global scope tampoco devuelve filas de otra empresa.
**Fuente:** `.claude/rules/10-multiempresa.md`

### [x] F0-05 · Batería de aislamiento

`tests/Feature/AislamientoTest.php` con la estructura y al menos un caso completo: crear en la
empresa A, autenticarse en la B, intentar leer por modelo, ruta, exportación y búsqueda.
**Hecho cuando:** el archivo existe, el caso pasa, y quitar el trait a propósito lo pone en rojo.
**Fuente:** `.claude/rules/40-pruebas-y-seguridad.md`

### [x] F0-06 · Usuarios, roles y permisos

Tabla `users` con rol `owner | superadmin | admin`. Tabla `user_modulos`. Políticas que verifican en
el servidor. Middleware de módulo.
**Hecho cuando:** un usuario sin un módulo recibe 403 al pedir su ruta directamente, no solo deja de
ver el menú.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 3

### [x] F0-07 · Autenticación

Iniciar y cerrar sesión, recuperar contraseña, restablecer, aceptar invitación. Bloqueo tras intentos
fallidos. Registro de accesos incluidos los fallidos.
**Hecho cuando:** los cuatro flujos funcionan y hay prueba de cada uno.
**Fuente:** `docs/03-diseno-e-interfaz.md` 5.1

### [x] F0-08 · Auditoría

Tabla `auditoria`. Observador genérico que registra creación, edición y borrado con valores anterior
y nuevo. No editable ni borrable desde la aplicación.
**Hecho cuando:** crear un registro cualquiera deja una línea sin que nadie la escriba a mano.
**Fuente:** `docs/02-arquitectura-y-datos.md` 7.2

### [x] F0-09 · Sistema de diseño

Variables CSS con la paleta completa. Tipografía Inter. Componentes base: `TablaDatos`,
`BarraFiltros`, `Modal`, `CampoTexto`, `CampoFecha`, `Selector`, `Etiqueta`, `Aviso`, `EstadoVacio`,
`TarjetaIndicador`, `Notificacion`.
**Hecho cuando:** existe una página de muestra con todos los componentes y ninguno tiene un color literal.
**Fuente:** `docs/03-diseno-e-interfaz.md` cap. 3 · `prototipos/sistema.html`

### [x] F0-10 · Estructura de la aplicación

Barra lateral con los módulos, encabezado con nombre de empresa y usuario, y el comportamiento en
pantallas pequeñas. Un módulo sin permiso no aparece en el menú.
**Hecho cuando:** se navega entre secciones vacías y el menú se adapta al rol.
**Fuente:** `docs/03-diseno-e-interfaz.md` cap. 4

### [x] F0-11 · Catálogos y festivos

Tablas globales: países, departamentos y ciudades de Colombia, EPS, fondos, ARL, cajas, bancos,
festivos. Seeders con los datos de Colombia. Los festivos incluyen los que caen en sábado.
**Hecho cuando:** las listas encadenadas de departamento y ciudad funcionan con datos reales.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 10

Hecho: las nueve tablas, sus modelos, las listas encadenadas con los 33 departamentos y 1.101
municipios, 232 países y los festivos de 2025, 2026 y 2027 con los de sábado incluidos. Las cinco
listas de entidades se cargaron con `Listas_Desplegables_Formulario_Empleados.xlsx`, que resolvió la
decisión D.5: 18 EPS, 7 fondos, 9 ARL, 34 cajas y 25 bancos.

---

## Fase 1 — Personas y contratos

### [x] F1-01 · Personas y vinculaciones

Tablas `personas`, `perfil_sociodemografico` y `vinculaciones`, con la separación entre persona y
vinculación. Documento único por empresa en `personas`, no en `vinculaciones`.
**Hecho cuando:** una persona puede tener dos vinculaciones sin duplicar sus datos personales, y hay
prueba de ello.
**Fuente:** `docs/02-arquitectura-y-datos.md` 3.2

### [x] F1-02 · Periodos contractuales

Tabla `periodos_contractuales` con `orden`, `evento`, tipo de contrato y fechas. El periodo inicial
es la fila con `orden = 1`.
**Hecho cuando:** se pueden calcular sobre ella las prórrogas acumuladas y la duración total del vínculo.
**Fuente:** `docs/02-arquitectura-y-datos.md` 3.3

Hecho: la tabla, `EventoContractual`, el modelo `PeriodoContractual` y `HistorialDeContrato`, que
calcula prórrogas acumuladas —pactadas y automáticas por separado—, duración total del vínculo,
vencimiento vigente y tope de cuatro años. El periodo inicial lo abre sola la vinculación al crearse.

### [x] F1-03 · Listado de empleados

Tabla con filtros de estado, tipo de contrato, cargo, rango de ingreso y búsqueda. Exportación que
respeta filtros. Estado vacío con su texto.
**Hecho cuando:** los filtros funcionan en el servidor y la exportación trae exactamente lo filtrado.
**Fuente:** `docs/03-diseno-e-interfaz.md` 5.3 · usa el skill `crear-modulo`

Hecho: `ListadoDeEmpleados` —los seis filtros, el orden y la paginación, todo en el servidor—,
`EmpleadoController`, `EmpleadosExport` con Laravel Excel, la pantalla `Empleados/Index` y el
componente compartido `Paginacion`. Pantalla y exportación comparten la misma consulta, así que
«Exportar N registros filtrados» no puede mentir.

### [x] F1-04 · Registro de empleado

Asistente de cuatro pasos. Regla de país, departamento y ciudad: listas encadenadas si es Colombia,
texto libre si no. Segundo nombre y segundo apellido opcionales. Estado activo automático.
**Hecho cuando:** se registra un empleado completo y el estado queda activo sin que nadie lo digite.
**Fuente:** `docs/01-especificacion-funcional.md` 5.1 a 5.3

Hecho: la pantalla `Empleados/Nuevo` con sus cuatro pasos —identificación, residencia y seguridad
social, información contractual y resumen—, el componente compartido `PasosDelAsistente`, la
validación completa en `RegistrarEmpleado` y la escritura en `RegistroDeEmpleado`, que busca la
persona por su documento antes de crearla. Los enumerados `Genero` y `TipoRiesgo` y las listas de
entidades en `Catalogos\Entidades`. El estado no se digita en ninguna parte: nace activo por el valor
por defecto de la columna.

### [x] F1-05 · Advertencia de reingreso

Al digitar un documento que ya tuvo vinculación en esa empresa, aviso en pantalla con el texto exacto
del documento. Énfasis especial si el reingreso es dentro de los 30 días siguientes a la salida.
**Hecho cuando:** el aviso aparece con el texto literal y hay prueba del caso de 30 días.
**Fuente:** `docs/01-especificacion-funcional.md` 5.6

Hecho: `AdvertenciaDeReingreso`, con los dos textos literales como constantes del servidor; la prop
opcional `reingreso` que el asistente pide con `router.reload({ only: [...] })` al salir del campo
del documento y del de la fecha de ingreso; el componente compartido `AvisoDeReingreso`, que se
muestra en el paso 1 y otra vez en el resumen. El énfasis de los 30 días se mide contra
`Vinculacion::fechaDeSalida()`, **provisional hasta F2-09**: hoy sale de `fecha_fin`.

### [x] F1-06 · Perfil sociodemográfico

Bloque opcional, activable por parámetro de empresa. Todos sus campos opcionales aunque el bloque
esté activo. Al activarlo después, los empleados existentes quedan en blanco sin marcar error.
**Hecho cuando:** activar y desactivar el parámetro muestra y oculta el bloque sin romper nada.
**Fuente:** `docs/01-especificacion-funcional.md` 5.2

Hecho: `BloqueSociodemografico` —el parámetro, las seis listas, las reglas y la escritura en un solo
sitio—, los cuatro enumerados que faltaban (`EstadoCivil`, `NivelEducativo`, `MedioTransporte`,
`TiempoDesplazamiento`) tomados del archivo de listas de Angela, el trait `OpcionesDeLista` y el paso
del asistente, que se intercala antes del resumen solo si la empresa lo habilitó. Un perfil en blanco
no crea fila: no hay diferencia entre un perfil vacío y uno que no existe.

### [x] F1-07 · Ficha del empleado

Encabezado con saldo de vacaciones siempre visible y pestañas de datos, contrato, novedades y
movimientos.
**Hecho cuando:** las cuatro pestañas cargan y el saldo se ve arriba.
**Fuente:** `docs/03-diseno-e-interfaz.md` 5.3

Hecho: `FichaDeEmpleado` —el encabezado, el resumen de seis datos y el contenido de las cuatro
pestañas—, la pantalla `Empleados/Ficha` y tres componentes compartidos: `Pestanas`, `ListaDeDatos` y
`ResumenDeEmpleado`, que volverá a usarse en el modal grande de novedades. El saldo de vacaciones se
ve arriba **diciendo que todavía no se puede calcular**: el motor es F2-02 y la regla 2 prohíbe
guardarlo, así que cualquier número de hoy sería inventado.

### [x] F1-08 · Listado y detalle de contratos

Vista por defecto de los que vencen en 90 días. Detalle con historial de periodos empezando por el
inicial. Fecha límite de preaviso visible en la ficha.
**Hecho cuando:** un contrato con tres prórrogas muestra cuatro filas de historial.
**Fuente:** `docs/01-especificacion-funcional.md` 5.5

Hecho: `ListadoDeContratos` —los tres filtros del documento 3, el orden y la paginación, todo en el
servidor—, `DetalleDelContrato`, `ContratoController`, `ContratosExport` y las pantallas
`Contratos/Index` y `Contratos/Detalle`. `VigenciaDelContrato` es el objeto nuevo: responde en qué
día está un contrato —cuánto falta para vencer, si el preaviso todavía alcanza— y lo reutilizarán las
alertas del capítulo 9. La fecha límite de preaviso sale de `preaviso_dias`, nunca de un 30 escrito a
mano. El historial del detalle es la misma tabla de la ficha, armada por el mismo método.

### [x] F1-09 · Renovar contrato

Acción separada de editar la fecha, con nombres y consecuencias distintas. Calcula y muestra duración
de la prórroga, total acumulado y prórrogas restantes. Registra el tipo de prórroga.
**Hecho cuando:** renovar crea una fila nueva en el historial y editar la fecha no.
**Fuente:** `docs/01-especificacion-funcional.md` 5.5

Hecho: `RenovacionDeContrato` —el objeto nuevo, que responde qué pasaría si se guardara esa prórroga—,
`Vinculacion::renovar()`, `RenovarContrato`, la ruta `POST /contratos/{contrato}/renovar` y el
componente `ModalDeRenovacion`. **D.1 quedó resuelta como la propone el documento 6**: el modal
pregunta el tipo de prórroga, y de él dependen los cupos cortos —cuatro pactadas, tres automáticas,
leídos de la configuración—. `registrarPeriodo()` ahora mueve también `tipo_contrato` y `fecha_fin` de
la vinculación en la misma transacción: el historial y lo vigente ya no pueden desalinearse. Las tres
cuentas del modal se calculan en el servidor y llegan escritas; al cambiar la fecha o el tipo, la
pantalla pide de nuevo solo la prop `renovacion`. El tope de cuatro años y el límite de prórrogas
cortas se **advierten** y no bloquean: volverlos bloqueo es F1-10.

### [x] F1-10 · Validaciones de prórroga

Bloqueo si la nueva fecha supera el tope legal, ofreciendo la acción de cambiar a indefinido.
Advertencia fuerte si incumple la regla de prórroga mínima. Las dos configurables por estado.
**Hecho cuando:** una prórroga que supera el tope no se puede guardar, y hay prueba.
**Fuente:** `docs/01-especificacion-funcional.md` 5.5 · `docs/06-decisiones-pendientes.md` B.3

Hecho: las dos validaciones viven en `RenovacionDeContrato::hallazgos()`, con un solo mensaje escrito
por regla que sirve al aviso del modal y al error del servidor —`impedimentos()` es lo que consulta
`RenovarContrato`—. **«Configurables por estado» se leyó como el estado de cada validación**, bloquea
o advierte, en el nuevo `config/validaciones.php` y el enumerado `EstadoDeValidacion`, que decide
también el color: rojo y bloqueado son la misma cosa. **El tope de cuatro años bloquea** y su mensaje
nombra «Cambiar a término indefinido» —la acción es F1-11—; **la prórroga mínima advierte con fuerza y
permite guardar**, que es lo que piden B.1 y B.3 mientras el asesor laboral confirme la regla. El
botón del modal queda deshabilitado con la proyección bloqueada, como el prototipo.

### [x] F1-11 · Cambiar a término indefinido

Solo en contratos a término fijo. La fecha de ingreso no cambia. Queda registrado en el historial.
**Hecho cuando:** tras el cambio, la antigüedad y el saldo siguen contando desde la fecha original.
**Fuente:** `docs/01-especificacion-funcional.md` 5.5

Hecho: `CambioAIndefinido` responde las dos únicas preguntas de la acción —**si existe en ese
contrato** y **qué implica**—, y las cinco frases del prototipo se escriben allí y no en el
componente, para que la misma prueba que comprueba el efecto lea el texto. El tramo lo agrega
`Vinculacion::cambiarATerminoIndefinido()` pasando por `registrarPeriodo()`, **sin fecha de fin**: con
ella desaparecen el vencimiento, el preaviso y las alertas de renovación. **La fecha de ingreso y el
tramo inicial no se tocan**, así que la antigüedad sigue contando desde la fecha original. El modal es
una confirmación sin campos y `CambiarATerminoIndefinido` responde 403 fuera del término fijo: el
cambio inverso no existe y por eso no hay ruta que lo haga.

### [x] F1-12 · Parámetros de la empresa

Pantalla con los cuatro parámetros y su explicación en lenguaje claro. Aviso al activar el perfil
sociodemográfico.
**Hecho cuando:** cambiar un parámetro se refleja de inmediato en los cálculos.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 4

Hecho: `ParametrosDeLaEmpresa` —los cuatro parámetros, sus explicaciones en lenguaje claro, sus reglas
y la escritura, en un solo sitio—, `ConfiguracionController`, `GuardarParametros`, las rutas
`GET /configuracion` y `PUT /configuracion/parametros`, y la pantalla `Configuracion/Parametros` con
las cuatro pestañas del prototipo. **Tres parámetros se guardan y el cuarto se deduce**: «correos que
reciben las alertas» sale de los usuarios y de sus módulos, porque eso es lo que dice el capítulo 4, y
se cambia en Usuarios y permisos. Nada se guarda en caché: el valor se lee de la empresa en contexto,
así que cambiar un parámetro se refleja en la petición siguiente —se prueba con el bloque
sociodemográfico, el único parámetro con consumidor construido hasta la fase 2—. Sí y No viajan como
texto `si`/`no` para que la cadena «false» no pueda colarse como verdadera.

### [x] F1-13 · PUNTO DE CONTROL — revisión

Ejecuta el skill `revisar`.
**Hecho cuando:** existe el informe en `bitacora/` y lo encontrado está arreglado o convertido en tareas `REV-`.

Informe en `bitacora/revision-2026-08-20.md`. Aislamiento, motor, interfaz, seguridad y datos sin
hallazgos. Se arregló la duplicación de los textos de presentación —`App\Presentacion\Formato`— y
quedaron `REV-01` y `REV-02` por el armazón de listado repetido en el servidor y en la interfaz.

---

## Fase 1B — Panel del proveedor, mínimo

### [x] F1B-01 · Base del panel

Aplicación separada bajo su propia ruta, solo para el rol `owner`, con segundo factor obligatorio.
Ningún usuario de empresa puede alcanzarla.
**Hecho cuando:** un superadmin que intenta entrar recibe 403 y hay prueba.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 2

### [x] F1B-02 · Crear empresa

Formulario de tres pasos: datos, parámetros, plan y cuenta del superadministrador. Resumen final de
configuración copiable. Invitación por correo.
**Hecho cuando:** se crea una empresa completa desde la interfaz en menos de cinco minutos, sin tocar
la base de datos.
**Fuente:** `docs/04-panel-del-proveedor.md` 4.2 · `prototipos/panel-proveedor.html`

Hecho: `CreacionDeEmpresa` —las reglas, la escritura y los textos del resumen en un solo sitio—,
`EmpresaController`, `CrearEmpresa`, las cuatro rutas `proveedor.empresas.*` y las pantallas
`Proveedor/CrearEmpresa` y `Proveedor/ResumenDeEmpresa`. **La empresa, su superadministrador y su
invitación nacen dentro de la misma transacción**: si el correo ya estaba en uso no queda ninguna
empresa a medias, y hay prueba. La ciudad dejó de ser texto y pasó a ser el municipio del catálogo.
El plan se guarda como cuatro columnas de `tenants` —nombre y los dos números pactados— porque la
tabla `planes` con sus precios depende de A.1, que sigue abierta. El resumen lo arma el servidor
leyendo la empresa ya guardada, y el texto que se copia es exactamente el que se enseña.

### [x] F1B-03 · Listado y ficha de empresas

Listado con indicadores de salud de uso y de pago. Ficha con resumen, parámetros editables por el
owner y gestión de usuarios de esa empresa.
**Hecho cuando:** se ve el listado con datos reales y se edita un parámetro desde la ficha.
**Fuente:** `docs/04-panel-del-proveedor.md` 4.1 y 4.3

Hecho: `ListadoDeEmpresas` —los cuatro filtros, el orden, la paginación y las filas—, `FichaDeEmpresa`
—las siete pestañas—, los enumerados `EstadoDeEmpresa` y `SaludDeUso`, y las pantallas
`Proveedor/Empresas` y `Proveedor/FichaDeEmpresa`. **Los empleados de cada empresa se cuentan dentro
de su contexto** con `ContextoTenant::paraTenant()`, no se leen del contador `empleados_cargados` que
todavía no refresca nadie. **La salud de uso sale del último ingreso**, que ahora lo escribe el propio
inicio de sesión en `RegistroDeAccesos`; sus umbrales viven en el nuevo `config/panel.php` porque son
una decisión comercial que el documento no fija. **«Pago» se declara y no se inventa**: las
suscripciones son F5-04, así que la columna lo dice y su filtro no se ofrece. Cuatro pestañas tienen
contenido y tres dicen de qué tarea depende el suyo. Los parámetros se editan desde la ficha y el
cambio queda en la auditoría **de esa empresa**, que es la que el cliente puede ver; las dos acciones
sobre un usuario mandan enlaces a su buzón y **ninguna enseña una contraseña**.

### [x] F1B-04 · Catálogos y constantes desde el panel

Administración de EPS, fondos, ARL, cajas, bancos y ciudades. Calendario de festivos por año con
aviso si falta el siguiente. Pantalla de constantes legales con su norma.
**Hecho cuando:** agregar una EPS no requiere desplegar código.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 8

Hecho: `CatalogosDelSistema` —las cinco pestañas del prototipo, lo que enseña cada una y las frases
de cada acción—, `ConstantesLegales`, `CatalogoController` con sus siete acciones de escritura, los
tres `FormRequest` y la pantalla `Proveedor/Catalogos`, con los componentes compartidos
`TablaDeCatalogo` y `ModalDeCatalogo`. **Agregar una EPS desde el panel la ofrece en el formulario de
empleado de todas las empresas en la petición siguiente**, y hay prueba. **Nada se borra:** las
entidades y los municipios se desactivan —dejan de ofrecerse y siguen leyéndose en quien ya los
tenía— y los festivos estrenaron `deleted_at`, que era la única tabla de catálogo sin borrado
lógico; volver a cargar una fecha quitada revive su fila en vez de chocar con el índice único. El
aviso del calendario sale **desde octubre** si falta el año siguiente, y en cualquier mes si falta el
del año en curso. **Constantes legales y umbrales son de solo lectura y es una decisión, no una
tarea a medias**: los números legales viven en `config/reglas_laborales.php` porque así lo exige la
regla del motor, y los cuatro umbrales que pide el capítulo 8 dependen de decisiones abiertas —D.2,
D.3, la demostración y la mora—, así que la pestaña dice de cuál depende cada uno en vez de
inventarlos.

---

## Fase 2 — Motor de cálculo y novedades

### [x] F2-01 · Pruebas del motor, primero

Escribe los diez casos obligatorios. Todos fallan, que es lo esperado.
**Hecho cuando:** los diez existen y fallan por falta de implementación, no por error de sintaxis.
**Fuente:** `docs/02-arquitectura-y-datos.md` 4.2

Hecho: `tests/Feature/MotorDeCalculoTest.php` con los diez casos de la sección 4.2, y las firmas del
motor en `app/Dominio/Calculo/` —`MotorDeCalculo`, `SaldoDeVacaciones`, `PeriodoDePrueba`—, donde cada
método lanza `CalculoSinImplementar`. **Los diez fallan con esa excepción**, es decir por falta de
implementación y no por sintaxis ni por una clase que no existe. Van en el grupo `motor`, que
`composer verificar` excluye para no dejar la verificación en rojo; `composer motor` los corre y los
enseña en rojo. Dos reglas salieron de leer los casos entre ellos y están anotadas: la acumulación va
en **meses comerciales de treinta días** y **el mes se causa completo al cerrarse**, no día a día.

### [x] F2-02 · Motor de cálculo

Las tres funciones de conteo con sus nombres explícitos y el cálculo de saldo como función, no como
columna.
**Hecho cuando:** los diez casos pasan.
**Fuente:** `.claude/rules/20-calculo-y-reglas-legales.md`

Hecho: los diez casos obligatorios pasan y `composer verificar` los corre sin exclusiones —el script
`motor` y `--exclude-group=motor` se fueron, y `CalculoSinImplementar` se borró—. `MotorDeCalculo`
tiene las tres reglas de conteo con sus nombres distintos más `diasComercialesDelMes()`,
`cumpleMesesEl()`, `diaHabilAnteriorParaAlertas()` y `diasDeAnticipacion()`; `SaldoDeVacaciones`
suma mes calendario a mes calendario, **solo los ya cerrados**, y `PeriodoDePrueba` lee sus tres
constantes de `config/reglas_laborales.php`. **La base comercial quedó con sus dos caras**: el día 31
no cuenta y un mes cubierto entero son treinta días aunque tenga veintiocho —lo segundo se dedujo, y
la pregunta está en `ESTADO.md`—. Los dos conteos de días corridos escritos a mano ahora llaman al
motor y el privado de `RenovacionDeContrato` desapareció: **no queda ninguna cuenta laboral fuera de
`app/Dominio/Calculo/`**. El saldo de la ficha del empleado dejó de ser un hueco y enseña el número
calculado, con `Formato::diasDecimales()` nuevo para los días con fracción. Ocho casos más se
sumaron a la batería, sin tocar los diez.

### [x] F2-03 · Flujo compartido de novedades

Modal de documento con validación al salir del campo, y modal grande con ficha del empleado. Un solo
componente reutilizado por los cinco módulos.
**Hecho cuando:** el componente existe y una novedad de prueba lo usa.
**Fuente:** `docs/03-diseno-e-interfaz.md` 5.5

Hecho: `resources/js/Components/ModalDeNovedad.vue` con los cinco pasos de la sección 5.5 —modal
pequeño que pide solo el documento, consulta al servidor **al salir del campo**, error debajo del
campo que no deja continuar, y modal grande con `ResumenDeEmpleado` arriba y el formulario del módulo
en un `slot`—. La búsqueda la resuelve `App\Novedades\EmpleadoDeNovedad`, compartida desde
`HandleInertiaRequests` como prop opcional y pedida con `router.reload({ only: [...] })`: **una sola
búsqueda para los cinco módulos**, sin `axios`. `App\Novedades\TipoDeNovedad` guarda los cinco títulos
del prototipo y su módulo. La ficha del modal es `FichaDeEmpleado::resumenParaNovedad()`, con el saldo
del motor. La novedad de prueba que lo usa es la de `ModalDeNovedad.test.js`, que registra una
incapacidad de mentira por el `slot`; **el primer módulo real que lo llame es F2-04**.

### [x] F2-04 · Incapacidades

Módulo completo. Días corridos calculados en pantalla. Campo de diagnóstico con acceso restringido.
**Hecho cuando:** del 14 al 16 de julio muestra 3 días y el diagnóstico no lo ve quien no tiene permiso.
**Fuente:** `docs/01-especificacion-funcional.md` 6.3 · skill `crear-modulo`

Hecho: la tabla `incapacidades` —colgada de `vinculacion_id`, sin columna de días—,
el modelo `Incapacidad` con `dias()` llamando a `MotorDeCalculo::diasCorridos()`, `ListadoDeIncapacidades`
sobre el armazón de `Listado` (REV-01), la pantalla `Incapacidades/Index` sobre `useListado` (REV-02) y
enchufada al flujo compartido de novedades (F2-03) con `ModalDeNovedad`. **El código de diagnóstico no
se recorta en ningún punto de la pantalla**: el único permiso que la sección 6.3 exige es el del módulo,
y quien llega al listado ya lo tiene por el middleware `modulo:incapacidades`. La única fuga posible era
la auditoría —otro módulo, otro permiso—, así que `codigo_diagnostico` entró a la lista `OCULTAS` de
`ObservadorDeAuditoria`, con su prueba. Los días de la incapacidad que se está digitando se calculan en
el servidor y llegan por una recarga parcial (`diasDeIncapacidad`), igual que la proyección del modal de
renovación de contrato: en el modal no se resta ninguna fecha. **Edición y borrado quedan para F2-10**,
que ya los tiene asignados para los tres módulos de novedades a la vez.

### [x] F2-05 · Vacaciones

Módulo completo con acumulación automática, cálculo en días hábiles y descuento del saldo.
**Hecho cuando:** registrar y eliminar unas vacaciones deja el saldo exactamente como estaba.
**Fuente:** `docs/01-especificacion-funcional.md` 6.4

Hecho: la tabla `vacaciones` —colgada de `vinculacion_id`, sin columna de días—, el modelo
`Vacacion` con `dias()` llamando a `MotorDeCalculo::diasHabilesVacaciones()`, `ListadoDeVacaciones`
sobre el armazón de `Listado` (REV-01), la pantalla `Vacaciones/Index` sobre `useListado` (REV-02) y
enchufada al flujo compartido de novedades (F2-03) con `ModalDeNovedad`. **El descuento del saldo no
escribe nada aparte**: `SaldoDeVacaciones::al()` gana un tercer sumando, `diasDisfrutadosHasta()`, que
suma los días hábiles de las vacaciones ya registradas con inicio hasta esa fecha; como `Vacacion` usa
borrado lógico, eliminar una fila la saca sola de esa suma la próxima vez que se pida el saldo, sin
ninguna operación inversa. Es exactamente el «hecho cuando» de la tarea, probado en
`VacacionesTest.php` guardando y eliminando un periodo y comparando el saldo antes, con la vacación
puesta y después de borrarla. **Edición y borrado por pantalla quedan para F2-10**, igual que en
incapacidades: lo que esta tarea necesitaba de «eliminar» es que el cálculo lo soporte, no una acción
de la interfaz. El filtro del listado es solo el rango de fechas —«Empleado» y «Cargo», que también
lista el documento 1 para este módulo, son F4-01, igual que la búsqueda por nombre que le falta a
incapacidades—.

### [x] F2-06 · Advertencia de saldo insuficiente

Compara contra el saldo proyectado a la fecha de disfrute, no al de hoy. El mensaje dice cuántos días
faltan. No bloquea.
**Hecho cuando:** el caso de los 14 días para el 28 de agosto muestra «faltan 1,25 días».
**Fuente:** `docs/01-especificacion-funcional.md` 6.4

Hecho: `App\Vacaciones\AdvertenciaDeSaldo`, con el texto exacto del documento 3 —«Al 28 de
agosto tendrá 12,75 días disponibles. Estás registrando 14 días: faltan 1,25 días.»—, armado sobre
`SaldoDeVacaciones::al()` y `::faltantePara()`, que ya existían desde F2-02 y no se tocaron.
`VacacionController` comparte una nueva prop `saldoDeVacaciones` —`Inertia::optional`, igual que
`diasDeVacaciones`— que resuelve la vinculación del `vinculacion_id` digitado y, en cuanto hay un
periodo con sentido, calcula el saldo proyectado a la fecha de disfrute; sin fechas todavía entrega
solo el saldo de hoy. **La franja permanente del documento 3, 5.5** —saldo actual y saldo
proyectado, con el aviso ámbar debajo cuando no alcanza— vive en `Pages/Vacaciones/Index.vue`, bajo
los campos de fecha del formulario; se vuelve a pedir con la misma recarga parcial que ya usaban los
días hábiles, ahora también disparada al elegir el empleado y no solo al cambiar una fecha. **No
bloquea**: no se tocó `RegistrarVacacion` ni `guardar()`, que es la regla explícita del documento 1.
`Formato::diaYMes()` es nuevo —«28 de agosto», sin año, que es como se lee la fecha de disfrute en la
frase—. Pruebas nuevas en `VacacionesTest.php` (el caso exacto de los 14 días, que no avisa cuando
alcanza, la franja con solo el saldo de hoy y la integración del periodo digitado) y en
`Pages/Vacaciones/Index.test.js`, la primera prueba de Vitest de esta pantalla porque es la primera
vez que tiene lógica propia que `ModalDeNovedad.test.js` no cubre.

### [x] F2-07 · Licencias, ausencias y suspensiones

Módulo con el selector de tres tipos. Días corridos, domingos y festivos incluidos. Efecto
proporcional sobre la acumulación de vacaciones.
**Hecho cuando:** la licencia del 16 de marzo al 15 de abril reduce a la mitad la acumulación de
ambos meses.
**Fuente:** `docs/01-especificacion-funcional.md` 6.5

Hecho: la tabla `licencias` —**una sola tabla con un campo de clasificación**, no tres, como pide el
documento—, el enumerado `TipoDeLicencia`, el modelo `Licencia` con `dias()` llamando a
`MotorDeCalculo::diasCorridos()`, `ListadoDeLicencias` sobre el armazón de `Listado` (REV-01), la
pantalla `Licencias/Index` sobre `useListado` (REV-02) y enchufada al flujo compartido de novedades
(F2-03). **El efecto sobre la acumulación era el hueco que F2-05 dejó anotado**:
`MotorDeCalculo::diasDeSuspensionDelMes()` devolvía cero porque no había tabla de dónde leer; ahora
suma los días de cada licencia que caen en ese mes con `diasSuspendidosDelMes()`, y solo si la
empresa tiene activo `suspensiones_afectan_vacaciones` —el parámetro se lee **dentro del motor**, no
en quien llama—. La aritmética de la proporción ya estaba escrita y probada desde F2-02
(`acumulacionProporcional()`), así que esta tarea no tocó el saldo. El caso obligatorio pasa: del 16
de marzo al 15 de abril deja marzo y abril en 0,625 días cada uno. **Nada se escribe aparte**: como
`Licencia` usa borrado lógico, eliminar una la saca sola de la reducción la próxima vez que se pida
el saldo. Filtros del listado: rango de fechas y tipo; «Empleado» es F4-01, igual que en los otros
dos módulos de novedades. Edición y borrado por pantalla siguen siendo F2-10.

### [x] F2-08 · Aumentos y promociones

Módulo con salario y cargo actuales de solo lectura, y el histórico de la persona.
**Hecho cuando:** guardar un movimiento actualiza el registro principal y aparece en el histórico.
**Fuente:** `docs/01-especificacion-funcional.md` 8.1

Hecho: la tabla `movimientos` —la primera del grupo que **guarda el valor anterior**, porque al
guardar el registro principal ya cambió y el histórico tiene que enseñar «el anterior frente al
nuevo»—, el enumerado `TipoDeMovimiento` con la regla del cargo repetido en el aumento salarial
(`cargoResultante()`), `Vinculacion::registrarMovimiento()` con **las dos escrituras en una sola
transacción**, `ListadoDeMovimientos` sobre el armazón de `Listado` (REV-01), `MovimientoController`,
`RegistrarMovimiento`, `MovimientosExport` y la pantalla `Movimientos/Index` sobre `useListado`
(REV-02) enchufada al flujo compartido de novedades (F2-03). El cargo y el salario vigentes llegan
por la recarga parcial `situacionActual` y van de solo lectura; el nuevo salario y la fecha son lo
único que se digita en un aumento. La pestaña «Movimientos» de la ficha del empleado dejó de estar
vacía: enseña «Analista Senior → Coordinadora de Operaciones» y «$ 2.700.000 → $ 3.200.000». Filtros
del listado: rango de fecha de efectividad y tipo de movimiento; «Cargo» es F4-01, como «Empleado»
en los tres módulos de novedades.

### [x] F2-09 · Terminaciones

Módulo con los siete tipos de salida y los motivos dependientes. Autocompletado en expiración de
plazo y obra terminada. Categoría de rotación deducida.
**Hecho cuando:** cambiar el tipo de salida recarga los motivos y el empleado pasa a inactivo.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 7

Hecho: la tabla `terminaciones`, los enumerados `TipoDeSalida` —los siete tipos, sus 35 motivos, el
motivo automático y la categoría que deducen— y `CategoriaDeRotacion`,
`Vinculacion::registrarTerminacion()` con **las dos escrituras en una sola transacción** —la salida y
el paso a inactivo—, `ListadoDeTerminaciones` sobre el armazón de `Listado` (REV-01),
`TerminacionController`, `RegistrarTerminacion`, `TerminacionesExport` y la pantalla
`Terminaciones/Index` sobre `useListado` (REV-02) enchufada al flujo compartido de novedades (F2-03).
**La categoría de rotación no se guarda**: la deduce el tipo de salida, que es lo que pide la sección
7.4. El motivo de la expiración del plazo y de la obra terminada lo completa el servidor y se ignora
el que llegue. La confirmación con la consecuencia llega escrita del servidor con el nombre del
empleado. **La deuda de F1-05 quedó pagada**: `fechaDeSalida()` devuelve la fecha registrada, y de
ella salen el énfasis de los 30 días del reingreso y el mes en que el saldo deja de acumular;
`fecha_fin` queda solo como respaldo del personal histórico que traerá la carga inicial.

### [x] F2-10 · Edición y borrado de novedades

Todas editables y eliminables sin límite de antigüedad, con confirmación que explica la consecuencia
calculada.
**Hecho cuando:** eliminar unas vacaciones muestra el saldo antes y después en la confirmación.
**Fuente:** `docs/01-especificacion-funcional.md` 6.2

Hecho: las **tres novedades del capítulo 6** —incapacidades, vacaciones y licencias— se editan y se
eliminan desde su listado, sin límite de antigüedad y aunque el empleado ya no esté activo. La
interfaz `NovedadEditable`, `ConfirmacionDeBorrado` —la plantilla literal del documento 3, 6.3 con la
consecuencia ya calculada—, `DetalleDeNovedad` —la prop `novedad`, gemela de `EmpleadoDeNovedad`—,
`SaldoDeVacaciones::sin()`, las tres `Editar*` heredando de sus `Registrar*`, las seis rutas
`PUT`/`DELETE`, el componente compartido `ModalDeBorrado` y el composable `useNovedad`.
`ModalDeNovedad` estrenó modo edición: el mismo modal, sin el paso del documento. **Movimientos y
terminaciones quedaron fuera a propósito y su decisión está en `REV-03`.**

### [x] F2-11 · PUNTO DE CONTROL — revisión

Ejecuta el skill `revisar`.

Informe en `bitacora/revision-2026-08-22.md`. Aislamiento, motor, interfaz y seguridad sin hallazgos:
las diez tablas de negocio con trait, RLS y caso propio; los diez casos obligatorios del motor
pasando y ningún conteo laboral fuera de `app/Dominio/Calculo/`; ninguna confirmación que pregunte
«¿está seguro?». Se arreglaron tres duplicaciones —el bloque de paginación copiado **ocho** veces, ya
extraído a `Listado::paraLaPantalla()`; la lectura de las dos fechas digitadas copiada **tres** veces,
ya en `App\Novedades\PeriodoDigitado`; y la guardia `PANTALLAS` de `useListado.test.js`, que cubría
seis de las ocho pantallas—. Quedó `REV-04` por la pantalla de auditoría, que no tiene tarea en
ninguna fase.

---

## Fase 3 — Alertas

### [x] F3-01 · Infraestructura de alertas

Tabla `alertas_enviadas` con clave de idempotencia e índice único. Proceso diario que recorre
empresas una por una. Cola con reintentos. Plantilla base de correo.
**Hecho cuando:** ejecutar el proceso dos veces el mismo día no duplica ningún correo.
**Fuente:** `docs/02-arquitectura-y-datos.md` cap. 5 · skill `agregar-alerta`

Hecho: la tabla `alertas_enviadas` con su índice único `(tenant_id, clave)` y su política RLS, el
modelo `AlertaEnviada`, los enumerados `TipoDeAlerta` —las trece del capítulo 9— y `EstadoDeAlerta`,
`AlertaPorEnviar`, la interfaz `ReglaDeAlerta`, `CatalogoDeReglas`, `DestinatariosDeAlertas`,
`RegistroDeAlertas`, `ProcesoDiarioDeAlertas` con su `ResumenDelProceso`, el job `EnviarAlerta`, el
mailable `CorreoDeAlerta` con la plantilla base `correos/alerta.blade.php` y el comando
`alertas:diarias`, programado a las 06:00 de `America/Bogota`. **La idempotencia la impone el índice
y no una comprobación previa**: `RegistroDeAlertas` inserta y captura el rechazo dentro de su propio
savepoint, porque una comprobación previa pierde la carrera contra un segundo proceso. El recorrido
va empresa por empresa con `ContextoTenant::paraTenant()`, y una empresa que falla no detiene a las
demás. El proceso evalúa cada día su fecha **y las de los días no hábiles que vienen detrás**
(`fechasQueLeTocanA()`), así que un viernes evalúa viernes, sábado, domingo y el lunes si es festivo;
ajustar el texto al adelanto es F3-02. **Ninguna regla de disparo existe todavía y no es un olvido**:
las once llegan en F3-03, F3-04 y F3-05, y el armazón se prueba con `Tests\Fixtures\ReglaDePrueba`,
la gemela de `RegistroDePrueba`.

### [x] F3-02 · Corrimiento al día hábil

Si la fecha cae en sábado, domingo o festivo, se envía el día hábil anterior y el texto se ajusta.
**Hecho cuando:** una alerta programada para domingo llega el viernes diciendo «en dos días (fecha)».
**Fuente:** `docs/01-especificacion-funcional.md` 9.1

Hecho: `App\Dominio\Alertas\ReferenciaTemporal`, la frase compartida por las once alertas. Recibe las
dos fechas que la regla de disparo ya tenía —la del envío y la del hecho— y escribe «hoy (21 de
agosto)», «mañana (…)», «en dos días (…)» o «en 15 días (…)», siempre con la fecha exacta entre
paréntesis (9.1). **La mitad del calendario ya venía de F3-01**: el proceso adelanta la evaluación y
guarda `fecha_programada` corrida; lo que faltaba era que el texto no mintiera al adelantarse. Las
cantidades de una cifra van con letras y las de dos con número, que es lo que hacen literalmente las
plantillas de la sección 5.4 del documento 2 —«en dos días» y «en 15 días»—. `ReglaDePrueba` ahora
redacta con esta clase, así que el corrimiento se comprueba de punta a punta: sábado → «mañana»,
domingo → «en dos días», lunes festivo → «en tres días». El caso obligatorio del motor —«se envía el
viernes anterior y el texto dice "en dos días" con la fecha»— quedó completo por primera vez: le
faltaba justo la mitad del texto. No redacta nada del pasado en relativo: un hecho anterior al envío
lanza excepción, porque ninguna de las once plantillas dice «hace dos días».

### [x] F3-03 · Alertas de periodo de prueba

Las tres de contrato indefinido y las dos de término fijo, con sus textos exactos y la fecha entre
paréntesis.
**Hecho cuando:** el caso del ingreso el 15 de abril produce las tres alertas en 14/05, 30/05 y 13/06.
**Fuente:** `docs/01-especificacion-funcional.md` 9.2 y 9.3

Hecho: las cinco reglas en `app/Dominio/Alertas/Reglas/`, con `ReglaDePeriodoDePrueba` de base —quién
es candidato, que el empleado esté activo y de dónde sale la fecha— y `ReglaDeVisperaDePrueba`
compartiendo el texto que el documento le da idéntico a las dos vísperas. Ya están registradas en el
catálogo, así que el proceso diario las manda. **La fecha del hecho es siempre el último día del
periodo de prueba**, que sale de `PeriodoDePrueba::terminaEl()`: ninguna regla cuenta días. La de los
15 días se dispara **quince días antes del final y no cuarenta y cinco después del ingreso**: en el
ejemplo del documento coinciden, pero sobre febrero no, y manda el mensaje —«completará en 15 días»
tiene que ser cierto—. La del mes cumplido es la única que no usa `ReferenciaTemporal`: su plantilla
escribe la fecha entera, con año, porque el final puede caer en el año siguiente. **Los asuntos
también se ajustan al corrimiento**: los tres de la sección 5.4 traen «Mañana», «quedan 15 días» y
«quedan 2 días» escritos a mano, y un asunto que miente es lo primero que se lee. La obra o labor
recibe el calendario del indefinido, que es el que le corresponde por 9.6. Dos pruebas cubren los
contratos tan cortos que el aviso caería antes del ingreso.

### [x] F3-04 · Alertas de contrato a término fijo

Plazo de renovación a 32 días, efecto del silencio a 30, última renovación corta y cercanía al tope.
**Hecho cuando:** las cuatro se disparan en sus fechas y hay prueba de cada una.
**Fuente:** `docs/01-especificacion-funcional.md` 9.4 · `docs/06-decisiones-pendientes.md` D.3

Hechas **las dos del preaviso**: `PlazoDeRenovacion` y `EfectoDelSilencio`, sobre la base compartida
`ReglaDePreavisoDelFijo`, registradas en el catálogo y con sus textos literales de la sección 5.4.
Las dos cuelgan de la fecha límite del preaviso —`HistorialDeContrato::fechaLimiteDePreaviso()`—, así
que **el «32» no está escrito en ninguna parte**: es el preaviso configurado más los dos días que el
propio mensaje anuncia. La condición «si no se ha registrado prórroga ni terminación» se cumple por
construcción y tiene prueba.

**Hecho también lo que trajo D.3:**

- **`CercaniaAlTope`, la alerta nueva.** Un solo aviso, tres meses antes de
  `HistorialDeContrato::fechaDelTopeLegal()`, con el texto de 5.4 y la fecha del tope escrita entera.
  La anticipación vive en `config/alertas.php`, que es nuevo: es criterio y no ley, y el documento 2,
  8.1, manda sacarla del código. Cuelga del tope y no del vencimiento, así que las prórrogas que se
  registren en medio no la mueven; en un contrato anterior a la reforma cuenta desde el 25 de junio
  de 2025 (C.2) y tiene prueba de ese caso.
- **`EfectoDelSilencio` escoge entre tres consecuencias.** La primera frase no cambia; la segunda
  depende de si la prórroga automática cabe entera antes del tope, se trunca contra él, o ya no cabe
  ninguna porque el contrato vence en el tope. Las decide comparando `fechaDelTopeLegal()` con
  `finDeLaProrrogaAutomatica()`, el método nuevo del historial. **El «prácticamente llega al tope» de
  D.3 no se implementó**: un margen de tolerancia sería un umbral legal inventado, y una prórroga que
  cabe entera recibe el texto normal, que es cierto.

**Hecho en la tercera pasada — el cupo corregido y la cuarta alerta:**

- **El cupo de prórrogas cortas quedó en uno solo, de tres.** `config/reglas_laborales.php` tiene
  ahora la constante `prorrogas_cortas` en 3 y las dos viejas —`prorrogas_cortas_pactadas` en 4 y
  `prorrogas_cortas_automaticas` en 3— desaparecieron, igual que
  `EventoContractual::limiteDeProrrogasCortas()`: el cupo es de la cadena y por eso vive donde se
  cuenta la cadena. `HistorialDeContrato::prorrogasCortas()` y `prorrogasCortasRestantes()` ya no
  reciben el evento y filtran por `esProrroga()`, y las tres llamadas de `RenovacionDeContrato`
  dejaron de pasarlo. **El caso mezclado tiene prueba**: dos pactadas cortas y una automática corta
  agotan el cupo, que es justo lo que el conteo viejo dejaba pasar.
- **El campo «tipo de prórroga» sigue en el modal** —es la decisión D.1— alimentando el historial y
  la frase «sería la 2ª prórroga, pactada por escrito». Lo único que perdió es el reparto del cupo, y
  con él el texto de ayuda del selector, que decía que el límite era distinto en cada caso.
- **`UltimaRenovacionCorta`, la cuarta alerta.** Cuelga de `ReglaDePreavisoDelFijo` y dispara en el
  mismo día que `PlazoDeRenovacion` —los dos días de margen pasaron a la clase base, para que «el
  mismo momento» sea literalmente el mismo número—. Le toca al contrato cuyo **tramo vigente** es la
  prórroga corta que agotó el cupo (`HistorialDeContrato::elTramoVigenteAgotoElCupoCorto()`): si
  después se renueva por un año, el aviso no se repite. Texto de 5.4, con el número de prórrogas
  precisado a las cortas, que son las que gastan cupo.
- `config/validaciones.php` no se tocó: `renovacion_prorroga_minima` sigue en `advierte`, que es B.3
  y sigue abierta.

### [x] F3-05 · Resúmenes mensuales

Contratos vencidos sin gestionar, acumulación de 15 días, año cumplido sin disfrute mínimo y obra o
labor prolongada.
**Hecho cuando:** el resumen llega agrupado en un solo correo, no uno por empleado.
**Fuente:** `docs/01-especificacion-funcional.md` 9.5 y 9.6

**Hechos los cuatro**, y el «hecho cuando» de la tarea está cumplido y probado: el resumen sale en un
solo correo por empresa, sin vinculación, con el listado dentro.

- **`ResumenMensual`, la base compartida.** Las tres cuelgan de ella y de ella salen las tres
  diferencias con las nueve alertas individuales: **salen el último día hábil del mes**
  (`MotorDeCalculo::ultimoDiaHabilDelMes()`, nuevo), **van agrupadas** con
  `AlertaPorEnviar::paraLaEmpresa()` —que existía desde F3-01 y hasta hoy no usaba nadie— y **no se
  mandan vacías**: un correo mensual que dice «0 contratos vencidos» enseña a ignorar el remitente.
  El día de salida es hábil por definición, así que el corrimiento de 9.1 no tiene nada que hacer
  aquí y el proceso lo evalúa exactamente una vez al mes.
- **`ContratosVencidosSinGestionar`** (9.4). Solo término fijo —el indefinido y la obra o labor no
  tienen fecha que superar—, y «sin gestionar» se cumple por construcción, igual que en
  `ReglaDePreavisoDelFijo`: la terminación deja la vinculación inactiva y la prórroga mueve el
  vencimiento. Mira contra el corte y no contra el fin del mes calendario, porque su texto habla en
  pasado y tiene que ser cierto el día que sale. `MotorDeCalculo::diasTranscurridos()` es nuevo: los
  «días transcurridos» de la plantilla no son días corridos y contarlos con la regla de al lado
  escribiría un número falso.
- **`AcumulacionDeQuinceDias`** (9.5). Los quince días **no están escritos**: son doce meses a 1,25,
  la constante del artículo 186. Mira contra el fin del mes calendario porque el texto dice
  «completaron este mes» y el saldo causa el mes completo al cerrarse. Es un cruce y no un umbral —al
  cierre de este mes alcanza y al del anterior no—, así que nadie reaparece todos los meses hasta que
  se tome vacaciones. También le aplica a la obra o labor, que es lo que manda 9.6.
- **`AnioSinDisfruteMinimo`** (9.5). Cada año de servicio, no solo el primero: el motivo está en la
  propia plantilla, «el mínimo anual que exige la ley». «Continuos» se lee como un solo periodo
  registrado de seis días hábiles o más, que sale de `disfrute_minimo_dias_habiles`.
- **`ObraOLaborActivos`, el cuarto y el único bimestral.** D.2 lo desbloqueó cambiándole la forma en
  vez de darle un número: **no hay umbral**, es el listado de todos los contratos de obra o labor
  activos de la empresa, cada dos meses, para visibilidad. Cada línea lleva nombre, cargo, fecha de
  ingreso y cuánto lleva, con los meses del motor y la frase de `Formato::tiempoTranscurrido()`. Trae
  también al que ingresó ese mismo mes, que es exactamente lo que D.2 decidió: no existe criterio
  legal para decir a partir de cuándo una obra es «prolongada», así que ningún corte de antigüedad se
  podría sostener.
- **`ResumenMensual` ganó una cadencia y no solo un día**, el gancho `saleEsteMes(Carbon $corte)`, que
  por defecto devuelve `true`. Las tres mensuales no cambiaron de código.
- **La cadencia se ancla en los meses impares** —enero, marzo, mayo, julio, septiembre y noviembre—,
  el último día hábil de cada uno. Es decisión de implementación y no de Angela, que solo pidió «cada
  dos meses»: hacía falta un ancla para que el disparo sea determinista y por tanto idempotente, y la
  paridad del mes calendario lo consigue sin guardar estado ni depender de cuándo se dio de alta cada
  empresa. Cambiarla es cambiar una condición en un sitio.
- **`TipoDeAlerta::ObraOLaborProlongada` se renombró a `ObraOLaborActivos`**, con su valor
  `obra_o_labor_activos` y su etiqueta «Contratos de obra o labor activos». El nombre viejo describía
  la alerta que D.2 descartó. La prueba del catálogo enumera ahora los trece tipos.
- **`config/alertas.php` no creció**: el renglón del umbral que esperaba ahí ya no existe, y así lo
  dicen ahora su comentario de cabecera y el renglón del panel del proveedor, que decía «sin decidir».

### [x] F3-06 · Entregabilidad y registro

Proveedor transaccional configurado. Registro de envíos consultable con destinatarios, estado e
intentos. Panel de alertas dentro del sistema como canal alterno.
**Hecho cuando:** un correo de prueba llega a bandeja de entrada y queda registrado.
**Fuente:** `docs/02-arquitectura-y-datos.md` 5.5

Hechas las tres cosas de la sección 5.5, y el «hecho cuando» está probado de punta a punta:
`alertas:prueba <empresa> <correo>` manda el correo **por el mismo camino que las alertas de verdad**
—`RegistroDeAlertas`, `EnviarAlerta`, `CorreoDeAlerta`— y deja su fila en `alertas_enviadas`, que se
consulta en la sección Alertas.

- **`ProveedorDeCorreo` es lo único que se puede comprobar del proveedor desde dentro**: si el
  transporte configurado es transaccional y si el remitente ya está en el dominio propio. Ahí no hay
  ninguna credencial a propósito —la sección 7.1 manda los secretos fuera del repositorio— y por eso
  el proveedor se configura en `.env`, que ahora explica las cinco líneas de Postmark, Resend o SES y
  **la mitad que no está en el archivo**: SPF, DKIM y DMARC, que viven en el DNS. `advertencia()`
  advierte y no bloquea: en local el correo va al log a propósito, porque el flujo entero tiene que
  poder recorrerse sin credenciales de nadie.
- **El correo de prueba es el único envío que no es idempotente por día**, y es la decisión de fondo
  de la tarea. `AlertaPorEnviar::deEntregabilidad()` pone la hora en la clave: la regla 5.2 protege de
  que el proceso diario repita lo que nadie le pidió, y aquí pasa lo contrario —quien está corrigiendo
  el SPF de su dominio manda, mira la bandeja, corrige el DNS y vuelve a mandar la misma mañana—.
- **`TipoDeAlerta` ganó un caso que no es una alerta**, `PruebaDeEntregabilidad`, con
  `esDelCalendario()` separándolo de las trece del capítulo 9. Va en `alertas_enviadas` como todo lo
  demás porque esa tabla **es** el registro de envíos: un correo de prueba sin rastro no probaría
  justamente lo que se quiere probar. Su módulo es `Configuracion` —solo el superadministrador—
  porque no habla de ningún empleado. Dos pruebas lo vigilan: que ninguna regla registrada lo dispare
  y que sea el único tipo fuera del calendario.
- **El panel de alertas es el módulo `Alertas`**, el único que no está en la tabla 4.2 del documento 3:
  lo pide el documento 2, y va en el grupo «Sistema» al lado de Auditoría porque es lo mismo que ella,
  un registro de solo lectura. `ListadoDeAlertas` sobre el armazón de `Listado`, `AlertaController`,
  la pantalla `Alertas/Index` sobre `useListado` y una sola ruta, sin ninguna de escritura.
- **Tener el módulo `Alertas` no abre las alertas de los demás módulos.** `AlertasDelUsuario` decide
  los tipos visibles con el mismo `TipoDeAlerta::modulo()` que usa `DestinatariosDeAlertas` para
  decidir a qué correos sale cada aviso, así que el panel y el buzón **no pueden discrepar**: el canal
  alterno alcanza exactamente a quien habría recibido el correo y a nadie más. El filtro ofrece solo
  los tipos visibles —una lista completa contaría qué alertas existen y dejaría deducir por el número
  de resultados si a alguien le llegaron— y pedir un tipo ajeno por la dirección tampoco saca nada.
- **El detalle no vuelve al servidor.** El asunto, el cuerpo y los destinatarios viajan con la fila y
  son **los que se enviaron**, no los que se calcularían hoy: si el cargo de la persona cambió
  después, el registro sigue contando lo que llegó al buzón. El modal es la otra mitad del «canal
  alterno»: el aviso entero se lee ahí, con el mismo texto del correo.
- El listado abre por lo más reciente, al revés que los otros ocho, porque un registro de envíos se
  consulta siempre por lo de ayer. `Formato::fechaYHora()` es nuevo y es la única fecha de la casa con
  hora: «el 25 de diciembre» no le responde a un cliente que dice que a esa hora no le había llegado
  nada.

### [!] F3-06b · Entregabilidad real — bloqueada: no es código, la hace David una vez

**No la tome ninguna sesión.** Aquí no hay nada que programar: F3-06 dejó construido todo lo
programable y sus pruebas en verde. Lo que falta está fuera del repositorio y solo lo puede hacer una
persona con acceso a la cuenta del proveedor y al DNS del dominio.

Existe como tarea propia porque **el «hecho cuando» de F3-06 —«un correo de prueba llega a bandeja de
entrada»— no se cumplió**, y esa tarea quedó `[x]` de todos modos. Sin esto, el sistema manda los
correos al archivo de registro y ninguna alerta sale de la máquina: la promesa que vende el producto
no se está cumpliendo, aunque el backlog esté en verde y las pruebas pasen.

Los cuatro pasos, en orden:

1. Abrir cuenta en un proveedor transaccional —Postmark, Resend o SES—.
2. Llenar las cinco líneas `MAIL_*` del `.env`. Hoy está en `MAIL_MAILER=log`, que es lo correcto para
   desarrollo: el correo va a `storage/logs/laravel.log` y el flujo entero se puede recorrer sin
   credenciales de nadie. Las cinco líneas de cada proveedor están comentadas en `.env.example`.
3. Crear **SPF, DKIM y DMARC** en el DNS del dominio propio. Es la mitad que no vive en ningún archivo
   del repositorio, y sin los tres el proveedor entrega y el buzón manda a spam.
4. Correr `php artisan alertas:prueba <empresa> <correo>` y **mirar en qué carpeta cae**. Ese comando
   manda por el mismo camino que las alertas de verdad, así que si ese correo llega a bandeja de
   entrada, llegan todas. Es el único envío que no es idempotente por día, justamente para poder
   corregir el DNS y reintentar la misma mañana.

**Hecho cuando:** el correo de `alertas:prueba` llega a bandeja de entrada —no a spam— y queda su
fila en el registro de envíos, consultable en la sección Alertas.

---

## Fase 4 — Reportería y panel de inicio

### [x] F4-01 · Exportación y filtros por módulo

Botón en cada módulo que respeta filtros y lo dice. Todas las exportaciones de novedades traen
documento, nombre y cargo. Los filtros de cada módulo son los del documento 1.
**Hecho cuando:** aplicar tres filtros y exportar produce exactamente esos registros con esas columnas.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 11

Hecho: **los cinco filtros que faltaban de la tabla 11.3** y el «hecho cuando» probado de punta a
punta —tres filtros puestos a la vez en vacaciones y la exportación trayendo exactamente el registro
que la pantalla enseña—. `tests/Feature/ExportacionesYFiltrosTest.php`, 22 casos.

- **`App\Contratacion\FiltraPorEmpleado`, el gemelo de `OrdenaPorEmpleado`.** Allí se ordena por los
  datos de la persona y aquí se filtra por ellos, con la misma división: el listado que parte de
  `vinculaciones` y el que parte de la tabla de la novedad, que tiene un salto más. El `concat_ws` de
  la búsqueda **quedó escrito una sola vez**: `ListadoDeEmpleados` perdió su copia privada.
- **«Empleado» es una caja de búsqueda y no un selector**, y es la misma de empleados: nombre completo
  o documento, con la clave `buscar`, así que `useListado` la maneja sin cambiarle una línea. Un
  desplegable de doscientas personas no se recorre, y quien registra una novedad sabe el nombre o la
  cédula. La lleva incapacidades, vacaciones y licencias.
- **«Cargo» es el cargo vigente de la vinculación**, el mismo que enseña la columna y va a la
  exportación: filtrar por «Analista de compras» devuelve exactamente las filas que dicen eso. Ninguna
  de las cinco tablas de novedad guarda el cargo del día del hecho. La lista de opciones es
  `ListadoDeEmpleados::cargos()`, que ya existía. Lo llevan vacaciones y movimientos.
- **«Estado del contrato» son los tres estados que la columna «Estado» ya pintaba** desde F1-08 —al
  día, plazo de preaviso pasado, vencido sin gestionar—, con nombre para poder pedirlos por la
  dirección. No es una regla nueva: `VigenciaDelContrato::estadoDeGestion()` es de donde ahora sale
  `tono()`, así que el color de la fila y el filtro que la trae **no pueden discrepar**, y
  `acotarAlEstado()` responde lo mismo en SQL. Tres pruebas lo vigilan: cada estado trae lo suyo, el
  tono de cada fila filtrada es el que le toca, y los tres reparten entre ellos todos los contratos
  sin dejar ninguno fuera.
- **Las siete exportaciones ya traían documento, nombre completo y cargo** (11.2) desde su propia
  tarea; lo que faltaba era una prueba que lo comprobara **en las siete de un tirón**, para que la
  octava no pueda olvidarlo. La de incapacidades ganó la columna «EPS», que el reporte de 11.4 pide
  junto al origen, el diagnóstico y los días.
- **`HistorialDeContrato::diasDePreaviso()`** es nuevo y reúne las tres lecturas de
  `reglas_laborales.preaviso_dias` que había suelta: la fecha límite, las alertas del preaviso y el
  estado de gestión.
- **Auditoría es el octavo módulo de la tabla 11.3 y queda fuera a propósito**: no tiene pantalla
  todavía, y sus cuatro filtros llegan con `REV-04`.

### [x] F4-02 · Caché de saldos

Tabla de caché refrescada por el proceso diario. Nunca fuente de verdad.
**Hecho cuando:** el dashboard suma el saldo de 200 empleados en menos de dos segundos.
**Fuente:** `docs/02-arquitectura-y-datos.md` 3.4

Hecho, y el «hecho cuando» **medido**: con doscientos empleados, sumar el saldo de la planta pasó de
**3,7 segundos a 0,006**, con la misma cifra por los dos caminos. Sin caché no se cumplía el plazo de
la tarea, así que el número de antes no es un adorno: es el motivo.

- **La tabla `saldos_en_cache`**, con `tenant_id`, el trait, su política de RLS y un índice único
  `(tenant_id, vinculacion_id)` que es lo que impide duplicar a nadie al refrescar dos veces, igual
  que la clave de `alertas_enviadas`. Guarda `dias` en decimal de precisión fija —el saldo se compara
  contra los quince días de un periodo completo y con coma flotante esa comparación falla sola—,
  `calculado_al` y `calculado_en`: un número sin la fecha a la que se refiere no se contrasta con nada.
- **Se sale de dos convenciones a propósito y las dos están justificadas en la migración.** No lleva
  borrado lógico —no hay información que proteger: la fila se vuelve a producir entera, y una fila
  muerta que hubiera que excluir en cada lectura solo añadiría una forma de sumar de más— y **no se
  audita**, porque si no el refresco nocturno dejaría una línea sin autor por empleado y por noche.
- **`CacheDeSaldos`, con dos métodos y ni un tercero.** «Nunca la fuente de verdad» se cumple por
  construcción y no por disciplina: **no existe ningún método que devuelva el saldo de una persona**,
  así que la ficha, la advertencia de saldo insuficiente, la confirmación de borrado y los dos
  resúmenes mensuales de vacaciones siguen llamando a `SaldoDeVacaciones`, que calcula. `total()` es
  lo único que lee la tabla y `refrescar()` lo único que la escribe, copiando el resultado del motor:
  no hay una segunda aritmética del saldo que pueda separarse de la primera.
- **`total()` no se olvida de nadie.** Los empleados registrados después del último refresco todavía
  no tienen fila y se calculan ahí mismo: la caché puede dar un número **viejo**, nunca uno **falso**.
  En régimen son tres consultas y ninguna cuenta laboral. El terminado después del refresco sale del
  total el mismo día aunque su fila siga guardada, porque el cruce es contra los activos.
- **`TotalDeSaldos`** lleva la cifra, la fecha a la que se refiere, sobre cuántos se sumó y cuántos se
  calcularon al vuelo. El panel de F4-04 puede escribir «actualizado al 22 de agosto» en lugar de dar
  a entender que la cifra es de este instante.
- **El refresco cuelga del proceso diario** (documento 2, 3.4: «el mismo proceso que envía las
  alertas»), al final de cada vuelta y **en su propio bloque**: un fallo recalculando saldos anota la
  empresa como fallida pero no puede costar un correo, que es lo que se vende. `ResumenDelProceso`
  cuenta las filas refrescadas y las dice en su línea de consola.
- **Diecisiete pruebas nuevas**, entre `CacheDeSaldosTest.php` y los tres casos de aislamiento. Las
  dos que importan tiran en direcciones contrarias: la de los doscientos empleados demuestra que
  sirve, y la de la fila envenenada demuestra que no manda —se le pone 999 y la ficha sigue enseñando
  el número calculado, y el refresco siguiente la corrige sola—. El aislamiento va aparte del armazón
  de `casoDeAislamiento()` porque aquí la fuga no se vería como el dato de otra empresa en pantalla:
  se vería como un total más alto de lo debido, que es la clase de error que nadie nota.

### [x] F4-03 · Panel de inicio, bloque de acción

Las tarjetas de lo que exige atención, cada una enlazando al listado ya filtrado.
**Hecho cuando:** pulsar «contratos por vencer» lleva al listado filtrado.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 12 · `prototipos/sistema.html`

Hechas **las cuatro tarjetas del prototipo**, y el «hecho cuando» probado hasta el final: el enlace de
«contratos vencen en 30 días» abre el listado y **trae exactamente la cifra que la tarjeta enseña**.
`tests/Feature/PanelDeInicioTest.php`, 25 casos.

- **`App\Inicio\BloqueDeAccion`, `TarjetaDeAccion` y `AtencionDelInicio`**, más el componente
  compartido `TarjetaDeAccion.vue` y el `InicioController` —la ruta dejó de ser una función suelta—.
  La pantalla no cuenta nada: la cifra, el texto, el tono y el destino llegan escritos.
- **Las tres cifras acumuladas del capítulo 12 enlazan cada una a lo suyo**: 30 en grande y 60 y 90 al
  pie, con `?vence=30|60|90`. Salen de `ListadoDeContratos::porVencer()`, la misma cuenta que la
  cabecera del módulo, así que el panel y el listado **no pueden discrepar**. La de vencidos pide
  `vence=todos&estado=vencido`, porque el listado abre en noventa días y el que venció en febrero es
  justo el que más urge.
- **Una tarjeta en cero no se pinta**, la misma decisión que `ResumenMensual` tomó con los correos: un
  bloque llamado «Requiere tu atención» que enseña cuatro ceros enseña a no mirarlo. Cuando no queda
  ninguna, la pantalla lo dice con una frase. Y **ninguna tarjeta lleva a un 403**: el módulo lo
  decide `PoliticaDeModulos`, el mismo que responde en la ruta de destino.
- **El filtro `atencion` del listado de empleados** es el destino de las dos tarjetas que no van a
  contratos. No se ofrece en la barra —la tabla 11.3 fija lo que la barra ofrece, y nadie escoge
  «periodos de prueba que terminan esta semana» de un desplegable— pero sí sale como etiqueta
  removible, o quien pulsa la tarjeta vería un listado corto sin saber por qué.
- **La tarjeta de periodo de prueba deja fuera al contrato de obra o labor**, que es lo que dice 9.6.
  Las tres alertas todavía se lo mandan y eso es `REV-05`; esta pantalla no repite el error mientras
  tanto.
- **La cuarta tarjeta cuenta a quien ya acumuló un periodo completo y su etiqueta lo dice.**
  «Vacaciones en riesgo» son dos poblaciones en 11.4 y la segunda —quien está por cumplir un año sin
  disfrutar seis días continuos— tiene su propio correo mensual; llamarlas igual daría un número que
  no es el del reporte. Los quince días los calcula `SaldoDeVacaciones::diasDeUnPeriodoCompleto()`,
  que salió de dentro de `AcumulacionDeQuinceDias` para que no haya dos declaraciones del mismo quince.
- **El panel abre en milésimas y está medido.** Resolver el bloque entero de una vez tardaba **4,8
  segundos** con doscientos empleados, porque contar acumulación obliga a pedirle el saldo al motor
  uno por uno —se calcula, no se guarda—. La tarjeta de vacaciones pasó a `Inertia::defer` y la
  primera vuelta bajó a **0,007**. Las otras tres salidas se descartaron a propósito: leer la caché
  la convertiría en la fuente de verdad sobre una persona, y tocar el motor por velocidad es donde se
  rompen los números. La medición quedó como prueba con el umbral de dos segundos del documento.

### [x] REV-05 · Quitar las alertas de periodo de prueba de obra o labor

**Adelantada desde el final del backlog el 23 de agosto de 2026.** Es corta, y es la única pendiente
que corrige algo que el sistema hace mal hoy: le manda a los empleados de obra o labor tres correos
que por ley no les corresponden. Desde el final de la lista no se habría hecho hasta terminar todo.

F3-03 construyó las tres alertas de periodo de prueba del contrato indefinido —al mes, a los 45 días,
un día antes— y, apoyándose en que 9.6 decía «aplican también, cuando corresponda», las mandó también
a los contratos de obra o labor. Angela ya respondió la pregunta que quedó abierta desde entonces:
**a obra o labor no le va ninguna de las tres.** El documento 1, sección 9.6, ya se corrigió para
decirlo así de claro.

**Hay una segunda razón para hacerla ya, y llegó con F4-03:** la tarjeta de periodo de prueba del
panel de inicio **sí** deja fuera a la obra o labor, porque esa tarea decidió no repetir el error en
una pantalla nueva. Así que hoy el panel y el correo dicen cosas distintas sobre la misma persona.
El que está bien es el panel.

**Hecho cuando:** un empleado con contrato de obra o labor no genera ninguna de las tres alertas de
periodo de prueba en ningún punto de su periodo de prueba, con prueba que lo demuestre; un empleado a
término indefinido las sigue generando exactamente igual que antes; y el panel de inicio y el correo
dejan de discrepar.
**Fuente:** `docs/01-especificacion-funcional.md` 9.6

Hecho: `ReglaDePeriodoDePrueba::PRUEBA_GENERAL` —de donde las tres alertas del indefinido leen sus
modalidades— dejó de incluir `TipoContrato::ObraLabor`. El cambio fue de una sola línea porque las
tres reglas ya delegaban en esa constante y ninguna repetía la lista de modalidades por su cuenta: es
la razón de ser de la clase base. El contrato a término indefinido no se tocó y sigue generando las
tres exactamente igual —hay prueba de eso mismo, que ya existía—. La prueba
`'la obra o labor recibe las tres del periodo general, como dice la sección 9.6'` de
`AlertasDePeriodoDePruebaTest.php`, que probaba justamente lo que ahora está mal, pasó a comprobar lo
contrario: que ninguna de las tres se dispara. El panel de inicio no se tocó —ya dejaba fuera a la
obra o labor desde F4-03— y con este cambio deja de discrepar con el correo, que era la segunda razón
por la que esta tarea se adelantó.

### [x] F4-04 · Panel de inicio, indicadores

Los seis bloques restantes en el orden definido. Solo los indicadores aprobados.
**Hecho cuando:** el panel carga completo en menos de dos segundos.
**Fuente:** `docs/01-especificacion-funcional.md` 12.1

Hechos los seis bloques —Vacaciones, Planta, Rotación, Ausentismo, Composición y Movimientos—, cada
uno con la misma forma (`titulo`, `tarjetas`, `distribuciones` y, cuando aplica, `avisos`), así que
`Pages/Inicio.vue` los pinta con un solo bucle en vez de repetir marcado por bloque. Van en su propia
prop diferida de Inertia, `indicadores`, igual que la tarjeta de vacaciones acumuladas de F4-03.

- **`App\Inicio\Indicadores\`, seis clases y un orquestador.** `BloqueDeIndicadores` decide qué
  bloques calcular según los módulos del usuario —la misma regla que ya sigue `BloqueDeAccion` con sus
  tarjetas—, y arma el bloque de Ausentismo con la mitad de Incapacidades, la de Licencias, las dos o
  ninguna, porque sus indicadores vienen de dos permisos distintos.
- **Vacaciones lee de la caché de saldos (F4-02) y no del motor.** `CacheDeSaldos` ganó
  `conAcumulacionExcesiva()`, un conteo agregado —nunca el saldo de nadie en particular, que sigue sin
  tener método propio— para que «días acumulados» y «personas con acumulación excesiva» respondan en
  milisegundos igual que `total()`. «Días disfrutados en el periodo» sí es en vivo: se lee como el mes
  en curso hasta hoy —lo que enseña el prototipo, «Días disfrutados en agosto»— y es una decisión de
  implementación, no una regla legal.
- **Los índices de rotación y de ausentismo no están en ningún documento con una fórmula.** Se usó la
  fórmula estándar de cada indicador —salidas o días perdidos sobre el promedio o el disponible del
  periodo, por cien—, con el mismo criterio de implementación con que F3-05 fijó la cadencia de los
  meses impares: no es un valor legal ni una pregunta para Angela, es aritmética del propio indicador.
  El de ausentismo no mira más allá de hoy ni en el numerador ni en el denominador, para no inflar el
  índice de los primeros días del mes con una incapacidad certificada por adelantado.
- **«Principales motivos de salida» no recorta a un top.** No existe un criterio para decidir cuántos
  son «los principales» —la misma razón por la que D.2 descartó un umbral para la obra o labor
  prolongada—, así que se muestran todos los motivos con al menos una salida en el mes, de mayor a
  menor.
- **Composición queda con dos de sus cuatro indicadores bloqueados por D.4**, que el documento 6 marca
  expresamente como pendiente y bloqueante: los cortes de los rangos de edad y de antigüedad. Cargo,
  género y la antigüedad **promedio** —un solo número, no un reparto en rangos— sí se calculan enteros;
  el bloque trae un aviso que dice de qué depende lo que falta, el mismo patrón que F1B-03 usó con las
  pestañas del panel del proveedor.
- **Movimientos no repite el indicador descartado por 12.2.** El reparto entre aumentos y promociones
  es una nota que descompone la misma cifra, no una proporción nueva —esa es justo la que 12.2
  descarta—.
- **El «hecho cuando» quedó medido y no solo afirmado.** `tests/Feature/PanelDeIndicadoresTest.php`
  repite el método de F4-03: doscientos empleados, la caché ya refrescada —lo que hace el proceso
  diario cada noche—, y los seis bloques resueltos en menos de dos segundos. El resto de la batería
  cubre cada indicador, los permisos por módulo y que un bloque no cuente la planta de otra empresa.
- `resources/js/Components/DistribucionLista.vue` es el componente nuevo: una lista de conteos por
  categoría con su barra proporcional, compartida por las seis distribuciones del panel.

### [x] F4-05 · PUNTO DE CONTROL — revisión

Ejecuta el skill `revisar`.

Informe en `bitacora/revision-2026-08-23.md`. **Alcance: las once tareas desde el último punto de
control** —la fase 3 entera, que no tenía uno propio, más REV-05 y las cuatro de la fase 4—.
Aislamiento, seguridad y datos sin hallazgos: las doce tablas de negocio con trait, RLS y caso propio;
el job de correo restablece el contexto de empresa y lo limpia aunque falle; el proceso diario recorre
empresa por empresa; ningún conteo laboral fuera de `app/Dominio/Calculo/`. Se arreglaron dos cosas:
**el indicador de días disfrutados contaba los días pactados de una vacación en curso y no los
vividos**, al contrario de lo que decía su propio comentario y de lo que hace el bloque de ausentismo
con una incapacidad abierta; y **la guardia de aislamiento del panel de indicadores solo vigilaba uno
de los seis bloques**. Quedó `REV-06` por la regla de «la fecha entre paréntesis», que es la tercera
revisión que la levanta y necesita una decisión de David.

---

## Fase 5 — Carga inicial, demostración y panel completo

### [x] F5-01 · Lector de plantillas

Lee las seis plantillas de `plantillas/`. Modo simulación que valida sin escribir y devuelve errores
por número de fila con el motivo.
**Hecho cuando:** una plantilla con tres errores deliberados los señala por fila y no escribe nada.
**Fuente:** `docs/02-arquitectura-y-datos.md` cap. 6

Hecho: `app/CargaInicial/` con `LectorDeArchivo`, `SimulacionDeCarga`, `ResultadoDeSimulacion`,
`PlantaDeLaEmpresa`, `CatalogoDePlantillas` y las **seis** clases de `Plantillas/`, más el comando
`carga:simular {empresa} {plantilla} {archivo}`. El «hecho cuando» está probado literalmente: un
archivo con cuatro filas y tres errores deliberados —una fecha ilegible, una EPS que no está en el
catálogo y el saldo vacío— devuelve tres errores en las filas 3, 4 y 5, y `personas` y `vinculaciones`
siguen en cero.

- **Las reglas de cada plantilla son las del formulario de su módulo, sin copiar ninguna.** La fila
  traducida se le pasa a `RegistrarEmpleado`, `RegistrarIncapacidad`, `RegistrarVacacion`,
  `RegistrarLicencia` o `RegistrarMovimiento`, y de ahí salen `rules()`, `messages()` y
  `attributes()`. Si la plantilla tuviera reglas propias, tarde o temprano admitiría por archivo lo
  que la pantalla rechaza.
- **Lo que la plantilla añade son condiciones del archivo, no de la pantalla**, y cada una con su
  motivo escrito: el saldo de vacaciones obligatorio (documento 2, 6.2: la única ausencia
  bloqueante), la fecha de ingreso no futura —aquí van «únicamente las personas vinculadas hoy»—,
  el documento sin repetir dentro del archivo, y la comprobación que la plantilla 6 promete en su
  hoja de instrucciones: el último movimiento de cada persona tiene que cuadrar con el salario y el
  cargo de la planta.
- **El título es la llave y el número de fila es el del Excel.** Las columnas se buscan por nombre y
  no por posición; la que falte se señala en la fila 1 y el resto del archivo se lee igual.
- **Un error de digitación no puede acabar en un error de base de datos.** Cuando un municipio o un
  departamento no se resuelven, los dos campos se vacían antes de validar: dejar ahí el nombre haría
  que la comprobación del catálogo buscara un número donde hay letras.
- `TipoDeCelda` estrenó `Dinero`, que sale como texto de dos decimales: la convención del proyecto es
  que el dinero nunca viaje en coma flotante.
- **Tres desajustes entre las plantillas y el sistema quedaron anotados en `ESTADO.md`**, no
  resueltos por cuenta propia: «Sura» y «Sura EPS» en la fila de ejemplo, el código de diagnóstico
  que la plantilla marca opcional y la columna exige, y el rango «Menos de 30 min» que el sistema
  parte en dos.

### [x] F5-02 · Ejecución de la carga

Dentro de una transacción: o entra todo o no entra nada. Control de dependencias entre plantillas.
Registro de la carga.
**Hecho cuando:** tras cargar, el saldo de cada persona coincide con el de la plantilla más lo
acumulado desde la fecha de corte.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 13

Hecho: `EjecucionDeCarga`, `ResultadoDeLaCarga`, `CargaRechazada`, `HistorialDeCargas`, la tabla
`cargas_iniciales` con su modelo `RegistroDeCarga`, el comando `carga:ejecutar` y **la pantalla de
puesta en marcha del panel** —`CargaInicialController`, `App\Proveedor\PuestaEnMarcha`, el
`FormRequest` `CargarPlantilla` y `Pages/Proveedor/PuestaEnMarcha.vue`—, que era la que F5-01 dejó
sin construir a propósito porque su botón de confirmar es esta tarea. El «hecho cuando» está probado
con el corte a fin de mes: quien informa 17,25 días el 31 de agosto tiene 17,25 ese día y 21 el 30 de
noviembre, tres meses cerrados después.

- **La ejecución no se fía del informe que se enseñó: vuelve a simular el archivo entero.** Entre
  validar y confirmar pasan minutos y en esos minutos alguien pudo desactivar una EPS o subir otro
  archivo. De paso es lo que permite no guardar el Excel en ninguna parte (documento 1, 13.1): llega,
  se lee dos veces y se va.
- **Cada plantilla escribe por donde escribe su módulo**, igual que valida con las reglas de su
  formulario: `RegistroDeEmpleado`, las relaciones de la vinculación y `BloqueSociodemografico`. La
  excepción es el histórico de movimientos, que no pasa por `registrarMovimiento()` porque ese método
  actualiza además el cargo y el salario vigentes, y aquí los vigentes ya son correctos.
- **La transacción está probada por donde de verdad se rompería**: no con un archivo malo —eso lo
  para la simulación antes de escribir— sino con una escritura que falla en la segunda de tres filas.
  No queda ni una persona ni el registro de la carga.
- **La dependencia se comprueba contra `cargas_iniciales` y no contra la planta.** Una empresa puede
  tener empleados registrados por pantalla sin haber pasado nunca por la carga, y contra la planta la
  comprobación diría que sí a algo que nunca ocurrió.
- **Volver a pasar la plantilla de empleados se rechaza fila por fila**: un documento ya vinculado y
  activo es un error del archivo. Sin eso, la segunda pasada abriría una segunda vinculación a cada
  persona y nadie lo notaría hasta ver la planta repetida en el listado.
- **El corte del saldo acota también el disfrute.** `SaldoDeVacaciones` ya acotaba la acumulación
  desde `fecha_corte_saldo`; ahora hace lo mismo con los días disfrutados, porque los «días
  disponibles hoy» que informa la empresa ya vienen con el pasado descontado. Sin eso, cargar el
  histórico de vacaciones —que es opcional— le habría cambiado el saldo a todo el mundo.
- **Las dos columnas del contrato vigente siguen sin convertirse en tramos del historial, y es una
  decisión.** Con un número de prórrogas y una sola fecha habría que inventarse las fechas de los
  tramos intermedios, y 13.1 lo prohíbe con esas palabras: «no se inventan registros». La
  consecuencia está anotada en `ESTADO.md`.

### [x] F5-03 · Ambiente de demostración

Empresa ficticia poblada y proceso que la restaura cada noche. Accesos de prueba con vigencia limitada.
**Hecho cuando:** el ambiente se restaura solo y siempre luce igual de poblado.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 10

Hecho: `AmbienteDeDemostracion` —una sola empresa marcada `es_ambiente_demo`, con índice único parcial
que impide una segunda—, su planta fija de ocho personas con fechas relativas a hoy (periodo de
prueba, contrato fijo por vencer, vacaciones, incapacidad, licencia, movimiento, obra o labor y una
salida ya registrada), `restaurar()` —vacía con borrado lógico y vuelve a poblar dentro de una
transacción— y el comando `demostracion:restaurar`, programado cada noche a las 3:00 a. m. La pantalla
«Demostración» del panel —`DemostracionController`, `Pages/Proveedor/Demostracion.vue`— muestra el
estado, el botón «Restaurar ahora» y los accesos de prueba. **`AccesosDePrueba` no trae ningún valor
por omisión para la vigencia**: D.12 sigue sin decidir cuántos días dura un acceso, y el documento 4,
12.1, dice que es David quien lo decide cada vez que lo genera, así que el formulario exige el número
y no propone ninguno. El enlace `/prueba/{token}` loguea con la cuenta compartida de demostración
—`Rol::Superadministrador` dentro del tenant demo— y `CerrarAccesoDePruebaExpirado` cierra la sesión
si el acceso vence a la mitad de una visita, no solo al entrar. `CatalogosDelSistema` actualizó su fila
de este umbral: ya no dice «sin decisión escrita» sino dónde vive el mecanismo y por qué sigue sin un
número por defecto.

### [x] F5-04 · Suscripciones y cobros

Tablas `planes`, `suscripciones` y `pagos`. Estado, mora y suspensión con constancia.
**Hecho cuando:** suspender bloquea el acceso sin borrar nada y reactivar lo restituye.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 6

Hecho: las tres tablas —`planes` global sin `tenant_id`, `suscripciones` con índice único parcial de
una por empresa y `pagos` colgada de la suscripción—, los cinco enumerados (`EstadoDeSuscripcion`,
`EstadoDeCobro`, `EstadoDeFactura`, `MedioDePago` y la clase `EscaleraDeMora`), `App\Proveedor\Suscripciones`
con las seis escrituras, `PanelDeSuscripciones`, `SuscripcionController`, la pantalla
`Proveedor/Suscripciones` con la tabla y las tres tarjetas del prototipo, y la pestaña «Suscripción»
de la ficha de empresa con el historial de facturas y sus cuatro acciones.

**El «hecho cuando» está probado de punta a punta.** `BloquearEmpresaSuspendida`, middleware nuevo del
grupo `web`, manda a `/suspendido` a cualquier usuario de una empresa suspendida —salvo cerrar sesión y
esa misma pantalla—, y reactivar devuelve el acceso **en la petición siguiente**, porque el estado se
lee de la empresa en cada petición y no se guarda en la sesión. La prueba comprueba además que no se
borró nada: el empleado cargado sigue vivo. La pantalla `ServicioSuspendido` dice las tres cosas que
el documento 6, A.4 obliga a decir, incluida que la información se entrega si el cliente la pide.

- **La escalera de mora avisa, no suspende.** Los números —5, 15 y 30 días— son la propuesta del
  capítulo 6, que dice «los números son una decisión de ustedes», y viven en `config/panel.php` con el
  precedente de la salud de uso. Llegar al último peldaño pone el aviso en la pantalla; el corte lo
  ejecuta una persona con su motivo escrito, porque el documento pide «motivo y constancia» y 17.3
  sigue listando la escalera entre lo pendiente. Hay prueba de que mirar la pantalla no suspende a nadie.
- **`planes` nace vacía y es una decisión.** A.1 sigue aplazada, así que no hay seeder de precios: los
  planes se dan de alta desde el panel y la tarjeta dice de qué decisión depende la tabla mientras esté
  vacía. Al abrir una suscripción o cambiar de plan, el nombre y los dos cupos se copian a las columnas
  `plan_*` de `tenants` en la misma transacción: son las que lee el listado de empresas desde F1B-02 y
  dos sitios con planes distintos era el problema a evitar.
- **La columna «Pago» del listado dejó de ser un hueco** y estrenó su filtro, el cuarto que pide 4.1.
  Una empresa sin suscripción lo dice con palabras. La constancia de cada suspensión la escribe sola la
  auditoría **de esa empresa**, y el motivo se guarda además en la suscripción para leerlo en la ficha.
- **Un pago adelanta el cobro un mes desde la fecha que tenía**, no desde hoy: pagar tarde no corre el
  calendario del cliente. Anularlo lo deshace exactamente y deja la fila en el historial, como eliminar
  unas vacaciones con el saldo. No existe el estado «pendiente»: lo que dice que hay plata por cobrar es
  la próxima fecha de cobro ya vencida, que es el mismo indicador de mora.

### [x] F5-05 · Consola de alertas del proveedor

Listado de todo lo enviado con filtros, estado y reenvío puntual. Vista de entregabilidad.
**Hecho cuando:** se responde en menos de un minuto a «¿a quién y cuándo se envió esta alerta?».
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 7

Hecho: `App\Proveedor\ConsolaDeAlertas`, el `ConsolaDeAlertasController` y la pantalla
`Proveedor/Alertas`. **`alertas_enviadas` tiene el trait `PerteneceATenant` y su política de RLS, y
la política no tiene excepción para el `owner`**: una sola consulta que cruce empresas no es posible,
así que esta clase recorre las empresas una por una —como ya hace `ProcesoDiarioDeAlertas`—, filtra
dentro del contexto de cada una y combina, ordena y pagina el resultado en memoria. Las ocho columnas
del capítulo 7, los cuatro filtros y el reenvío puntual —que reencola con `EnviarAlerta` una alerta
enviada o fallida, nunca una pendiente— quedan probados cruzando empresas de verdad. **La vista de
entregabilidad cuenta entregadas contra fallidas, no contra rebotadas**: «rebotada» es un estado que
hoy nadie escribe porque no hay proveedor de correo transaccional conectado (F3-06b), y la pantalla lo
dice con esas palabras en vez de fingir una cifra que no se mide. `SeccionDelPanel::pendientes()`
perdió la sección «Alertas».

### [x] F5-06 · Salud del sistema y métricas

Estado del proceso diario, cola, errores, copias de seguridad y las métricas de negocio del panel.
**Hecho cuando:** el inicio del panel muestra las tarjetas de atención y las cifras del negocio.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 3 y 9

Hecho: **`Proveedor/Inicio`** reescrita con las seis tarjetas de atención (3.1) y los siete
indicadores de negocio (3.2) —`App\Proveedor\PanelDeInicio`—, y **`Proveedor/Salud`** nueva, con el
proceso diario, la cola y los errores de aplicación —`App\Proveedor\SaludDelSistema`—, que le quita a
`Salud` la última salida de `SeccionDelPanel::pendientes()`: solo queda `Equipo`. Dos piezas de datos
eran nuevas de verdad y las anticipaba el propio comentario de `ResumenDelProceso` desde F3-01:
- **`ejecuciones_de_alertas`**, el historial de corridas del proceso diario. `EnviarAlertasDiarias`
  mide la duración con `hrtime()` —lo único que `ResumenDelProceso` no podía saber de sí mismo— y
  `RegistroDeEjecuciones` la guarda junto a los conteos que ya traía. De aquí salen «procesos fallidos»
  del inicio y el historial de la pantalla de salud.
- **`errores_aplicacion`**, con `App\Proveedor\RegistroDeErrores` enganchado al `reportable()` de
  `bootstrap/app.php`, que hasta hoy estaba vacío. Laravel ya descarta antes de llegar ahí lo que nunca
  fue un error de negocio —validación, 404, autorización—, así que la tabla no se llena de ruido.

**La cola no necesitaba tabla nueva**: `jobs` y `failed_jobs` son el andamiaje estándar de Laravel
desde el primer día y nadie los había leído. `App\Proveedor\ColaDeTrabajos` los consulta y el
reintento delega en el propio `queue:retry` del framework.

**Las copias de seguridad no se inventan.** La decisión A.5 del documento 6 aplaza esa infraestructura
a propósito «hasta que exista producto»: sin ella no hay ninguna fecha real que enseñar, y la pantalla
lo dice con esas palabras en vez de fingir un «03:15, automática, de hoy» como el prototipo. Es la
misma disciplina de F5-05 con la tasa de rebote.

**«Con la variación frente al mes anterior» necesitaba una foto que no existía.** El estado de una
empresa cambia sin dejar historia propia y el ingreso recurrente depende del plan que tenía cada
suscripción ese día, no el de hoy. `metricas_mensuales` —una fila por mes, capturada el día 1 por
`metricas:capturar-mes`— es la pieza nueva; el primer mes de vida del sistema no tiene con qué
comparar, y la tarjeta lo dice en vez de mostrar una variación de mentira.

Tres números del documento no estaban en ninguna tabla y se resolvieron así, cada uno con su rastro en
el código:
- **«Clientes en riesgo» no tiene un umbral escrito en el documento** —solo «cayó de forma marcada»—,
  así que la ventana de comparación (30 días contra los 30 anteriores) y el porcentaje de caída (50%)
  quedaron en `config/panel.php`, con el mismo criterio que `salud_de_uso`: propuesta, no ley.
- **«Cliente sin arrancar» sí trae sus dos números en el propio texto** —una semana, cinco
  empleados—, así que esos van como constantes en `PanelDeInicio`, no en la configuración.
- **«Bajas de clientes» y «cancelación acumulada» se leen del borrado lógico de `tenants`.** Es la
  única forma de dar de baja una empresa que la regla 4 permite, aunque hoy ninguna pantalla ofrezca
  esa acción —así que el número es honesto en cero, no inventado—.

Pruebas nuevas en `InicioDelProveedorTest.php` (las seis tarjetas, los siete indicadores, el
aislamiento del ambiente de demostración) y `SaludDelSistemaTest.php` (el historial no duplica por
corrida, el registro de errores, la cola y su reintento, que las copias no inventan fecha y que el
espacio de la base de datos sale de Postgres). `TarjetaDeAccion` —del bloque de acción del cliente,
documento 3, 5.2— se reutilizó para las seis tarjetas de atención en vez de duplicarla: ganó el tono
`exito` y un `valor` que acepta texto además de número, con sus dos pruebas nuevas en
`TarjetaDeAccion.test.js`.

### [ ] F5-07 · Bitácora y acceso al entorno del cliente

Tabla `notas_cliente`. Acceso del owner al entorno de una empresa, de solo lectura, registrado en la
auditoría **de esa empresa** y visible para ella.
**Hecho cuando:** entrar como cliente deja constancia que el propio cliente puede ver.
**Fuente:** `docs/04-panel-del-proveedor.md` 4.4

---

## Fase 6 — Pulido

### [ ] F6-01 · Estados vacíos

Los siete listados con su texto propio y su botón de primera acción.
**Hecho cuando:** ninguna pantalla dice «No hay datos».
**Fuente:** `docs/03-diseno-e-interfaz.md` 6.3

### [ ] F6-02 · Textos del sistema

Todos los mensajes de error y confirmación revisados contra los textos definidos.
**Hecho cuando:** ninguna pantalla muestra un mensaje escrito para programadores.
**Fuente:** `docs/03-diseno-e-interfaz.md` 6.3

### [ ] F6-03 · Confirmaciones con consecuencia

Toda acción destructiva explica el efecto calculado, no pregunta «¿está seguro?».
**Hecho cuando:** eliminar unas vacaciones dice cuántos días se devuelven y cómo queda el saldo.

### [ ] F6-04 · Indicadores de carga

En toda acción que tarde más de un instante.

### [ ] F6-05 · Pantallas pequeñas

Menú colapsable, tablas en tarjeta, modales a pantalla completa.
**Hecho cuando:** el sistema es usable a 375px de ancho.
**Fuente:** `docs/03-diseno-e-interfaz.md` 6.4

### [ ] F6-06 · Accesibilidad

Contraste, recorrido con teclado, foco visible, etiquetas reales, zoom al 200%.
**Hecho cuando:** se completa un registro de novedad usando solo el teclado.

### [ ] F6-07 · Operación

Monitoreo de errores en producción. Copias de seguridad automáticas y **una restauración probada de
principio a fin**.
**Hecho cuando:** existe una copia que se restauró y funcionó.

### [ ] F6-08 · REVISIÓN FINAL

Ejecuta el skill `revisar` completo y escribe en `ESTADO.md` el listado de lo que falta para poder
vender, tomándolo de `docs/06-decisiones-pendientes.md`.

---

## Tareas surgidas de revisiones

### [x] REV-01 · Un solo armazón de listado en el servidor

`ListadoDeEmpleados` y `ListadoDeContratos` tienen la misma estructura escrita dos veces:
`opcion()`, `porPagina()`, `dePersona()`, `ordenar()`, `pagina()`, `total()`, `tieneFiltros()`,
`paraLaInterfaz()`, la constante `TODOS`, `TAMANOS_DE_PAGINA` y la lista blanca de ordenamientos.
Extrae lo común —clase base abstracta o _trait_, con la lista blanca y el mapa de columnas como
declaración de cada listado—, dejando en cada clase solo sus filtros y sus filas.

**Motivo:** hoy son dos, pero el documento 3 cuenta **siete** pantallas de listado. Con el patrón
copiado, el día que la paginación o el orden cambien hay que acordarse de siete sitios.

**Hecho cuando:** los dos listados comparten el armazón, las pruebas de empleados y de contratos
siguen en verde sin tocarlas, y agregar un listado nuevo no vuelve a copiar `opcion()`.

**Cuándo:** antes del primer listado de la fase 2 (F2-04). Hacerlo con dos ejemplos es barato;
con cinco, ya no.

Hecho: `App\Listados\Listado`, clase base abstracta y genérica sobre el modelo, con el armazón
entero —`consulta()`, `pagina()`, `total()`, `tieneFiltros()`, `paraLaInterfaz()`, `ordenar()`, y los
lectores `filtro()`, `opcion()`, `texto()`, `fecha()`, `porPagina()`, `valoresDe()`, `textoDe()`— más
`TAMANOS_DE_PAGINA`, `POR_PAGINA_POR_DEFECTO` y `TODOS`. Cada listado declara solo lo suyo:
`base()`, `filtrar()`, `columnasDeOrden()`, `ordenPorDefecto()`, `filtrosParaLaInterfaz()` y
`aplicados()`. **La lista blanca y el mapa de columnas son una sola declaración**: el orden se valida
contra las claves de `columnasDeOrden()`, así que no pueden separarse. Se migraron **los tres**
listados que existen —empleados, contratos y empresas del panel—; el ordenamiento por persona, que
empleados y contratos escribían igual, quedó en el trait `OrdenaPorEmpleado`. Las pruebas de
empleados, contratos y del panel de empresas siguen en verde **sin tocarlas**, y `ListadosTest`
prueba el armazón con un listado de mentira que declara dos filtros y nada más.

### [x] REV-02 · Un solo armazón de listado en la interfaz

`Pages/Empleados/Index.vue` y `Pages/Contratos/Index.vue` repiten `pedir()`, `ordenar()`,
`quitar()`, `limpiar()`, `urlExportar` y `textoExportar` casi carácter por carácter. Extrae un
_composable_ —`resources/js/Utilidades/useListado.js`— que reciba la ruta base y las props de
filtros y devuelva las seis cosas.

**Motivo:** el mismo de REV-01, y con un agravante: `textoExportar` arma la frase «Exportar 43
registros filtrados» que la regla de interfaz exige, y dos copias de una frase obligatoria se
separan solas.

**Hecho cuando:** las dos pantallas usan el _composable_, sus pruebas de Vitest siguen en verde y
el _composable_ tiene las suyas.

Hecho: `resources/js/Utilidades/useListado.js`, el gemelo en la interfaz de `App\Listados\Listado`.
Devuelve las nueve cosas que las pantallas repetían —`busqueda`, `buscar()` con su espera,
`pedir()`, `ordenar()`, `quitar()`, `limpiar()`, `abrir()`, `urlExportar` y `textoExportar`— y cada
pantalla declara solo lo suyo: `ruta`, `filtros`, `pagina`, `tieneFiltros` y `deLista`. Se migraron
**las tres** pantallas que existen —empleados, contratos y empresas del panel—, igual que en el
servidor. Las tres perdieron 179 líneas y ganaron 46. **`textoExportar` está escrito una sola vez**,
que era el agravante de esta tarea. Diecinueve casos nuevos en
`resources/js/Utilidades/useListado.test.js`, tres de ellos leyendo los `.vue` para comprobar que
ninguna pantalla conserva su copia; ninguna prueba existente se tocó.

### [ ] REV-03 · Editar y eliminar movimientos y terminaciones

F2-10 dejó editables y eliminables las **tres** novedades del capítulo 6. Movimientos (8.1) y
terminaciones (capítulo 7) comparten el flujo de registro pero no son novedades del capítulo 6, y
borrarlos no es quitar una fila: ambos cambiaron al empleado cuando se registraron.

**Decisión de David: eliminar es volver al pasado.** Borrar uno de estos dos registros deja al
empleado exactamente como estaba **antes de crearlo**, sin rastro de que existió:

- **Eliminar un movimiento** devuelve al empleado el salario y el cargo que tenía justo antes de ese
  movimiento (los que el propio registro guardó como «anteriores» al aplicarse).
- **Eliminar una terminación** devuelve al empleado a activo, con la fecha de fin borrada, y con él
  vuelven todas sus alertas —igual que si el despido nunca se hubiera registrado.

**Solo se puede deshacer el último de la lista.** Si después de un movimiento o una terminación hubo
otro registro encima, ese de encima hay que eliminarlo primero. No se permite eliminar uno de en medio
del historial, porque ya no habría un «antes» claro al que volver.

**Hecho cuando:** eliminar el movimiento o la terminación más reciente de un empleado lo deja
indistinguible de como estaba antes de crearlo —mismo salario, mismo cargo, mismo estado, mismas
alertas—, y hay prueba. Intentar eliminar uno que no es el más reciente lo rechaza con un mensaje
claro.
**Fuente:** esta decisión (no hay documento previo; queda registrada aquí como la fuente).

**Hecho cuando:** las dos preguntas están respondidas, los dos módulos tienen sus acciones con la
consecuencia escrita antes de ejecutarla, y `TipoDeNovedad::tituloDeEdicion()` deja de devolver nulo
para ellos.

### [ ] REV-04 · Pantalla de auditoría

El módulo `Auditoria` está en el menú, la tabla y el observador existen desde F0-08, y **ninguna tarea
del backlog le da pantalla**: es el último `SeccionPendiente` del entorno del cliente y no hay nada en
las fases 3 a 6 que lo cambie.

Listado de lo registrado con filtros por módulo, usuario, acción y rango de fechas, y el detalle con
el valor anterior y el nuevo. De solo lectura: la tabla no es editable ni borrable desde la
aplicación, y eso no cambia por tener pantalla.

**Motivo:** F2-10 dejó las novedades editables y eliminables sin límite de antigüedad, así que hoy el
único sitio donde consta quién corrigió o borró un registro es una tabla que nadie puede mirar. Es la
contrapartida de esa libertad. Además F5-07 da por hecha esta pantalla —el acceso del owner queda
«registrado en la auditoría de esa empresa **y visible para ella**»— sin que exista.

**Cuidado:** `codigo_diagnostico` está en la lista `OCULTAS` del observador justamente porque la
auditoría es otro módulo con otro permiso. Al darle pantalla hay que comprobar que sigue sin salir.

**Hecho cuando:** editar y eliminar una vacación se ven en la pantalla con su autor, su fecha y el
valor anterior; el módulo sale de las cuatro listas de exclusión compartidas —`routes/web.php`,
`EstructuraTest.php`, `componenteDe()` en `FlujoDeNovedadTest.php` y `PANTALLAS` en
`useListado.test.js`—; y hay prueba de que el código de diagnóstico no aparece.

**Fuente:** `docs/02-arquitectura-y-datos.md` 7.2 · `docs/03-diseno-e-interfaz.md` cap. 4

> `REV-05` estaba aquí y **se movió delante de F4-04** el 23 de agosto de 2026. El motivo: es la única
> tarea pendiente que corrige algo que el sistema está haciendo mal **ahora mismo** —mandar tres
> correos que por ley no corresponden—, y desde el final de la lista no se habría hecho hasta terminar
> el proyecto entero.

### [ ] REV-06 · Acotar la regla de «la fecha entre paréntesis»

`.claude/rules/30-interfaz.md` la enuncia para **toda** la interfaz: «Toda referencia temporal lleva
la fecha entre paréntesis: "Mañana (14 de junio)"». Su fuente —documento 1, 9.1— la enuncia solo para
**los mensajes de las alertas**, y ahí el código la cumple al pie de la letra desde F3-02, con
`ReferenciaTemporal` y sus pruebas.

En pantalla, en cambio, el código nunca la ha cumplido y **la fase 4 amplió la distancia**: el panel
de inicio escribe «Contratos vencen en 30 días», «Periodos de prueba terminan esta semana», «Días
disfrutados en agosto» e «Índice de rotación de agosto», ninguna con fecha absoluta al lado. No es un
descuido de F4-03 ni de F4-04: es lo que dibuja el prototipo, y una tarjeta que dijera «Contratos
vencen en 30 días (22 de septiembre)» estaría diciendo la fecha de un corte, no la de ningún contrato.

**Es una decisión de David sobre el texto de la regla, no trabajo de producto.** La recomendación,
repetida ya en las revisiones de F1-13 y F2-11 y sin resolver desde entonces:

- **En los correos, la regla se queda literal.** Ahí no hay ninguna columna al lado que salve la
  frase, y es donde la fuente la enuncia.
- **En pantalla, la versión débil**, que es la que el código ya cumple y el prototipo dibuja: una
  referencia relativa necesita su fecha absoluta visible, en el mismo sitio o en la columna de al lado.

**Hecho cuando:** `.claude/rules/30-interfaz.md` distingue los dos casos, y queda escrito en la propia
regla que en pantalla basta con la fecha visible al lado. Si David decide lo contrario —que la regla
vale literal también en pantalla—, entonces la tarea es al revés: repasar el panel de inicio y las ocho
pantallas de listado poniendo la fecha entre paréntesis, y esta tarea deja de ser de una línea.

**Motivo:** una regla que el código lleva tres revisiones incumpliendo deja de leerse como una regla.
O se acota a lo que la sostiene el documento, o se cumple.
**Fuente:** `.claude/rules/30-interfaz.md` · `docs/01-especificacion-funcional.md` 9.1 ·
`bitacora/revision-2026-08-20.md` y `bitacora/revision-2026-08-22.md`
