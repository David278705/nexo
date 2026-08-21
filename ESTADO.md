# Estado del proyecto

**Última sesión:** 2026-08-20 · Tarea F1-11
**Verificación:** verde
**Siguiente tarea:** F1-12 · Parámetros de la empresa

## Qué se hizo

- **`CambioAIndefinido`**, el objeto de la acción. Responde solo dos cosas: **si la acción existe en
  ese contrato** —solo el término fijo, documento 1, 5.5— y **qué implica el cambio**. No escribe nada
  y no cuenta nada del historial.
- **Las cinco frases del prototipo se escriben en el servidor**, no en el componente. Cada una afirma
  algo que el sistema después cumple, y la misma prueba que comprueba el efecto lee el texto. En el
  navegador, la pantalla podría prometer una cosa y el servidor hacer otra.
- **`Vinculacion::cambiarATerminoIndefinido()`** pasa por `registrarPeriodo()` como cualquier otro
  hecho contractual: una fila nueva en el historial y lo vigente movido en la misma transacción.
- **El tramo nuevo no lleva fecha de fin.** Un indefinido no vence, así que `fecha_fin` de la
  vinculación queda en nulo y con ella desaparecen el vencimiento, el límite de preaviso y las alertas
  de renovación. El contrato sale del listado de los que vencen, que es lo que se prueba.
- **La fecha de ingreso no se toca, y el tramo inicial tampoco.** Es el criterio de terminado: la
  antigüedad de la ficha da lo mismo antes y después del cambio. Reescribir el tramo inicial borraría
  la trazabilidad que el cambio pretende dejar.
- **`CambiarATerminoIndefinido` es una petición sin reglas de datos**, porque no llega ninguno: la
  única regla está en `authorize()` y responde 403 en indefinido y en obra o labor. Después del cambio
  el propio contrato deja de admitirlo, así que el inverso está cerrado por el mismo camino.
- **`ModalDeCambioAIndefinido`** es una confirmación sin ni un campo: la entrada, las implicaciones y
  la advertencia llegan como props. El botón «Cambiar a término indefinido» va a la izquierda del de
  renovar, en secundario, como en el prototipo.
- `CambioAIndefinidoTest` con 16 casos: cuándo se ofrece, qué enseña el modal, el efecto en el
  historial, en lo vigente, en la antigüedad, en el detalle, en el listado y en la auditoría, y el
  aislamiento entre empresas. Seis casos de Vitest del modal y dos de la pantalla.
- `composer verificar` en verde: Pint, PHPStan sin errores, 413 pruebas de Pest, build y 162 de Vitest.

## Decisiones tomadas

- **El tramo indefinido empieza el día en que se hace el cambio**, no en la fecha de ingreso ni en el
  vencimiento del tramo anterior. Lo pide el documento 1, 5.5: el cambio «queda registrado con la
  fecha en que se hizo, para efectos de trazabilidad y reportes». Un contrato se vuelve indefinido
  desde que las partes lo acuerdan, no desde que habría vencido.
- **El único ajuste a esa fecha es de coherencia del historial, no de derecho laboral**: en un
  contrato que todavía no ha empezado, el tramo nuevo arranca el mismo día que el vigente. Un tramo
  que empezara antes que el anterior deja el historial en un orden imposible de leer.
- **La confirmación explica la consecuencia calculada y no pregunta «¿está seguro?»**, que es la regla
  del documento 3, sección 6.2, para lo irreversible. Por eso el modal nombra la fecha de ingreso y la
  fecha del cambio con sus valores, y no una frase genérica.
- **La disponibilidad se lee del historial y no de la columna de la vinculación.** Es la misma fuente
  que usa `RenovacionDeContrato::disponible()`, y la que decide qué tipo de contrato está vigente
  ahora. Dos fuentes para la misma pregunta se separan a la primera corrección.
- **El botón de renovar y el de cambiar a indefinido leen banderas distintas.** Las dos exigen término
  fijo, pero renovar exige además un vencimiento del que colgar la prórroga. Una sola bandera para las
  dos habría pintado un botón que no lleva a ninguna parte.
- **La acción no valida el tope de cuatro años ni nada parecido.** Es voluntaria y también es la salida
  del bloqueo de F1-10 (documento 1, 5.5): pasar a indefinido nunca puede ser lo que se impida.

## Preguntas para Angela

- **El documento 1 y el documento 6 no dicen lo mismo sobre la prórroga mínima.** La sección 5.5 del
  documento 1 dice que **las dos** validaciones bloquean —«no son advertencias»— y la tabla 5.4 del
  documento 3 la sigue; B.1 y B.3 del documento 6, y el propio enunciado de F1-10, piden advertir con
  fuerza en la prórroga corta hasta que el asesor laboral confirme la aplicación práctica. **Se
  siguió el documento 6.** **La pregunta concreta: ¿se confirma esa lectura y se corrige la sección
  5.5, o la prórroga mínima también debe bloquear?** Si es lo segundo, es cambiar una palabra en
  `config/validaciones.php`.
- **La plantilla de carga y el formulario ya no dicen lo mismo.** `plantillas/2 - Perfil
  sociodemografico (opcional).xlsx` ofrece «Primaria · Bachillerato · Técnico · Tecnólogo ·
  Profesional · Posgrado» y los rangos «Menos de 30 min · De 30 min a 1 hora · De 1 a 2 horas · Más
  de 2 horas»; el formulario quedó con las del archivo de listas, que son otras. **¿Se corrige la
  plantilla para que coincida con el formulario?** Es lo que se recomienda: dos vocabularios para el
  mismo campo obligarían a traducir en la carga inicial. Bloquea F5-01, no antes.
