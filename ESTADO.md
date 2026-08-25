# Estado del proyecto

**Última sesión:** 2026-08-24 · `REV-07` (la restauración nocturna del ambiente de demostración dejaba
de llenar la auditoría)
**Verificación:** verde — Pint, PHPStan sin errores, **1.174** pruebas de Pest, build y **384** de
Vitest.
**Siguiente tarea:** `REV-04` — Pantalla de auditoría. `F6-07` sigue `[!]` y se salta; `F3-06b` también.

## Qué se hizo

`REV-07` pedía dos cosas: un mecanismo para escribir sin dejar línea de auditoría en un tramo concreto
de código, y decidir quién puede usarlo. Lo segundo era el grueso de la tarea, según la propia
descripción del backlog.

- **`ObservadorDeAuditoria::sinAuditar(Closure $funcion)`.** Un flag estático que `anotar()` revisa
  primero: si está activo, ninguna escritura de ningún modelo deja línea, sin importar cuál sea. Se
  activa y se desactiva con `try`/`finally`, así que una excepción dentro de la función no lo deja
  pegado en `true` para el resto de la petición.
- **La decisión: se restringió al ambiente de demostración, y se hizo cumplir en tiempo de ejecución,
  no solo por convención.** `sinAuditar()` mira la empresa en contexto (`tenant_actual()`) y revienta
  con `SinAuditarRestringido` si no está marcada `es_ambiente_demo` — incluida la ausencia total de
  contexto. La razón: cualquier otra empresa, incluidas las del propio proveedor, tiene que auditar
  todo lo que escribe sin excepción (regla 4 del proyecto); dejar el bloque abierto para quien sea
  sería la forma más fácil de esconder una edición real. No se guardó como una lista de modelos —el
  camino que ya usa `SIN_AUDITAR`— porque `Persona`, `Vinculacion` y las novedades **sí** tienen que
  auditarse en las empresas reales; lo que cambia no es el modelo, es en qué empresa se está
  escribiendo.
- **`AmbienteDeDemostracion::restaurar()` usa el bloque.** `vaciar()`, `poblar()` y el `forceFill` de
  `demo_restaurado_en` quedan dentro de un solo `sinAuditar()`, adentro de la misma transacción que ya
  envolvía a las dos primeras. El `demo_restaurado_en` se movió adentro a propósito: se probó primero
  sin moverlo y la escritura del `Tenant` seguía dejando dos líneas por restauración, porque pasaba
  fuera del bloque.
- **Pruebas.** `AmbienteDeDemostracionTest.php` gana el «hecho cuando» literal de la tarea: se restaura
  una vez para que la empresa y su usuario existan —esas dos líneas sí quedan, porque pasan una sola
  vez en la vida del sistema, no cada noche—, y dos restauraciones más después no suman ninguna línea
  nueva. `AuditoriaTest.php` gana cuatro casos para el mecanismo en sí: se abre dentro de la demo, no se
  abre fuera de ella, no se abre sin ninguna empresa en contexto, y se vuelve a cerrar aunque la función
  que ejecuta lance una excepción. **La prueba que vigila que una empresa real sigue auditándose entera
  no se tocó**, como pedía la tarea.

## Decisiones tomadas

- **El guardia de `sinAuditar()` revienta en vez de devolver silenciosamente sin ejecutar nada.** Un
  `return` callado dejaría pasar por alto, sin ningún aviso, el intento de usarlo fuera de la demo — el
  mismo criterio que ya usa `AuditoriaInmutable` para editar o borrar una línea.
- **No se tocó `SIN_AUDITAR`.** Es una lista de modelos que nunca se auditan en ninguna empresa; el
  problema de la demo es justo lo contrario, modelos que sí hay que auditar en todas partes menos en
  una.

## Preguntas para Angela

- (vacío — nada nuevo esta sesión)

## Decisiones para David

- **Todas las heredadas de sesiones anteriores siguen abiertas** — ver el detalle en el historial de
  `git log` de este archivo si hace falta releerlas: `F6-07` te espera a ti (los cuatro pasos de la
  restauración de prueba en Railway, en `BACKLOG.md` y en `docs/07-plan-de-incidentes.md` sección 4);
  los tres nombres del filtro «Estado del contrato»; el texto del correo de prueba de entregabilidad;
  los textos del correo de obra o labor; el texto del contrato que vence el día del tope; «acumula 3
  prórrogas inferiores a un año»; D.12 (no hay número sugerido de referencia para la vigencia de un
  acceso de prueba); D.4 (rangos de edad y de antigüedad, la única BLOQUEANTE); `REV-06` (acotar la
  regla de «la fecha entre paréntesis»); verificar a mano en un navegador el flujo de F6-06; el menú
  desplegable en pantallas pequeñas que no atrapa el foco; el mínimo del motivo de una visita al
  entorno; el umbral de «clientes en riesgo» y la escalera de mora; un contrato a término fijo cargado
  con prórrogas anteriores arrancando en cero; la plantilla 1 sin país de expedición;
  `User`/`Acceso`/`Invitacion` sin `SoftDeletes`; los meses comerciales de treinta días; la prórroga
  mínima (B.3); el fondo de cesantías; la prueba intermitente de `CatalogosTest`; el texto del empleado
  inactivo; PHP 8.2 contra el 8.3 que pide `CLAUDE.md`; y la frase del prototipo sobre el saldo
  negativo.

## Notas para la próxima sesión

- **Toma `REV-04` — es la primera `[ ]` real.** Es la pantalla de auditoría: el módulo está en el menú,
  la tabla y el observador existen desde F0-08, y ninguna tarea del backlog le había dado pantalla.
  Ahora que `REV-07` limpió el ruido de la demostración, la pantalla no va a abrir con miles de líneas
  contando que el sistema se borró solo de madrugada.
- **`F6-07` y `F3-06b` siguen `[!]` y se saltan**, cada una esperando su propio paso manual.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**, ajenos a esta tarea.
