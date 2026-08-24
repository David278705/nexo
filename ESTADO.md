# Estado del proyecto

**Última sesión:** 2026-08-24 · Tarea F6-04 (indicadores de carga)
**Verificación:** verde — Pint, PHPStan sin errores, **1.141** pruebas de Pest, build y **348** de Vitest.
**Siguiente tarea:** F6-05 · Pantallas pequeñas.

## Qué se hizo

- **F6-04, indicadores de carga** (documento 3, 2.4 y 2.5). La sesión encontró `Boton.vue` y
  `Formularios.test.js` ya modificados y sin commitear —el círculo que gira junto al texto mientras
  `enviando` es verdadero, con su prueba— y siguió desde ahí en vez de rehacerlo: el cambio es correcto
  y coincide exactamente con lo que pide la sección 2.5, «pantalla congelada» está mal, «indicador de
  carga» está bien.
- **Casi todos los formularios ya pasaban `enviando`** a `Boton.vue`, porque el documento 3 ya exigía
  deshabilitar el botón de guardar mientras envía desde antes de esta fase: los nueve `useForm` de
  Acceso, Empleados, la Configuración, el panel del proveedor y los cinco módulos de novedades. El
  cambio de `Boton.vue` les dio el círculo a todos a la vez, sin tocar ninguna pantalla.
- **Se barrieron los 37 componentes que usan `Boton`** buscando una acción que llame a
  `router.post`/`router.delete` directo y no guarde su propio `enviando`. Aparecieron seis, ninguna con
  indicador de ningún tipo: quitar un festivo, activar o desactivar una entidad y activar o desactivar
  un municipio (las tres en `Catalogos.vue`), anular un pago, quitar una nota de la bitácora, reenviar
  una invitación y reenviar el enlace de contraseña (las cuatro en `FichaDeEmpresa.vue`), y activar o
  desactivar un plan (`Suscripciones.vue`).
- **Cada una ganó su propio `ref`** —de fila, con el `id` de la fila en curso, cuando la tabla tiene más
  de una posible a la vez— fijado antes de la petición y limpiado en `onFinish`. `TablaDeCatalogo.vue`
  ganó la prop `alternando` para saber cuál de sus filas es la que está esperando.
- **Lo que no se tocó, y es a propósito**: las navegaciones con `<Link>`, los filtros con `router.get` y
  abrir una fila con `router.visit` ya los cubre la barra de progreso global de Inertia, configurada en
  `app.js` desde F0-09. El botón de copiar al portapapeles no espera ninguna red.

## Decisiones tomadas

- **La tarea no traía «Hecho cuando» ni «Fuente» en el backlog.** Se leyó el criterio de la sección 2.5
  del documento 3 —la fila «Espera» de la tabla— como la fuente, y el «hecho cuando» se interpretó como
  «ninguna acción que dispare una petición al servidor se queda sin ningún indicador», que es lo que se
  verificó una por una.
- **El indicador es siempre el mismo círculo de `Boton.vue` y no uno nuevo por pantalla.** Construir un
  segundo indicador habría sido la misma corrección que ya evitó `ModalDeBorrado`/`ModalDeNovedad`: dos
  formas de decir «espera» se separan a la primera corrección.

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

- **La siguiente tarea es F6-05, pantallas pequeñas**: menú colapsable, tablas en tarjeta, modales a
  pantalla completa. **Hecho cuando:** el sistema es usable a 375px de ancho. Fuente:
  `docs/03-diseno-e-interfaz.md` 6.4.
- **`REV-04` sigue pendiente**: el módulo `Auditoria` del entorno del cliente sigue siendo el último
  `SeccionPendiente`. El acceso del proveedor deja constancia en la auditoría del cliente desde F5-07,
  pero el cliente todavía no la puede mirar en ninguna pantalla.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
