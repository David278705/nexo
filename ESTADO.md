# Estado del proyecto

**Última sesión:** 2026-08-25 · Auditoría completa pedida por David: decidir lo que fuera legítimo
decidir sin inventar reglas legales, y dejar en el backlog todo lo que quedó a medias, olvidado o sin
conectar. No fue una tarea del backlog automático — fue trabajo directo con David.
**Verificación:** verde — Pint, PHPStan sin errores, **1.185** pruebas de Pest, build y **388** de
Vitest.
**Siguiente tarea:** `REV-08` · la plantilla de empleados no pide el país de expedición del documento.
`F6-07` y `F3-06b` siguen `[!]` y se saltan.

## Qué se hizo

Se auditaron las tres capas: las tareas `[!]`, las notas «heredadas» que se venían repitiendo en este
archivo desde `REV-02`, y una revisión manual de pantallas que el backlog daba por completas. De ahí
salieron catorce puntos. Once se resolvieron y quedaron implementados y probados esa misma sesión;
tres resultaron ser trabajo real y quedaron como tareas nuevas al final del backlog, más otras dos que
ya estaban ahí sin conectar.

**Resueltos e implementados, con `composer verificar` en verde después de cada uno:**

- **`REV-06`** — la regla de «la fecha entre paréntesis» quedó acotada en
  `.claude/rules/30-interfaz.md`: literal en los correos, fecha visible al lado en pantalla. Era la
  recomendación repetida desde F1-13 y F2-11; no hizo falta tocar ninguna pantalla, el código ya la
  cumplía.
- **D.4** (rangos de edad y de antigüedad) — resuelta con la propuesta que el documento 6 ya traía
  escrita, porque no era una regla legal, eran los tramos de un gráfico. Implementada en
  `IndicadoresDeComposicion`: los dos indicadores que le faltaban a «Composición» en el panel de
  inicio (F4-04) ya están completos, con prueba de los dos tramos sin techo.
- **Las cuatro de D.6** — orden del dashboard (confirmado tal como está construido), vigencia del
  acceso de demostración (14 días), nombre del proceso de carga inicial («Carga inicial», el que ya
  usa el código) y empleados retirados en el histórico (no, fuera de alcance del MVP).
- **B.3** — confirmado que `config/validaciones.php` ya implementa exactamente lo que Angela propuso
  en el documento 6: bloquea el tope de cuatro años, advierte la prórroga mínima. No hubo que tocar
  código, solo dejar de decir que seguía pendiente.
- **La prueba intermitente de `CatalogosTest`** — comparaba `Eps::pluck('id','nombre')` sin
  `orderBy`, y PostgreSQL no garantiza el orden sin él. Corregido con `orderBy('nombre')` en las dos
  puntas de la comparación; cinco corridas seguidas en verde.
- **PHP 8.2 contra el 8.3 que pedía `CLAUDE.md`** — la máquina real corre 8.2.12 desde el primer día
  sin ningún problema en más de setenta tareas, y `composer.json` ya pedía `^8.2`. Se corrigió el
  encabezado de `CLAUDE.md` para que diga la verdad en vez de una aspiración nunca cumplida.
- **El owner sin bloqueo en `/`** — encontrado mientras se probaba el sistema en local: el owner
  entraba al panel de inicio del cliente y veía una pantalla vacía en vez de un 403, porque `/` es la
  única sección sin middleware de módulo. Nuevo middleware `SoloCliente`, simétrico a `SoloProveedor`,
  aplicado solo a esa ruta — con la excepción de la visita al entorno (F5-07), que también redirige
  ahí a propósito y por poco se rompe con el primer intento del arreglo.
- **`Listado::ordenar()` desempataba siempre ascendente**, sin importar la dirección pedida —anotado
  desde `REV-04`—. Con `direccion=desc` y filas creadas en el mismo segundo, el desempate devolvía el
  orden contrario al pedido. Corregido para desempatar en la misma dirección; afecta a las nueve
  pantallas que comparten el armazón.
