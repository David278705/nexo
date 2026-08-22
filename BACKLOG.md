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

### [!] F3-04 · Alertas de contrato a término fijo — dos de las cuatro dependen de decisiones abiertas

Plazo de renovación a 32 días, efecto del silencio a 30, última renovación corta y cercanía al tope.
**Hecho cuando:** las cuatro se disparan en sus fechas y hay prueba de cada una.
**Fuente:** `docs/01-especificacion-funcional.md` 9.4

Hechas **las dos del preaviso**: `PlazoDeRenovacion` y `EfectoDelSilencio`, sobre la base compartida
`ReglaDePreavisoDelFijo`, registradas en el catálogo y con sus textos literales de la sección 5.4.
Las dos cuelgan de la fecha límite del preaviso —`HistorialDeContrato::fechaLimiteDePreaviso()`—, así
que **el «32» no está escrito en ninguna parte**: es el preaviso configurado más los dos días que el
propio mensaje anuncia. La condición «si no se ha registrado prórroga ni terminación» se cumple por
construcción y tiene prueba.

**Bloqueadas las otras dos, y ninguna es un olvido:**

- **Cercanía al tope legal.** La decisión **D.3** del documento 6 está marcada BLOQUEANTE y sin
  responder: no hay número para «se aproxima a los cuatro años». No se inventa.
- **Última renovación corta posible.** La tabla dice «llega al límite de prórrogas cortas», en
  singular, y el sistema tiene **dos** límites distintos —cuatro pactadas y tres automáticas—.
  Disparar con el primero que se agote afirmaría en un correo que «la próxima renovación deberá ser
  de mínimo un año» cuando todavía podría ser corta del otro tipo. Falta además el día de disparo.

Las preguntas concretas están en `ESTADO.md`. Al resolverlas, esta tarea se retoma: solo faltan las
dos reglas, sus textos y sus pruebas; el armazón ya está.

### [ ] F3-05 · Resúmenes mensuales

Contratos vencidos sin gestionar, acumulación de 15 días, año cumplido sin disfrute mínimo y obra o
labor prolongada.
**Hecho cuando:** el resumen llega agrupado en un solo correo, no uno por empleado.
**Fuente:** `docs/01-especificacion-funcional.md` 9.5 y 9.6

### [ ] F3-06 · Entregabilidad y registro

Proveedor transaccional configurado. Registro de envíos consultable con destinatarios, estado e
intentos. Panel de alertas dentro del sistema como canal alterno.
**Hecho cuando:** un correo de prueba llega a bandeja de entrada y queda registrado.
**Fuente:** `docs/02-arquitectura-y-datos.md` 5.5

---

## Fase 4 — Reportería y panel de inicio

### [ ] F4-01 · Exportación y filtros por módulo

Botón en cada módulo que respeta filtros y lo dice. Todas las exportaciones de novedades traen
documento, nombre y cargo. Los filtros de cada módulo son los del documento 1.
**Hecho cuando:** aplicar tres filtros y exportar produce exactamente esos registros con esas columnas.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 11

### [ ] F4-02 · Caché de saldos

Tabla de caché refrescada por el proceso diario. Nunca fuente de verdad.
**Hecho cuando:** el dashboard suma el saldo de 200 empleados en menos de dos segundos.
**Fuente:** `docs/02-arquitectura-y-datos.md` 3.4

### [ ] F4-03 · Panel de inicio, bloque de acción

Las tarjetas de lo que exige atención, cada una enlazando al listado ya filtrado.
**Hecho cuando:** pulsar «contratos por vencer» lleva al listado filtrado.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 12 · `prototipos/sistema.html`

### [ ] F4-04 · Panel de inicio, indicadores

Los seis bloques restantes en el orden definido. Solo los indicadores aprobados.
**Hecho cuando:** el panel carga completo en menos de dos segundos.
**Fuente:** `docs/01-especificacion-funcional.md` 12.1

### [ ] F4-05 · PUNTO DE CONTROL — revisión

Ejecuta el skill `revisar`.

---

## Fase 5 — Carga inicial, demostración y panel completo

### [ ] F5-01 · Lector de plantillas

Lee las seis plantillas de `plantillas/`. Modo simulación que valida sin escribir y devuelve errores
por número de fila con el motivo.
**Hecho cuando:** una plantilla con tres errores deliberados los señala por fila y no escribe nada.
**Fuente:** `docs/02-arquitectura-y-datos.md` cap. 6

### [ ] F5-02 · Ejecución de la carga

Dentro de una transacción: o entra todo o no entra nada. Control de dependencias entre plantillas.
Registro de la carga.
**Hecho cuando:** tras cargar, el saldo de cada persona coincide con el de la plantilla más lo
acumulado desde la fecha de corte.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 13

### [ ] F5-03 · Ambiente de demostración

Empresa ficticia poblada y proceso que la restaura cada noche. Accesos de prueba con vigencia limitada.
**Hecho cuando:** el ambiente se restaura solo y siempre luce igual de poblado.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 10

### [ ] F5-04 · Suscripciones y cobros

Tablas `planes`, `suscripciones` y `pagos`. Estado, mora y suspensión con constancia.
**Hecho cuando:** suspender bloquea el acceso sin borrar nada y reactivar lo restituye.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 6

### [ ] F5-05 · Consola de alertas del proveedor

Listado de todo lo enviado con filtros, estado y reenvío puntual. Vista de entregabilidad.
**Hecho cuando:** se responde en menos de un minuto a «¿a quién y cuándo se envió esta alerta?».
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 7

### [ ] F5-06 · Salud del sistema y métricas

Estado del proceso diario, cola, errores, copias de seguridad y las métricas de negocio del panel.
**Hecho cuando:** el inicio del panel muestra las tarjetas de atención y las cifras del negocio.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 3 y 9

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
