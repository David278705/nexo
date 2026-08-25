# Estado del proyecto

**Última sesión:** 2026-08-24 · F6-07 (operación: Sentry, plan de incidentes, panel de salud), cerrada
`[!]` porque el paso que falta es de David.
**Verificación:** verde — Pint, PHPStan sin errores, **1.169** pruebas de Pest, build y **384** de
Vitest.
**Siguiente tarea:** ninguna hasta que David haga la restauración de Railway que pide F6-07. Después
de eso, marcar F6-07 `[x]` y seguir con `REV-07`.

## Qué se hizo

F6-07 pedía cuatro cosas: monitoreo de errores, copias de seguridad con una restauración probada,
plan de incidentes y confirmar HTTPS. Las tres primeras eran código o documentación; la segunda tiene
un paso final que solo puede hacer David con su cuenta de Railway.

- **Monitoreo de errores con Sentry.** `composer require sentry/sentry-laravel`;
  `Sentry\Laravel\Integration::handles($exceptions)` agregado a `bootstrap/app.php`, en el mismo
  `withExceptions()` que ya tenía `RegistroDeErrores` (F5-06) y los tres textos de error del documento
  3 — las tres reportan la excepción, cada una a su destino, sin pisarse. Canal `sentry` explícito en
  `config/logging.php` con nivel `error`, para no llenar Sentry de ruido de depuración. `.env.example`
  ganó el bloque de `SENTRY_LARAVEL_DSN` y `LOG_STACK=single,sentry`, comentado, con la misma
  convención que ya usan las cinco líneas de correo. Sin DSN el SDK queda inactivo: local y pruebas
  corren exactamente igual que antes, y `tests/Unit/MonitoreoDeErroresTest.php` lo comprueba —el canal
  existe, el DSN se lee de la variable correcta, y la aplicación arranca sin romperse sin DSN puesto.
- **Plan de incidentes**, `docs/07-plan-de-incidentes.md`. No partía de cero: el documento 4, capítulo
  14, ya tenía tres escenarios escritos —sistema caído, incidente de seguridad, cálculo mal—. Lo que
  A.5 pedía y no existía era juntarlos con lo que agrega la infraestructura real y, sobre todo, **el
  escenario que faltaba: que una alerta legal no salga**, que es la promesa central del producto
  (`CLAUDE.md`). Dice dónde mirar primero —Sentry, la pantalla Salud del sistema, la entregabilidad
  del panel— y cómo reintentar el proceso diario de un día concreto sin duplicar nada, gracias al
  índice único de `alertas_enviadas`.
- **La pantalla «Salud del sistema» dejó de decir algo que ya no era cierto.** Desde F5-06, la tarjeta
  de copias de seguridad decía «depende de una decisión pendiente (A.5)» — cierto hasta el 23 de
  agosto, falso desde que David decidió Railway. `SaludDelSistema::paraLaPantalla()` y `Salud.vue`
  ahora describen el mecanismo real —*Point-in-Time Recovery*, WAL continuo, retención de 7 días—,
  **sin inventar una hora de «última copia»**: es un proceso continuo, no una corrida puntual, así que
  fingir una fecha habría sido exactamente el «indicador que se mira para decidir y no se puede
  confiar» que el propio código llevaba dieciocho meses citando. Pruebas de `SaludDelSistemaTest.php`
  y `Salud.test.js` actualizadas al nuevo texto.
- **HTTPS:** nada que hacer. Railway lo da por defecto en sus dominios.

## Decisiones tomadas

- **El nivel del canal `sentry` quedó en `error` y no en `debug`.** Es una decisión técnica, no legal
  ni de negocio: Sentry es para lo que hay que atender, y con `debug` cada consulta SQL de cada
  petición local terminaría contando contra el plan gratuito el día que alguien deje el DSN puesto sin
  querer.
- **El plan de incidentes no reemplaza el capítulo 14 del documento 4: lo completa.** Reescribirlo
  entero hubiera duplicado texto que ya estaba bien escrito y correcto; lo que hacía falta era el
  escenario que faltaba y la referencia a Sentry y Railway, que no existían cuando se escribió el
  documento 4.

## Por qué F6-07 no quedó `[x]`

El «hecho cuando» de la tarea es literal: **«existe una copia que se restauró y funcionó»**. Eso exige
entrar al panel de Railway con la cuenta de David, pedir una restauración a un punto en el tiempo,
verificar un registro en el servicio nuevo que crea Railway, y descartarlo. Es una acción real sobre
infraestructura de producción con una cuenta a la que este agente no tiene ni debería tener acceso —no
es una regla de negocio ambigua que se pueda resolver leyendo un documento, es un paso que solo puede
dar una persona. Los cuatro pasos exactos, y dónde dejar la evidencia, están en `BACKLOG.md` bajo
F6-07 y repetidos en `docs/07-plan-de-incidentes.md`, sección 4.

## Preguntas para Angela

- (vacío — nada nuevo esta sesión)

## Decisiones para David

- **F6-07 te espera a ti.** Los cuatro pasos de la restauración de prueba están en `BACKLOG.md` y en
  `docs/07-plan-de-incidentes.md`, sección 4. Cuando la hagas, deja la evidencia en `bitacora/` y marca
  la tarea `[x]` — o pide que la próxima sesión lo haga por ti una vez esté la evidencia.
- **Todas las heredadas de la sesión anterior siguen abiertas** — ver el detalle en el historial de
  `git log` de este archivo si hace falta releerlas: los tres nombres del filtro «Estado del
  contrato», el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor,
  el texto del contrato que vence el día del tope, «acumula 3 prórrogas inferiores a un año», D.12 (no
  hay número sugerido de referencia para la vigencia de un acceso de prueba), D.4 (rangos de edad y de
  antigüedad, la única BLOQUEANTE), `REV-06` (acotar la regla de «la fecha entre paréntesis»),
  verificar a mano en un navegador el flujo de F6-06, el menú desplegable en pantallas pequeñas que no
  atrapa el foco, el mínimo del motivo de una visita al entorno, el umbral de «clientes en riesgo» y
  la escalera de mora, un contrato a término fijo cargado con prórrogas anteriores arrancando en cero,
  la plantilla 1 sin país de expedición, `User`/`Acceso`/`Invitacion` sin `SoftDeletes`, los meses
  comerciales de treinta días, la prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente
  de `CatalogosTest`, el texto del empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, y la
  frase del prototipo sobre el saldo negativo.

## Notas para la próxima sesión

- **No tomes ninguna tarea nueva mientras F6-07 siga `[!]`.** Es la primera de la lista y está escrita
  para que nadie la reintente sin la parte de David hecha.
- **Si David ya dejó la evidencia de la restauración en `bitacora/`:** marca F6-07 `[x]` citando esa
  evidencia, y sigue con `REV-07` — la restauración nocturna del ambiente de demostración que llena la
  auditoría, que va delante de `REV-04` a propósito.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**, ajenos a esta tarea.