- **El menú desplegable de pantallas pequeñas no atrapaba el foco** — el único diálogo del sistema que
  se quedó sin el mecanismo que F6-06 le dio a `Modal.vue`, porque no es un `Modal.vue`, es su propio
  armazón en `Armazon.vue`. Mismo patrón: atrapa el tabulador, enfoca el menú al abrir, devuelve el
  foco a quien lo abrió al cerrar.

**Investigados y descartados, sin inventar nada:** «meses comerciales de treinta días» y «el texto
del empleado inactivo» no aparecen en ningún `bitacora/*.md`, documento ni código actual con ese
nombre — puede que ya estuvieran resueltos por diseño o que la nota sobreviviera a un cambio que la
volvió obsoleta. La frase del prototipo sobre el saldo negativo tampoco existe ya en
`prototipos/sistema.html`.

**Confirmados como preguntas reales para Angela, sin tocar:** B.2 (periodo de prueba en cambio de
modalidad contractual — el escenario de «la nueva labor es completamente distinta» no está resuelto
expresamente en la norma) y el fondo de cesantías (no está en ningún documento funcional; hace falta
que un asesor laboral confirme si el dato hace falta pedirlo).

## Decisiones tomadas

- **Ninguna de las resoluciones de esta sesión era una regla legal.** D.4, D.6 y B.3 eran cortes de
  gráfico, confirmaciones de nombre y una verificación de que el código ya coincidía con lo que Angela
  había propuesto. Donde la pregunta sí era legal —B.2, fondo de cesantías— se dejó intacta.
- **REV-09 se diseñó para no fabricar historial.** La plantilla de carga pide «prórrogas ya
  realizadas» como un número, no como tramos con fecha y tipo. Reconstruir tramos falsos habría sido
  mentir en el historial visible del contrato. La decisión de implementación —guardar el número aparte
  y sumarlo al conteo, con una línea informativa en vez de tramos inventados— queda escrita en la
  tarea para que quien la construya no tenga que volver a decidirlo.

## Preguntas para Angela

- (vacío — las dos genuinas de esta sesión, B.2 y fondo de cesantías, ya estaban registradas de antes
  y no tienen novedad; ver `docs/06-decisiones-pendientes.md`.)

## Decisiones para David

- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway, en
  `BACKLOG.md` y en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Mientras tanto, `MAIL_MAILER=log` y ninguna alerta sale de la máquina de verdad —
  aunque en este momento sí hay Mailtrap configurado en el `.env` local para pruebas.
- **Verificar a mano en un navegador el flujo completo de F6-06** sigue pendiente — ninguna sesión de
  código puede hacer esa comprobación por ti.
- **Las dos preguntas para Angela siguen abiertas**: B.2 y el fondo de cesantías. Están en
  `docs/06-decisiones-pendientes.md`, secciones B.2 y en la nota de F1-04.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **La siguiente tarea es `REV-08`**, seguida de `REV-09` — las dos tocan la misma plantilla de
  empleados y conviene hacerlas en ese orden porque `REV-09` ya asume que `REV-08` no le cambió las
  columnas que no le tocan.
- **`REV-10` (Usuarios y permisos) y `REV-11` (Facturación) son las dos más grandes.** Son pantallas
  completas del cliente que la especificación pedía desde el principio (`docs/03` 5.7) y que ninguna
  fase llegó a construir — no estaban bloqueadas, nunca tuvieron tarea. `REV-10` trae consigo dar
  `SoftDeletes` a `User`, `Acceso` e `Invitacion`, que hace falta para que «desactivar a un usuario»
  sea de verdad borrado lógico.
- **`REV-12` es más chica**: conectar dos pestañas de la ficha de empresa del proveedor —«Alertas» y
  «Uso»— a datos que ya existen desde hace fases. Quedaron con el texto de cuando esos datos no
  existían y nadie volvió a mirarlas.
- **`F5-08` es de una sola línea**: poner 14 como valor sugerido en el formulario de accesos de
  prueba, ahora que D.12 ya tiene número.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
