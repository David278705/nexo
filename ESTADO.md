# Estado del proyecto

**Última sesión:** 2026-08-24 · Tarea F6-06 (accesibilidad)
**Verificación:** verde — Pint, PHPStan sin errores, **1.141** pruebas de Pest, build y **359** de Vitest.
**Siguiente tarea:** F6-07 · Operación.

## Qué se hizo

- **F6-06, accesibilidad** (documento 3, 3.5). De las cinco exigencias de la sección, cuatro ya
  estaban resueltas antes de esta tarea:
  - **Contraste**: la paleta de `app.css` ya trae fondo/borde/texto propios por cada tono
    —información, éxito, advertencia, error— calculados para 4,5:1, con `SistemaDeDisenoTest.php`
    vigilando que ningún componente escriba un color literal.
  - **Foco visible**: `:focus-visible` global en `app.css` desde F0-09.
  - **Etiquetas reales**: `CampoTexto`, `Selector` y `CampoFecha` ya usaban `<label for>` con
    `aria-describedby` hacia el error o la ayuda, no *placeholder*.
  - **Recorrido con teclado de las tablas**: `TablaDatos` ya daba `tabindex="0"` a la fila y a la
    tarjeta, con Enter y Espacio abriendo igual que el clic.
  - **Lo único que faltaba, y era justo lo que impedía el «hecho cuando», era `Modal.vue`.** No
    atrapaba el foco: quien tabulaba desde el último campo caía en la pantalla de atrás en vez de
    volver al primero, y al pasar del paso del documento al formulario grande de `ModalDeNovedad`
    —el modal pequeño se desmonta y el grande se monta en el mismo instante— el foco se perdía en
    el `body`. Completar una novedad usando solo el teclado se cortaba justo ahí.

## Decisiones tomadas

- **El arreglo se puso en `Modal.vue` y no en cada modal que lo envuelve.** Los cinco modales del
  sistema —`ModalDeNovedad`, `ModalDeBorrado`, `ModalDeRenovacion`, `ModalDeCambioAIndefinido` y el
  genérico— comparten el mismo componente base, así que una referencia al diálogo, una función
  `atraparFoco()` que intercepta Tab/Shift+Tab en los bordes y un `watch({ immediate: true })` sobre
  `abierto` que pone el foco dentro al abrir y lo devuelve a quien lo abrió al cerrar, alcanzan a los
  cinco sin tocarlos.
- **El foco inicial no se impone si alguien adentro ya se lo llevó.** `ModalDeNovedad` ya enfocaba el
  campo del documento a mano con su propio `nextTick`; `Modal` solo pone el primero de reserva
  —comprobando `contenedor.value.contains(document.activeElement)`— para los cuatro modales que no
  hacían nada especial. Se comprobó empíricamente con Vitest que el orden de los `watch` dentro del
  mismo ciclo de reactividad deja primero el foco explícito de `ModalDeNovedad` y luego, sin pisarlo,
  la reserva de `Modal`; y que al pasar del modal pequeño al grande, el `watch` del grande —que antes
  no existía— es lo que mueve el foco por primera vez.
- **Las pruebas comprueban el mecanismo, no una sesión de teclado en un navegador real.** El proyecto
  no tiene Playwright ni `@testing-library/user-event`; jsdom no implementa el recorrido nativo de
  Tab. Las pruebas nuevas de `Modal.test.js` y `ModalDeNovedad.test.js` siguen el mismo patrón que ya
  usaba el archivo para Escape: `dispatchEvent` de `KeyboardEvent` y comprobación de
  `document.activeElement` y `evento.defaultPrevented`.

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

- **Verificar a mano en un navegador el flujo de F6-06 sigue pendiente.** Esta sesión no tuvo forma
  de abrir un navegador real ni de simular Tab de punta a punta —no hay Playwright ni `user-event`
  instalados—, así que lo que queda probado es el mecanismo (el foco no se escapa del modal, se
  recupera al cerrar, y llega al modal grande al cambiar de paso), verificado con Vitest. Antes de
  dar F6-06 por cerrado del todo conviene completar a mano, con el teclado, el registro de una
  incapacidad o unas vacaciones en el navegador.
- **El menú desplegable de la barra lateral en pantallas pequeñas (`Armazon.vue`, `menuAbierto`) no
  atrapa el foco**, a diferencia de los modales. Se dejó fuera de esta tarea porque el «hecho cuando»
  es sobre el registro de una novedad, no sobre la navegación móvil, y solo aparece por debajo de
  1024px. Si una futura tarea de accesibilidad quiere cerrar ese hueco, el mismo patrón de
  `atraparFoco()` de `Modal.vue` es reutilizable.
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
  de seguridad» de la salud del sistema, y F6-07 (la siguiente tarea), que la pide de nuevo.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F6-07, Operación**: monitoreo de errores en producción, copias de
  seguridad automáticas y una restauración probada de principio a fin. **Hecho cuando:** existe una
  copia que se restauró y funcionó. **Esta tarea probablemente se bloquea de entrada**: depende de
  A.5 (infraestructura y copias de seguridad), que sigue abierta en `docs/06-decisiones-pendientes.md`
  y ya está anotada como bloqueante para F6-07 desde hace varias sesiones. Si sigue sin decisión,
  márcala `[!]` con el motivo y no inventes un proveedor de monitoreo ni una estrategia de copias.
- **`REV-04` sigue pendiente**: el módulo `Auditoria` del entorno del cliente sigue siendo el último
  `SeccionPendiente`. El acceso del proveedor deja constancia en la auditoría del cliente desde F5-07,
  pero el cliente todavía no la puede mirar en ninguna pantalla.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
