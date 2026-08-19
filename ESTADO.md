# Estado del proyecto

**Última sesión:** 2026-08-18 · Tarea F1-03
**Verificación:** verde
**Siguiente tarea:** F1-04 · Registro de empleado

## Qué se hizo

- **`App\Contratacion\ListadoDeEmpleados`**, el único sitio donde se arma la consulta del listado:
  los seis filtros del documento 3 sección 5.3 —búsqueda por nombre o documento, estado, tipo de
  contrato, cargo y rango de fecha de ingreso—, el ordenamiento por las seis columnas y la
  paginación de 25 con opción de 50 y 100. **Todo resuelto en el servidor.**
- **`EmpleadoController`** con `index`, `exportar`, `ficha` y `nuevo`. No filtra ni cuenta nada: solo
  traduce la petición en props.
- **`App\Exports\EmpleadosExport`** con **Laravel Excel**, que se instaló en esta sesión
  (`maatwebsite/excel ^3.1`). Recibe el mismo `ListadoDeEmpleados` que armó la pantalla, así que
  trae exactamente lo filtrado. Columnas: documento, nombre completo, cargo, tipo de contrato, fecha
  de ingreso y estado.
- **`resources/js/Pages/Empleados/Index.vue`**: encabezado con la planta de la empresa, botones de
  Exportar y Nuevo, barra de filtros con etiquetas removibles, tabla y paginación. Cada cambio de
  filtro vuelve a pedir la página con los filtros en la dirección.
- **`Paginacion.vue`**, componente compartido nuevo, y **`TablaDatos` aprendió a ordenar**: encabezado
  pulsable, flecha y `aria-sort`. Ninguno de los dos ordena ni pagina por su cuenta; avisan y el
  servidor responde.
- **`EstadoVinculacion::paraElegir()` y `::valores()`**, como ya los tenía `TipoContrato`.
- **Rutas reales de `/empleados`**, con `modulo:empleados`. Empleados salió del bucle de secciones
  pendientes de `routes/web.php`.
- **`SubstituteBindings` se movió detrás de `ResolverTenant`** en `bootstrap/app.php`. Ver abajo.
- **`EmpleadosTest`** con 21 casos, **cinco casos de aislamiento por las rutas reales** en
  `AislamientoTest`, un caso nuevo en `EstructuraTest` y pruebas de Vitest para la pantalla, la
  paginación y el ordenamiento de la tabla.
- `composer verificar` en verde: 225 pruebas de Pest, PHPStan sin errores, build y 86 de Vitest.

## Decisiones tomadas

- **La resolución de rutas corre ahora dentro del contexto de empresa.** `SubstituteBindings` venía
  antes que `ResolverTenant` y resolvía `{empleado}` sin empresa fijada: **toda ficha respondía 404,
  incluida la propia**. Se sacó de su sitio en el grupo `web` y se volvió a poner detrás. Afecta a
  todas las fichas que se construyan de aquí en adelante, no solo a esta.
- **El listado muestra solo a los activos mientras no se diga otra cosa**, y ese filtro sale como
  etiqueta removible aunque sea el valor por defecto. Es lo que hace el prototipo; que no se viera
  daría la impresión de que faltan empleados.
- **Pantalla y exportación comparten la consulta**, no solo los filtros. Es lo único que garantiza
  que «Exportar 43 registros filtrados» diga la verdad el día que se agregue un filtro.
- **No hay columna de saldo de vacaciones**, aunque el prototipo la pinta. El saldo se calcula y el
  motor es F2-02. Se agrega entonces, no antes con un número inventado.
- **Un filtro que no encaja no es un error: no filtra.** `?contrato=prestacion_de_servicios` o
  `?ingresoDesde=ayer` devuelven el listado entero en vez de una pantalla de error. Las listas
  blancas también impiden que `?orden=salario` saque una columna que la pantalla no muestra.
- **El ordenamiento por documento y nombre va por subconsulta, no por `join`**, para no tener que
  calificar cada columna con su tabla en el resto de la consulta. Siempre desempata por `id`, o una
  página podría repetir o saltarse una fila.
