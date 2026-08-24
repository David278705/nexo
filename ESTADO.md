# Estado del proyecto

**Última sesión:** 2026-08-24 · Tarea F6-03 (confirmaciones con consecuencia)
**Verificación:** verde — Pint, PHPStan sin errores, **1.141** pruebas de Pest, build y **347** de Vitest.
**Siguiente tarea:** F6-04 · Indicadores de carga.

## Qué se hizo

- **F6-03, confirmaciones con consecuencia** (documento 3, 2.4 y 3.4). El «hecho cuando» de la tarea
  —eliminar unas vacaciones dice cuántos días se devuelven y cómo queda el saldo— ya estaba cumplido
  desde F2-10, y ninguna de las tres novedades del capítulo 6 usa un «¿está seguro?» genérico. Lo que
  quedaba, según la nota de la sesión anterior, era barrer el resto del sistema y el panel del
  proveedor buscando una acción destructiva que todavía preguntara así, o que no preguntara nada.
- **Aparecieron tres acciones sin ninguna confirmación**, las tres en el panel del proveedor y las
  tres disparando `router.post`/`router.delete` directo desde el botón de la fila, sin pasar por
  ningún modal: revocar un acceso de demostración (`Demostracion.vue`), quitar una nota de la
  bitácora de un cliente y anular un pago (las dos últimas en `FichaDeEmpresa.vue`). No preguntaban
  «¿está seguro?»: no preguntaban nada, que es peor que lo que la tarea prohíbe.
- **Las tres ganaron el mismo patrón que ya usaba `Catalogos.vue`** para quitar un festivo del
  calendario: un `ref` que señala la fila abre un `Modal` con el texto de la consecuencia y el botón
  rojo, y la acción de verdad —el `router.post`/`delete`— solo corre al confirmar.
- **Revocar el acceso y quitar la nota se redactan enteros en el cliente**, con datos que la fila ya
  trae (`nombreProspecto`, `texto` de la nota): no hace falta ninguna cuenta para saberlos.
- **Anular un pago sí necesitaba una cuenta**: cuánto vuelve a ser el próximo cobro es
  `proxima_fecha_cobro` menos un mes con `subMonthNoOverflow`, la misma resta que
  `Suscripciones::anularPago()` ya hacía para el mensaje de después del hecho. Se sacó a un método
  nuevo, `Suscripciones::consecuenciaDeAnular()`, de solo lectura —no escribe nada, solo la calcula—,
  y `FichaDeEmpresa::suscripcion()` la manda ya calculada en la prop `consecuenciaDeAnular`: la
  confirmación de la pantalla no recalcula nada, solo lee lo que el servidor ya mandó, igual que
  `ConfirmacionDeBorrado` de las novedades.

## Decisiones tomadas

- **La consecuencia de anular un pago es la misma para cualquier pago que se anule en ese momento**,
  porque `Suscripciones::anularPago()` siempre resta un mes de la `proxima_fecha_cobro` **actual** de
  la suscripción, no de una fecha propia de ese pago. Es el comportamiento que ya existía —no se tocó
  en esta tarea—, así que la confirmación nueva lo hereda tal cual: un solo texto en la prop
  `suscripcion`, no uno por fila del historial.
- **No se tocó ningún texto de `¿está seguro?` porque no había ninguno que decir así.** El problema
  real, en las tres acciones encontradas, era la ausencia total de confirmación y no la fórmula
  genérica que la tarea nombra como ejemplo de lo que hay que evitar.

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

- **La siguiente tarea es F6-04, indicadores de carga**: «En toda acción que tarde más de un
  instante». No trae «Hecho cuando» propio en el backlog, así que conviene apoyarse en el documento 3
  para el criterio exacto (spinner en botones que envían, estado deshabilitado mientras procesan,
  etc.) y revisar cuáles de las pantallas existentes ya lo hacen —muchos botones ya usan `:enviando`
  de `Boton.vue`— y cuáles no.
- **`REV-04` sigue pendiente**: el módulo `Auditoria` del entorno del cliente sigue siendo el último
  `SeccionPendiente`. El acceso del proveedor deja constancia en la auditoría del cliente desde F5-07,
  pero el cliente todavía no la puede mirar en ninguna pantalla.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
