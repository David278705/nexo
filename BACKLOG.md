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

### [ ] F1-13 · PUNTO DE CONTROL — revisión

Ejecuta el skill `revisar`.
**Hecho cuando:** existe el informe en `bitacora/` y lo encontrado está arreglado o convertido en tareas `REV-`.

---

## Fase 1B — Panel del proveedor, mínimo

### [ ] F1B-01 · Base del panel

Aplicación separada bajo su propia ruta, solo para el rol `owner`, con segundo factor obligatorio.
Ningún usuario de empresa puede alcanzarla.
**Hecho cuando:** un superadmin que intenta entrar recibe 403 y hay prueba.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 2

### [ ] F1B-02 · Crear empresa

Formulario de tres pasos: datos, parámetros, plan y cuenta del superadministrador. Resumen final de
configuración copiable. Invitación por correo.
**Hecho cuando:** se crea una empresa completa desde la interfaz en menos de cinco minutos, sin tocar
la base de datos.
**Fuente:** `docs/04-panel-del-proveedor.md` 4.2 · `prototipos/panel-proveedor.html`

### [ ] F1B-03 · Listado y ficha de empresas

Listado con indicadores de salud de uso y de pago. Ficha con resumen, parámetros editables por el
owner y gestión de usuarios de esa empresa.
**Hecho cuando:** se ve el listado con datos reales y se edita un parámetro desde la ficha.
**Fuente:** `docs/04-panel-del-proveedor.md` 4.1 y 4.3

### [ ] F1B-04 · Catálogos y constantes desde el panel

Administración de EPS, fondos, ARL, cajas, bancos y ciudades. Calendario de festivos por año con
aviso si falta el siguiente. Pantalla de constantes legales con su norma.
**Hecho cuando:** agregar una EPS no requiere desplegar código.
**Fuente:** `docs/04-panel-del-proveedor.md` cap. 8

---

## Fase 2 — Motor de cálculo y novedades

### [ ] F2-01 · Pruebas del motor, primero

Escribe los diez casos obligatorios. Todos fallan, que es lo esperado.
**Hecho cuando:** los diez existen y fallan por falta de implementación, no por error de sintaxis.
**Fuente:** `docs/02-arquitectura-y-datos.md` 4.2

### [ ] F2-02 · Motor de cálculo

Las tres funciones de conteo con sus nombres explícitos y el cálculo de saldo como función, no como
columna.
**Hecho cuando:** los diez casos pasan.
**Fuente:** `.claude/rules/20-calculo-y-reglas-legales.md`

### [ ] F2-03 · Flujo compartido de novedades

Modal de documento con validación al salir del campo, y modal grande con ficha del empleado. Un solo
componente reutilizado por los cinco módulos.
**Hecho cuando:** el componente existe y una novedad de prueba lo usa.
**Fuente:** `docs/03-diseno-e-interfaz.md` 5.5

### [ ] F2-04 · Incapacidades

Módulo completo. Días corridos calculados en pantalla. Campo de diagnóstico con acceso restringido.
**Hecho cuando:** del 14 al 16 de julio muestra 3 días y el diagnóstico no lo ve quien no tiene permiso.
**Fuente:** `docs/01-especificacion-funcional.md` 6.3 · skill `crear-modulo`

### [ ] F2-05 · Vacaciones

Módulo completo con acumulación automática, cálculo en días hábiles y descuento del saldo.
**Hecho cuando:** registrar y eliminar unas vacaciones deja el saldo exactamente como estaba.
**Fuente:** `docs/01-especificacion-funcional.md` 6.4

### [ ] F2-06 · Advertencia de saldo insuficiente

Compara contra el saldo proyectado a la fecha de disfrute, no al de hoy. El mensaje dice cuántos días
faltan. No bloquea.
**Hecho cuando:** el caso de los 14 días para el 28 de agosto muestra «faltan 1,25 días».
**Fuente:** `docs/01-especificacion-funcional.md` 6.4

### [ ] F2-07 · Licencias, ausencias y suspensiones

Módulo con el selector de tres tipos. Días corridos, domingos y festivos incluidos. Efecto
proporcional sobre la acumulación de vacaciones.
**Hecho cuando:** la licencia del 16 de marzo al 15 de abril reduce a la mitad la acumulación de
ambos meses.
**Fuente:** `docs/01-especificacion-funcional.md` 6.5

### [ ] F2-08 · Aumentos y promociones

Módulo con salario y cargo actuales de solo lectura, y el histórico de la persona.
**Hecho cuando:** guardar un movimiento actualiza el registro principal y aparece en el histórico.
**Fuente:** `docs/01-especificacion-funcional.md` 8.1

### [ ] F2-09 · Terminaciones

Módulo con los siete tipos de salida y los motivos dependientes. Autocompletado en expiración de
plazo y obra terminada. Categoría de rotación deducida.
**Hecho cuando:** cambiar el tipo de salida recarga los motivos y el empleado pasa a inactivo.
**Fuente:** `docs/01-especificacion-funcional.md` cap. 7

Pendiente que dejó F1-05: al guardar la fecha de salida, **`Vinculacion::fechaDeSalida()` tiene que
devolverla a ella** y no `fecha_fin`. Es un método de tres líneas y es de donde sale el énfasis de
los 30 días de la advertencia de reingreso.

### [ ] F2-10 · Edición y borrado de novedades

Todas editables y eliminables sin límite de antigüedad, con confirmación que explica la consecuencia
calculada.
**Hecho cuando:** eliminar unas vacaciones muestra el saldo antes y después en la confirmación.
**Fuente:** `docs/01-especificacion-funcional.md` 6.2

### [ ] F2-11 · PUNTO DE CONTROL — revisión

Ejecuta el skill `revisar`.

---

## Fase 3 — Alertas

### [ ] F3-01 · Infraestructura de alertas

Tabla `alertas_enviadas` con clave de idempotencia e índice único. Proceso diario que recorre
empresas una por una. Cola con reintentos. Plantilla base de correo.
**Hecho cuando:** ejecutar el proceso dos veces el mismo día no duplica ningún correo.
**Fuente:** `docs/02-arquitectura-y-datos.md` cap. 5 · skill `agregar-alerta`

### [ ] F3-02 · Corrimiento al día hábil

Si la fecha cae en sábado, domingo o festivo, se envía el día hábil anterior y el texto se ajusta.
**Hecho cuando:** una alerta programada para domingo llega el viernes diciendo «en dos días (fecha)».
**Fuente:** `docs/01-especificacion-funcional.md` 9.1

### [ ] F3-03 · Alertas de periodo de prueba

Las tres de contrato indefinido y las dos de término fijo, con sus textos exactos y la fecha entre
paréntesis.
**Hecho cuando:** el caso del ingreso el 15 de abril produce las tres alertas en 14/05, 30/05 y 13/06.
**Fuente:** `docs/01-especificacion-funcional.md` 9.2 y 9.3

### [ ] F3-04 · Alertas de contrato a término fijo

Plazo de renovación a 32 días, efecto del silencio a 30, última renovación corta y cercanía al tope.
**Hecho cuando:** las cuatro se disparan en sus fechas y hay prueba de cada una.
**Fuente:** `docs/01-especificacion-funcional.md` 9.4

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

_(las tareas `REV-` se agregan aquí)_
