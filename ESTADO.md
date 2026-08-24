# Estado del proyecto

**Última sesión:** 2026-08-24 · Tarea F6-02 (textos del sistema)
**Verificación:** verde — Pint, PHPStan sin errores, **1.140** pruebas de Pest, build y **343** de Vitest.
**Siguiente tarea:** F6-03 · Confirmaciones con consecuencia.

## Qué se hizo

- **F6-02, textos del sistema** (documento 3, 6.3). De los nueve textos de la tabla, seis ya estaban
  cubiertos por tareas anteriores: los tres estados vacíos (F6-01), el documento inexistente
  (F2-03/F2-04), «Incapacidad registrada.» y sus equivalentes en cada controlador, y la confirmación de
  borrado (F2-10). Los tres que faltaban —«Sin permiso», «Sesión expirada» y «Error inesperado»— no
  tenían pantalla propia: los contestaba Laravel directamente, en inglés la de una autorización
  denegada sin motivo de negocio (`AuthorizationException`, mensaje por defecto «This action is
  unauthorized.») y con traza de depuración la de un error real. Eso es exactamente el «mensaje escrito
  para programadores» que la tarea prohíbe.
- **`App\Presentacion\TextoDeError`**, nuevo: los tres textos literales del documento, en un solo
  sitio, junto a `App\Presentacion\Formato` que ya guardaba los de presentación.
- **`resources/js/Pages/Error.vue`**, nuevo: sin armazón (menú, cabecera), porque un error real puede
  llegar antes de que el usuario o la empresa queden resueltos en la petición.
- **`bootstrap/app.php` gana un `respond()`** dentro de `withExceptions()`: 419 (sesión vencida por
  CSRF) redirige a `/acceso` con el texto en la prop `estado` que `PanelDeAcceso.vue` ya sabía mostrar
  desde la recuperación de contraseña; 403 y 500 renderizan `Error.vue`. **Se deja fuera el entorno
  local** para no perder la traza de depuración mientras se programa; en `testing` sí se aplica, porque
  si no, no habría cómo probarlo.

## Decisiones tomadas

- **Un 403 de negocio no pierde su mensaje.** `TextoDeError::paraSinPermiso()` usa el mensaje de la
  excepción si no está vacío —como «Estás en el entorno del cliente en modo consulta…» de
  `EntornoDeSoloLectura` (F5-07)— y el genérico del documento 3 solo cuando el `abort(403)` no trae
  ninguno.
- **Una autorización denegada sin motivo propio se remapea con `$exceptions->map()`, antes de que
  `Handler::prepareException()` la convierta.** El único `FormRequest::authorize()` que hoy devuelve
  `false` es `CambiarATerminoIndefinido` (fuera del término fijo, F1-11); sin este mapeo, a la altura de
  `respond()` ya no queda un `AuthorizationException` que distinguir de un `abort(403, 'motivo de
  negocio')` —Laravel convierte ambos casos a excepciones de mensaje no vacío antes de esa etapa—, así
  que la única forma de no mostrar el inglés de Laravel era interceptarla más temprano.
- **`TokenMismatchException` no se detecta por tipo en `respond()`, sino por el código 419.**
  `prepareException()` también la transforma antes de que `respond()` la reciba, así que comprobar el
  tipo original no funciona; el código de estado sí es estable.
- **Cinco pruebas nuevas en `tests/Feature/TextosDeErrorTest.php`**, con rutas efímeras en el grupo
  `web` (patrón ya usado por `AislamientoTest.php`) para no depender de un camino real del sistema para
  cada uno de los cuatro casos. El primer segmento es `/prueba-de-error/` y no `/prueba/`, porque
  `routes/web.php` ya usa `/prueba/{token}` para el acceso de demostración.

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
  de seguridad» de la salud del sistema, y ahora también F6-07 (Operación), que la pide de nuevo.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F6-03, confirmaciones con consecuencia.** «Toda acción destructiva explica
  el efecto calculado, no pregunta "¿está seguro?"». Las tres novedades del capítulo 6 ya lo hacen
  desde F2-10 (`ConfirmacionDeBorrado`); lo que probablemente falta revisar es si queda alguna acción
  destructiva fuera de esas tres —o alguna que todavía use un «¿estás seguro?» genérico— en el resto
  del sistema y del panel del proveedor.
- **`REV-04` sigue pendiente**: el módulo `Auditoria` del entorno del cliente sigue siendo el último
  `SeccionPendiente`. El acceso del proveedor deja constancia en la auditoría del cliente desde F5-07,
  pero el cliente todavía no la puede mirar en ninguna pantalla.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