- **`/empleados/nuevo` y `/empleados/{id}` existen ya como secciones pendientes.** El botón «Nuevo
  empleado» y la fila completa tienen que llevar a algún sitio, y la ficha además resuelve la
  vinculación de verdad, así que la de otra empresa responde 404 desde hoy. F1-04 y F1-07 solo
  cambian el cuerpo del método.

## Preguntas para Angela

- **La columna «Cesantías» del archivo de listas no tiene dónde ir.** Trae Porvenir, Protección,
  Colfondos, Skandia, el Fondo Nacional del Ahorro y «Otro», pero **el fondo de cesantías no aparece
  en la sección 5.1 de la especificación funcional**, así que no hay columna en `vinculaciones` ni
  tabla que lo reciba. La pregunta concreta: **¿se agrega «fondo de cesantías» como campo obligatorio
  del formulario de empleado?** Si la respuesta es sí, es una tarea nueva —tabla, columna, seeder y
  formulario— y **hay que resolverlo antes de F1-04, que es la próxima tarea y construye justamente
  ese formulario.**
- Menor, del mismo archivo: nivel educativo quedó con **«Técnico / Tecnólogo» unidos** y
  **«Posgraduado(a)» sin distinguir especialización, maestría y doctorado**. La propia hoja de notas
  dice que es fácil de separar después. Se construye así en F1-06 salvo aviso.
- **Las opciones de género siguen sin decidirse (D.5).** La fábrica de personas deja el campo vacío a
  propósito. F1-04 pide ese campo en el paso 1: **si no está resuelto, la tarea se bloquea ahí.**
- **D.1 sigue abierta: ¿se registra el tipo de prórroga?** No ha bloqueado nada, pero la respuesta
  cambia el formulario de «Renovar» de F1-09: o pregunta cuál de las dos es, o no pregunta.

## Decisiones para David

- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Lo advierte la propia hoja «Notas y fuentes» del archivo: hay nueve EPS intervenidas por la
  Supersalud —siguen operando, por eso se cargaron— y el sector está en movimiento. Las fuentes son
  Supersalud, Fasecolda, la Superintendencia del Subsidio Familiar y la Superintendencia Financiera.
  **Es una lista global: si está mal, está mal para todos los clientes a la vez.**
- **Verificar el calendario de festivos antes de vender.** Cincuenta y tres fechas, quince minutos,
  mismo riesgo global que las EPS.
- **Contrastar los 1.101 municipios contra el archivo del DANE.** Ese mismo cotejo trae los códigos
  DANE que hoy están nulos.
- **B.3, ¿bloquear o advertir?**, deja de ser teórica en F1-09. La sugerencia del documento 6 es
  bloquear el tope de cuatro años y advertir con fuerza en la prórroga corta.
- **Vigencia del enlace de invitación: siete días.** Abierta desde F0-07, en
  `Invitaciones::DIAS_DE_VIGENCIA`, en un solo sitio. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`. Ya son cuatro las tablas de negocio que
  dependen de ello.
- Abierta de catorce sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide 8.3+**.
  `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-04 · Registro de empleado**: el asistente de cuatro pasos de la sección 5.1 a
  5.3 del documento 1. Reemplaza el cuerpo de `EmpleadoController::nuevo()`, que hoy pinta una
  sección pendiente. **Antes de empezar, mira las dos preguntas para Angela de arriba: género (D.5) y
  fondo de cesantías se piden los dos en ese formulario.**
- **El registro nunca crea una persona a ciegas: primero `Persona::conDocumento()`.** El paso 1 es
  además donde vive la advertencia de reingreso de F1-05. El formulario de ubicación ya está resuelto
  con `SelectorUbicacion` más `Ubicaciones::paraFormulario()`.
- **El listado ya está listo para recibir lo que se registre**: al guardar, redirigir a `/empleados`
  deja al empleado nuevo en la tabla sin tocar nada más.
- **Toda pantalla de listado nueva reutiliza `TablaDatos` con `ordenable`, `BarraFiltros` y
  `Paginacion`**, y su controlador reutiliza el patrón de `ListadoDeEmpleados`: un objeto que lee la
  petición, arma la consulta una vez y se la presta a la pantalla y a la exportación.
- **La columna de saldo de vacaciones entra en el listado cuando exista F2-02**, no antes.
- **No queda ninguna tarea bloqueada en el backlog.**
