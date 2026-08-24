# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F5-05 (consola de alertas del proveedor)
**Verificación:** verde — Pint, PHPStan sin errores, **1.083** pruebas de Pest, build y 324 de Vitest.
**Siguiente tarea:** F5-06 · Salud del sistema y métricas.

## Qué se hizo

- **F5-05, la consola de alertas del proveedor** (documento 4, capítulo 7). `App\Proveedor\ConsolaDeAlertas`,
  el `ConsolaDeAlertasController` bajo `/proveedor/alertas` y la pantalla `Proveedor/Alertas`.
- **El obstáculo real de la tarea era de aislamiento, no de interfaz.** `alertas_enviadas` tiene el
  trait `PerteneceATenant` desde F3-01 y su política de RLS activa; esa política no tiene excepción
  para el `owner` —lo dice la regla 1 del proyecto—, así que **no existe ninguna consulta que cruce
  empresas**, ni con el ORM ni con SQL directo. `ConsolaDeAlertas` hace lo mismo que ya hacía
  `ProcesoDiarioDeAlertas` y `ListadoDeEmpresas::empleadosDe()`: recorre las empresas una por una con
  `ContextoTenant::paraTenant()`, filtra dentro del contexto de cada una y combina, ordena y pagina el
  resultado ya en memoria. Es aceptable exactamente por lo que dice el documento 4, 16: «con pocos
  clientes no hacen falta indicadores sofisticados»; con cientos de empresas esto necesitaría una
  tabla de lectura aparte, pero hoy sería complejidad que nadie usa.
- **La forma de las props es la misma que `Listado::paraLaPantalla()`** —`filas`, `pagina`, `paginas`,
  `total`, `desde`, `hasta`, `filtros`, `aplicados`, `tieneFiltros`— para que la pantalla y `useListado`
  no tengan que saber que del otro lado no hay un `Eloquent\Builder`. La clase no extiende `Listado`
  porque violaría su contrato (asume una sola consulta): duplica en unas quince líneas los tres
  helpers de filtro que necesitaba (`fecha()`, `filtro()`, la validación de `empresa`), con un
  comentario que explica por qué no se heredan.
- **Las ocho columnas del capítulo 7** —programada, enviada, empresa, empleado, tipo, destinatarios,
  estado, intentos— y los cuatro filtros —rango de fechas, empresa, tipo, estado—. El detalle se abre
  en el mismo modal que el panel del cliente, con el aviso completo y el motivo del fallo.
- **El reenvío puntual.** `AlertaEnviada::puedeReenviarse()` y `::prepararParaReenvio()`, nuevos: una
  alerta pendiente no se reenvía —ya va en camino—, pero una enviada o una fallida sí, que son
  exactamente los dos casos que motivan la acción («nunca me llegó» y «se rechazó»). Reenviar la deja
  en `pendiente`, le borra el motivo del fallo anterior y despacha `EnviarAlerta` de nuevo. La empresa
  viaja por separado en la dirección (`/proveedor/alertas/{empresa}/{alerta}/reenviar`), como en el
  propio job: fuera de su contexto la alerta no se puede buscar, y pedir la de una empresa con el
  identificador de otra responde 404.
- **La vista de entregabilidad cuenta entregadas contra fallidas, no contra rebotadas.** El documento
  pide el porcentaje frente a rebotados, pero «rebotada» es un estado que hoy nadie escribe: no hay
  proveedor de correo transaccional conectado (F3-06b) y sin él nunca llega el aviso de rebote a la
  aplicación. La pantalla lo dice con esas palabras —«todavía no se puede medir»— en vez de fingir una
  cifra que no se mide. Es la misma disciplina que F3-04 con el margen de tolerancia del tope: no
  inventar un umbral o un estado que el documento no puede darse el lujo de fijar todavía.
- **`SeccionDelPanel::pendientes()` perdió la sección «Alertas»**; quedan Salud y Equipo.
- Pruebas nuevas en `ConsolaDeAlertasTest.php` (cruce de empresas, los cuatro filtros, el orden por lo
  más reciente, la forma de paginación, las tres del reenvío, las dos de entregabilidad y la puerta del
  panel) y en `Alertas.test.js` (las cifras, el filtro por empresa, el detalle, el reenvío condicionado
  a `puedeReenviarse`).

## Decisiones tomadas

- **Cruzar empresas se hace recorriéndolas una por una, nunca con una consulta global.** No es una
  preferencia de estilo: la política de RLS de `alertas_enviadas` falla cerrada sin una empresa fijada
  en la sesión, así que la única forma de leer de todas es entrar en el contexto de cada una. El
  patrón ya existía (`ProcesoDiarioDeAlertas`, `ListadoDeEmpresas::empleadosDe()`); esta tarea lo
  extiende a un listado paginado y ordenable, que es la parte nueva.
- **`ConsolaDeAlertas` no extiende `App\Listados\Listado`.** `Listado` asume una sola consulta
  (`Eloquent\Builder`) a la que pedirle filtro, orden y paginación; aquí no hay una sola consulta
  posible. Forzar la herencia habría significado sobreescribir casi todos sus métodos, que es la señal
  de que el contrato no es el correcto. La clase nueva copia la forma de sus props y tres de sus
  helpers de filtro, con la razón escrita en el comentario de cada uno.
- **El reenvío se ofrece para «enviada» y «fallida», no solo para «fallida».** El propio motivo de la
  consola —«un cliente dice que nunca le llegó un aviso»— es exactamente el caso de una alerta que
  quedó como enviada. Restringirlo a «fallida» habría dejado sin resolver la conversación que el
  capítulo 7 dice que existe para ganar.
- **La entregabilidad no inventa la cifra de rebote.** Ver arriba.

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

- **La escalera de mora sigue siendo tuya**, en `config/panel.php`, clave `cobro.escalera_de_mora`.
- **La tabla de precios (A.1) sigue sin decidir** y el catálogo de planes nace vacío.
- **Un contrato a término fijo cargado con prórrogas anteriores arranca contando cero prórrogas.**
- **La plantilla 1 sigue sin país de expedición del documento.**
- **`REV-06` sigue tuya: acotar la regla de «la fecha entre paréntesis».**
- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Hasta que eso se haga, ningún correo sale de la máquina
  **y la vista de entregabilidad de F5-05 sigue sin poder medir rebotes**, que es la primera
  consecuencia visible de esa tarea bloqueada.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F5-06, salud del sistema y métricas de negocio** (documento 4, capítulos 3 y
  9): el estado del proceso diario de alertas, la cola, los errores de aplicación, las copias de
  seguridad, y las cifras de negocio del inicio del panel —empresas activas, ingreso recurrente,
  clientes en riesgo—. `ResumenDelProceso` (F3-01) ya trae parte de lo que corrió hoy; `PanelDeSuscripciones`
  ya trae el ingreso recurrente; lo nuevo es dónde se guarda el historial de ejecuciones del proceso
  diario y de dónde salen los errores de aplicación y las copias de seguridad, que no tienen tabla
  todavía.
- **La pestaña «Alertas» de la ficha de empresa sigue declarada, no construida** (`FichaDeEmpresa::PENDIENTES`).
  F5-05 construyó la consola general, no esa pestaña puntual; reutilizarla ahí es sencillo —es la misma
  `ConsolaDeAlertas` filtrada a una sola empresa— pero es trabajo de otra tarea, no de F5-05: el
  capítulo 4.3 no es la fuente de esta tarea y ampliar el alcance a mitad de sesión es justo lo que las
  reglas de trabajo piden evitar.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
