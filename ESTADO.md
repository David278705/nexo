# Estado del proyecto

**Última sesión:** 2026-08-24 · Tarea F5-06 (salud del sistema y métricas)
**Verificación:** verde — Pint, PHPStan sin errores, **1.116** pruebas de Pest, build y 335 de Vitest.
**Siguiente tarea:** F5-07 · Bitácora y acceso al entorno del cliente.

## Qué se hizo

- **F5-06, salud del sistema y métricas** (documento 4, capítulos 3 y 9). `Proveedor/Inicio` se
  reescribió entera con las seis tarjetas de atención (3.1) y los siete indicadores de negocio (3.2)
  —`App\Proveedor\PanelDeInicio`—, y `Proveedor/Salud` nació de cero —`App\Proveedor\SaludDelSistema`—,
  con el proceso diario, la cola y los errores de aplicación. `Salud` salió de
  `SeccionDelPanel::pendientes()`: solo queda `Equipo`.
- **Dos tablas nuevas, las dos anticipadas desde F3-01.** `ejecuciones_de_alertas` —el historial de
  corridas del proceso diario, con la duración medida en `EnviarAlertasDiarias` (`hrtime()`, que es lo
  único que `ResumenDelProceso` no podía saber de sí mismo) y guardada por
  `App\Dominio\Alertas\RegistroDeEjecuciones`— y `errores_aplicacion`, con
  `App\Proveedor\RegistroDeErrores` enganchado al `reportable()` de `bootstrap/app.php`, que hasta hoy
  estaba vacío. Laravel descarta antes de llegar ahí lo que nunca fue un error de negocio —validación,
  404, autorización—, así que la tabla no se llena de ruido.
- **La cola no necesitaba tabla nueva.** `jobs` y `failed_jobs` son el andamiaje estándar de Laravel
  desde el primer día; `App\Proveedor\ColaDeTrabajos` las consulta y el reintento delega en el propio
  `queue:retry` del framework.
- **Las copias de seguridad no se inventan.** La decisión A.5 del documento 6 aplaza esa
  infraestructura a propósito «hasta que exista producto»; la pantalla dice con esas palabras que
  depende de esa decisión en vez de fingir una fecha, la misma disciplina que F5-05 con la tasa de
  rebote.
- **«Con la variación frente al mes anterior» necesitaba una foto que no existía.** `metricas_mensuales`
  —una fila por mes, capturada el día 1 por el nuevo comando `metricas:capturar-mes`— es la pieza
  nueva. El primer mes de vida del sistema no tiene con qué comparar, y la tarjeta lo dice en vez de
  mostrar una variación de mentira.
- **Tres números sin tabla propia, resueltos con lo que ya existía.** «Clientes en riesgo» se mide
  contra `accesos` (dos ventanas de 30 días, con el umbral de caída en `config/panel.php` porque el
  documento no lo fija). «Cliente sin arrancar» usa los dos números que el documento sí trae escritos
  (una semana, cinco empleados), como constantes. «Bajas» y «cancelación acumulada» se leen del borrado
  lógico de `tenants` —la única forma de dar de baja una empresa que la regla 4 permite—, aunque hoy
  ninguna pantalla ofrezca esa acción, así que el número es honesto en cero.
- **`TarjetaDeAccion` se reutilizó del bloque de acción del cliente** (documento 3, 5.2) para las seis
  tarjetas de atención, en vez de duplicarla: ganó el tono `exito` (verde, para «esto que se vigila
  está bien») y un `valor` que acepta texto además de número («OK», «Al día», «2027»).
- Pruebas nuevas en `InicioDelProveedorTest.php`, `SaludDelSistemaTest.php` y dos casos más en
  `TarjetaDeAccion.test.js`. `AccesoFactory` es nueva: no existía ninguna para poder controlar fechas de
  acceso en pruebas.

## Decisiones tomadas

- **El umbral de «clientes en riesgo» es criterio comercial y vive en `config/panel.php`.** El
  documento dice «cayó de forma marcada» sin número; la propuesta —30 días contra los 30 anteriores,
  caída a la mitad o más— sigue el mismo patrón que `salud_de_uso`.
- **«Bajas de clientes» se lee del borrado lógico de `tenants`**, no de un estado «cancelada» que no
  existe. Es una interpretación de un mecanismo ya obligatorio (regla 4), no una regla de negocio
  inventada, y el número se queda en cero hasta que exista una acción de cancelar.
- **Las copias de seguridad se declaran pendientes de una decisión de infraestructura (A.5), no se
  simulan.** Ver arriba.
- **La foto mensual (`metricas_mensuales`) captura el mes que acaba de cerrar, el día 1 a las 00:10**,
  no el que empieza: nada cambia entre las 00:00 y esa hora, así que el valor leído en ese momento es
  el mismo que hubiera tenido a las 23:59 del día anterior.

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

- **El umbral de «clientes en riesgo» de F5-06 es una propuesta**, en `config/panel.php`, clave
  `riesgo_de_cancelacion`: 30 días de ventana, caída a la mitad. Confirmarlo o cambiarlo es editar ese
  archivo.
- **La escalera de mora sigue siendo tuya**, en `config/panel.php`, clave `cobro.escalera_de_mora`.
- **La tabla de precios (A.1) sigue sin decidir** y el catálogo de planes nace vacío.
- **Un contrato a término fijo cargado con prórrogas anteriores arranca contando cero prórrogas.**
- **La plantilla 1 sigue sin país de expedición del documento.**
- **`REV-06` sigue tuya: acotar la regla de «la fecha entre paréntesis».**
- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Hasta que eso se haga, ningún correo sale de la máquina
  **y ahora también la salud del sistema de F5-06 sigue sin poder decir si el correo llega**, que es la
  segunda consecuencia visible de esa tarea bloqueada.
- **A.5, infraestructura y copias de seguridad, sigue abierta y ahora bloquea algo visible**: la
  tarjeta «Copias de seguridad» de la salud del sistema (F5-06) dice que depende de esa decisión en vez
  de enseñar una fecha inventada.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F5-07, bitácora y acceso al entorno del cliente** (documento 4, 4.4): la
  tabla `notas_cliente` —ya está en el modelo de datos desde el documento 2, 3.5, y esta es la tarea
  que la construye— y el acceso del owner de solo lectura al entorno de una empresa, registrado en la
  auditoría **de esa empresa** y visible para ella.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
- **La tabla de precios sigue vacía** (A.1): cuando exista, F5-06 no tiene nada que ajustar, porque
  «ingreso recurrente» ya lee el precio del plan de cada suscripción, no un número escrito a mano.
