# Estado del proyecto

**Última sesión:** 2026-08-24 · Tarea F6-05 (pantallas pequeñas)
**Verificación:** verde — Pint, PHPStan sin errores, **1.141** pruebas de Pest, build y **353** de Vitest.
**Siguiente tarea:** F6-06 · Accesibilidad.

## Qué se hizo

- **F6-05, pantallas pequeñas** (documento 3, 6.4). De los cuatro comportamientos que pide la sección,
  tres ya estaban resueltos antes de esta tarea, dos completos y uno con un error de umbral:
  - **El menú colapsable ya existía completo desde F0-10.** `Armazon.vue` lo abre por encima del
    contenido hasta 1024px, con su botón en `EncabezadoApp.vue`. No se tocó nada.
  - **Las tablas en tarjeta eran lo único que faltaba por completo.** `TablaDatos.vue` gana una
    segunda vista, un `<ul>` de tarjetas visible solo `lg:hidden`, hermana de la tabla que pasa a
    `hidden lg:block`. Las tres columnas de la tarjeta son **las tres primeras que no sean la de
    acciones** —una regla mecánica y consistente, no un juicio distinto por cada una de las veinte
    pantallas que usan el componente—. La columna de acciones, si existe, se enseña siempre y aparte,
    con `@click.stop` para que un botón dentro de una tarjeta clicable no dispare también el `abrir`
    de la fila. Los veinte componentes que usan `TablaDatos` heredan la tarjeta sin que se les tocara
    una línea.
  - **Los modales a pantalla completa estaban implementados con el prefijo equivocado.** `Modal.vue`
    cambiaba a caja centrada desde `sm:` (640px) en vez de `lg:` (1024px): entre 640 y 1024px se veía
    una caja fija de 560/800px en lugar de pantalla completa. Se corrigieron las cuatro clases; los
    cinco modales que envuelven `Modal.vue` quedan arreglados de una vez.
  - **El panel de inicio pasaba a dos columnas desde 640px.** `Inicio.vue` y `Proveedor/Inicio.vue`
    cambiaron `sm:grid-cols-2` por `lg:grid-cols-2`, o se quitó el paso intermedio, para que la única
    columna dure hasta 1024px. El orden de prioridad de los bloques ya lo decidía el servidor desde
    F4-04.
  - Pruebas nuevas en `TablaDatos.test.js` (la tarjeta, sus tres campos, la columna de acciones
    siempre visible, el clic y los dos prefijos `lg:hidden`/`lg:block`) y en `Modal.test.js` (el
    prefijo `lg:` y no `sm:`).

## Decisiones tomadas

- **«Los tres campos más importantes» se leyó como una regla mecánica**: las tres primeras columnas
  declaradas que no sean la de acciones, en vez de escoger a mano el mejor trío para cada uno de los
  veinte listados. El orden de columnas de cada pantalla ya pone lo identificador primero —documento,
  nombre, empresa— y lo secundario después; juzgar caso por caso habría sido una decisión subjetiva
  repetida veinte veces, con más riesgo de inconsistencia que la regla simple. Si algún listado
  concreto necesita otro trío, se puede añadir después una marca explícita por columna sin romper el
  resto.
- **La columna de acciones no cuenta entre los tres campos y siempre se ve.** Sin editar ni eliminar
  desde la tarjeta, los tres módulos de novedades con edición (F2-10) quedarían sin esa función en el
  celular, que es justo lo que el «hecho cuando» de F6-05 pide evitar.
- **El breakpoint de todo el capítulo 6.4 es `lg` (1024px), el que ya usaba `Armazon.vue`.** El
  proyecto no tiene `tailwind.config.js` —Tailwind 4 se configura en `resources/css/app.css`— y no
  redefine los puntos de corte, así que `lg` es el de 1024px por defecto y no hubo que inventar nada.

## Preguntas para Angela

- Las tres de F5-01 siguen abiertas: «Sura» contra «Sura EPS» y «ARL Sura» en la fila de ejemplo de la
  plantilla 1; el código de diagnóstico que la plantilla 3 marca opcional y el sistema exige; y el
  rango «Menos de 30 min» de la plantilla 2, que el sistema parte en dos.
- Heredadas, las cinco de sesiones anteriores: los tres nombres del filtro «Estado del contrato», el
  texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».
- D.12 sigue formalmente abierta: no hay un número sugerido de referencia para la vigencia de un
  acceso de prueba; hoy el formulario no ofrece ninguno y quien lo genera decide caso por caso.

## Decisiones para David

- **La tercera condición del 4.4 (F5-07) sigue siendo tuya y es de papel**: «el contrato y el acuerdo
  de tratamiento de datos deben mencionarlo» el acceso al entorno del cliente. El software ya cumple
  las dos suyas; no debería usarse con un cliente real hasta que ese párrafo exista (A.2 y A.6).
- **El mínimo del motivo (diez caracteres) de una visita al entorno sigue siendo propuesta**, en
  `EntrarAlEntornoDelCliente`.
- **El umbral de «clientes en riesgo» de F5-06 sigue siendo una propuesta**, en `config/panel.php`,
  clave `riesgo_de_cancelacion`.
- **La escalera de mora sigue siendo tuya**, en `config/panel.php`, clave `cobro.escalera_de_mora`.
- **La tabla de precios (A.1) sigue sin decidir** y el catálogo de planes nace vacío.
- **Un contrato a término fijo cargado con prórrogas anteriores arranca contando cero prórrogas.**
- **La plantilla 1 sigue sin país de expedición del documento.**
- **`REV-06` sigue tuya: acotar la regla de «la fecha entre paréntesis».**
- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Hasta que eso se haga, ningún correo sale de la máquina
  y la salud del sistema sigue sin poder decir si el correo llega.
- **A.5, infraestructura y copias de seguridad, sigue abierta** y sigue bloqueando la tarjeta «Copias
  de seguridad» de la salud del sistema, y F6-07 (Operación), que la pide de nuevo.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F6-06, accesibilidad**: contraste, recorrido con teclado, foco visible,
  etiquetas reales, zoom al 200%. **Hecho cuando:** se completa un registro de novedad usando solo el
  teclado. Esta tarea no trae «Fuente» explícita en el backlog; el capítulo relevante es
  `docs/03-diseno-e-interfaz.md`, sección «Accesibilidad» (dentro del capítulo 3) y
  `.claude/rules/30-interfaz.md`, que ya trae un resumen.
- **La regla nueva de «tres campos más importantes» vive en un solo sitio**, el comentario de cabecera
  de `TablaDatos.vue`. Si una futura tarea necesita que un listado concreto muestre un trío distinto al
  de sus tres primeras columnas no-acciones, ahí es donde hay que abrir la puerta —una marca por
  columna, no una reescritura del componente—.
- **`REV-04` sigue pendiente**: el módulo `Auditoria` del entorno del cliente sigue siendo el último
  `SeccionPendiente`. El acceso del proveedor deja constancia en la auditoría del cliente desde F5-07,
  pero el cliente todavía no la puede mirar en ninguna pantalla.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
