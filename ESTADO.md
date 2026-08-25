# Estado del proyecto

**Última sesión:** 2026-08-24 · `REV-06` (bloqueada)
**Verificación:** verde — sin cambios de código esta sesión; el estado sigue siendo el de `REV-04`:
Pint, PHPStan sin errores, 1.181 pruebas de Pest, build y 385 de Vitest.
**Siguiente tarea:** No queda ninguna `[ ]` en el backlog. Las dos únicas pendientes están
bloqueadas: `F3-06b` (operación de David, no código), `F6-07` (operación de David, no código) y
ahora `REV-06` (decisión de David sobre el texto de una regla). El backlog de tareas de producto
está agotado hasta que David resuelva alguna de las tres.

## Qué se hizo

- Se tomó `REV-06`, la primera `[ ]` del backlog. La propia tarea dice, en su propio texto, que es
  «una decisión de David sobre el texto de una regla, no trabajo de producto», y su «Hecho cuando»
  ofrece dos redacciones distintas según lo que David elija para `.claude/rules/30-interfaz.md`.
- Se revisó que la regla siga sin acotar (`.claude/rules/30-interfaz.md` línea 75, sigue diciendo
  «toda referencia temporal» sin distinguir correos de pantalla) y que ningún documento —tampoco
  `docs/06-decisiones-pendientes.md`, que no la menciona— registre que David ya haya decidido entre
  las dos opciones escritas en el backlog.
- No había nada que implementar sin inventar la decisión: cualquiera de las dos redacciones cambia
  qué se considera correcto en las ocho pantallas de listado y en el panel de inicio, así que
  escribirla a criterio propio sería inventar una regla de producto, justo lo que la sesión tiene
  prohibido.
- Se marcó `REV-06` como `[!]` en `BACKLOG.md` con el motivo, y no se tomó ninguna tarea siguiente,
  como manda el procedimiento de bloqueo.

## Decisiones tomadas

- Ninguna. No se implementó código porque la tarea es en sí misma la decisión pendiente.

## Preguntas para Angela

- (vacío — nada nuevo esta sesión)

## Decisiones para David

- **`REV-06` sigue esperándote**: elige entre las dos opciones ya escritas en el backlog para
  `.claude/rules/30-interfaz.md`:
  - **Opción A (la recomendada, repetida desde F1-13 y F2-11):** la regla de «fecha entre
    paréntesis» se queda literal solo en los correos de alertas —que es donde el documento 1, 9.1,
    la exige y donde el código ya la cumple desde F3-02—. En pantalla basta con que la referencia
    relativa tenga su fecha absoluta visible al lado, en el mismo sitio o en la columna contigua,
    que es lo que el código ya hace y lo que dibuja el prototipo.
  - **Opción B:** la regla vale literal también en pantalla. Si se elige esta, `REV-06` deja de ser
    una tarea de una línea: hay que repasar el panel de inicio y las ocho pantallas de listado
    agregando la fecha entre paréntesis a cada referencia relativa que hoy no la lleva.
  - Mientras no se decida, esta tarea seguirá bloqueada y el backlog de producto no tiene ninguna
    tarea `[ ]` disponible para la próxima sesión automática.
- Todas las heredadas de sesiones anteriores siguen abiertas — ver el detalle en el historial de
  `git log` de este archivo si hace falta releerlas: `F6-07` (los cuatro pasos de la restauración de
  prueba en Railway); los tres nombres del filtro «Estado del contrato»; el texto del correo de
  prueba de entregabilidad (`F3-06b`); los textos del correo de obra o labor; el texto del contrato
  que vence el día del tope; «acumula 3 prórrogas inferiores a un año»; D.12 (no hay número sugerido
  de referencia para la vigencia de un acceso de prueba); D.4 (rangos de edad y de antigüedad, la
  única BLOQUEANTE); verificar a mano en un navegador el flujo de F6-06; el menú desplegable en
  pantallas pequeñas que no atrapa el foco; el mínimo del motivo de una visita al entorno; el umbral
  de «clientes en riesgo» y la escalera de mora; un contrato a término fijo cargado con prórrogas
  anteriores arrancando en cero; la plantilla 1 sin país de expedición; `User`/`Acceso`/`Invitacion`
  sin `SoftDeletes`; los meses comerciales de treinta días; la prórroga mínima (B.3); el fondo de
  cesantías; la prueba intermitente de `CatalogosTest`; el texto del empleado inactivo; PHP 8.2
  contra el 8.3 que pide `CLAUDE.md`; y la frase del prototipo sobre el saldo negativo.
- **`App\Listados\Listado::ordenar()` desempata siempre con `orderBy('id', 'asc')`**, sin importar la
  `direccion` pedida, anotado desde `REV-04`: con `direccion=desc` y filas creadas en el mismo
  segundo, «lo más reciente primero» deja de ser cierto entre empates. Sigue sin tarea propia porque
  es del armazón compartido por las nueve pantallas de listado.

## Notas para la próxima sesión

- **El backlog no tiene ninguna `[ ]` pendiente.** Antes de repetir esta búsqueda, revisa si David
  dejó resuelta alguna de las tres bloqueadas (`REV-06`, `F3-06b`, `F6-07`) en este archivo o en el
  propio `BACKLOG.md`. Si ninguna se resolvió, no hay tarea de producto que tomar: dilo y termina,
  como pide el punto 1 del procedimiento cuando no queda ninguna `[ ]`.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