- **La columna «Cesantías» del archivo de listas sigue sin dónde ir.** Trae Porvenir, Protección,
  Colfondos, Skandia, el Fondo Nacional del Ahorro y «Otro», pero **el fondo de cesantías no aparece
  en la sección 5.1 de la especificación funcional**. La pregunta concreta: **¿se agrega «fondo de
  cesantías» como campo obligatorio del formulario de empleado?** Si la respuesta es sí, es una tarea
  nueva —tabla, columna, seeder, un campo en el paso 2 del asistente y un renglón en la ficha, que ya
  están construidos—.
- Menor: nivel educativo quedó con **«Técnico / Tecnólogo» unidos** y **«Posgraduado(a)» sin
  distinguir especialización, maestría y doctorado**, tal como los pediste.
- **B.1 sigue abierta y ahora tiene consecuencia visible.** El límite de prórrogas cortas advierte y
  no bloquea porque así lo pide ese punto. **Confirmada la regla con el asesor laboral, pasa a
  bloquear con una palabra.**
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años.

## Decisiones para David

- **La salida que nombraba el bloqueo del tope ya existe y se puede pulsar.** El mensaje de F1-10 dice
  «Cambiar a término indefinido» y ahora hay un botón con ese texto exacto al lado del de renovar.
- **Sigue sin existir «Editar contrato».** El aviso del modal de renovación dice «edita el contrato»
  sin enlazar a ninguna parte. Cuando exista, el aviso lleva un enlace.
- **B.3 quedó resuelta como la sugiere el documento 6**: bloquear el tope de cuatro años, advertir con
  fuerza la prórroga corta. Los dos estados son configurables en `config/validaciones.php`.
- **El texto del hueco del saldo de vacaciones se lee en pantalla y sigue siendo tuyo.** Está en
  `FichaDeEmpleado::saldoDeVacaciones()`, en un solo sitio.
- **La fecha de salida no existe todavía.** `Vinculacion::fechaDeSalida()` devuelve `fecha_fin` de una
  vinculación inactiva. De ella dependen el énfasis de los 30 días de la advertencia de reingreso y
  la antigüedad de quien ya salió. Está anotado en F2-09.
- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Lo advierte la propia hoja «Notas y fuentes» del archivo: hay nueve EPS intervenidas por la
  Supersalud y el sector está en movimiento. **Es una lista global: si está mal, está mal para todos
  los clientes a la vez.**
- **Verificar el calendario de festivos antes de vender.** Cincuenta y tres fechas, quince minutos,
  mismo riesgo global que las EPS.
- **Contrastar los 1.101 municipios contra el archivo del DANE.** Ese mismo cotejo trae los códigos
  DANE que hoy están nulos.
- **Vigencia del enlace de invitación: siete días.** Abierta desde F0-07, en
  `Invitaciones::DIAS_DE_VIGENCIA`. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`.
- Abierta de veintidós sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-12 · Parámetros de la empresa**: la pantalla con los cuatro parámetros del
  capítulo 4 del documento 1 y su explicación en lenguaje claro. **Dos de ellos ya se leen desde el
  código construido** —el perfil sociodemográfico opcional (F1-06) y lo que consulte el motor de
  alertas—, así que la pantalla es sobre todo darles un sitio donde cambiarlos, no inventarlos. El
  aviso al activar el perfil sociodemográfico es el que ya explica la ficha: los empleados que ya
  existían quedan en blanco y eso no es un error.
- **`CambioAIndefinido` es el sitio de cualquier cosa sobre el cambio de modalidad**,
  `RenovacionDeContrato` el de una prórroga propuesta y `VigenciaDelContrato` el de las cuentas sobre
  un contrato existente. No escribas una cuarta.
- **Las frases que la pantalla promete se escriben donde vive el efecto.** Si una confirmación afirma
  algo que el servidor hace, el texto va en el servidor: así una prueba puede comprobar las dos cosas
  a la vez.
- **Toda regla que dependa de una decisión abierta va a `config/validaciones.php`**, con su estado y
  el punto del documento 6 que la sostiene. No se escriben ramas «por ahora advierte» dentro de la
  lógica.
- **El helper `enLaEmpresa()` de las pruebas vive en `PersonasYVinculacionesTest.php`** y Pest lo
  comparte con todos los archivos. Sin él, cualquier consulta hecha desde una prueba fuera de una
  petición HTTP devuelve vacío, porque el global scope falla cerrado.
- **La tabla del historial sigue sin las columnas «Duración» y «Registrado» del prototipo.** La arma
  `FichaDeEmpleado::filasDelHistorial()` y la enseñan las dos pantallas, así que agregarlas es un solo
  sitio.
- **`OpcionesDeLista` sigue solo en los cuatro enumerados de F1-06.** `Genero`, `TipoDocumento`,
  `TipoContrato`, `TipoRiesgo`, `TipoVivienda`, `EstadoVinculacion` y `EventoContractual` mantienen
  sus métodos escritos a mano. Unificarlos es un rato y no rompe nada.
- **No queda ninguna tarea bloqueada en el backlog.**
