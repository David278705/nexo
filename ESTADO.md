# Estado del proyecto

**Última sesión:** 2026-08-19 · Tarea F1-10
**Verificación:** verde
**Siguiente tarea:** F1-11 · Cambiar a término indefinido

## Qué se hizo

- **`RenovacionDeContrato::hallazgos()`**, el sitio único de las dos validaciones de 5.5: el tope de
  cuatro años y la regla de prórroga mínima. Cada una escribe **un solo mensaje**, y ese mismo texto
  es el que se lee en el aviso del modal y el que sale como error del servidor. Un modal que dice una
  cosa y un servidor que dice otra son dos reglas, y una de las dos está mal.
- **`impedimentos()`** es lo que consulta la validación: devuelve los mensajes de las reglas que sí
  impiden guardar, y vacío si falta un dato o si la regla está configurada como advertencia.
- **`config/validaciones.php` y el enumerado `EstadoDeValidacion`**, que es la lectura de
  «configurables por estado»: el estado de cada validación —`bloquea` o `advierte`— es una palabra de
  la configuración, no una rama escrita dentro de la lógica. El enumerado decide también el color,
  porque son la misma decisión: rojo y bloqueado son lo mismo por definición.
- **El tope de cuatro años bloquea.** El mensaje dice que no se puede registrar, por qué —pasado el
  tope el contrato es indefinido por ley— y nombra la salida: «Cambiar a término indefinido». La
  fecha que llega justo al tope sí se registra.
- **La prórroga mínima advierte con fuerza y permite guardar**, y su mensaje añade que si la prórroga
  ya está firmada puede registrarse y queda documentada en la auditoría. Con una palabra de la
  configuración se vuelve bloqueo, y el mensaje cambia solo.
- **`RenovarContrato::after()`** pregunta las dos reglas **después** de las de formato y solo si
  pasaron: una fecha que no es fecha no supera ningún tope, y un segundo error sobre `fecha_fin`
  escondería el primero.
- **El modal deshabilita «Registrar prórroga»** cuando la proyección llega con `bloqueado`, que es lo
  que hace el prototipo. `Boton` tiene ahora la prop `deshabilitado`, separada de `enviando`.
- `RenovacionTest` creció con dos bloques —seis casos del tope y cinco de la prórroga mínima, cada
  regla probada en su estado configurado **y en el contrario**— y se reescribió el caso que antes
  permitía pasarse del tope. `EstadoDeValidacionTest` cubre el enumerado y los dos valores de fábrica.
  Dos casos de Vitest más: el botón deshabilitado con la proyección bloqueada y habilitado cuando solo
  advierte.
- `composer verificar` en verde: 396 pruebas de Pest, PHPStan sin errores, build y 154 de Vitest.

## Decisiones tomadas

- **«Las dos configurables por estado» se leyó como el estado de cada validación: bloquea o
  advierte.** Es lo que hace que la promesa de B.1 —«convertir la advertencia en bloqueo es un cambio
  de una línea»— sea cierta de verdad. La alternativa, un parámetro por empresa, se descartó: el
  capítulo 4 del documento 1 lista los parámetros que el cliente configura y este no está, y dejar
  que una empresa desactive un bloqueo legal es exactamente lo que el bloqueo existe para evitar.
- **El estado vive en `config/validaciones.php` y no en `reglas_laborales.php`.** Ese archivo son
  constantes legales —números y fechas de una norma— y estas dos palabras son una decisión de
  producto sobre cómo se aplica la norma. Además hay una prueba que cuenta las diez constantes
  legales, y sumarle valores que no lo son la volvería una prueba que no dice nada.
- **Una configuración equivocada bloquea.** Si el valor no es ninguno de los dos, `EstadoDeValidacion`
  devuelve `Bloquea`: ante una configuración mal escrita, no registrar algo que puede ser ilegal es la
  opción segura.
- **El mensaje cambia con el estado, no solo el color.** Un bloqueo empieza por «No se puede registrar
  esta prórroga» y una advertencia dice la consecuencia a secas. Decirle a alguien que no puede
  guardar y luego dejarlo guardar es peor que no avisarle.
- **«Gasta el último cupo corto» sigue siendo ámbar y no es un hallazgo.** Todavía no incumple nada
  —la prórroga cabe— pero la siguiente ya no podrá ser corta. Es la distinción que separa lo que hay
  que saber de lo que no se puede hacer.
- **El bloqueo se comprueba en el servidor y el botón deshabilitado es cortesía.** El modal no sabe
  qué es un tope legal: recibe la bandera y obedece. Enviar el formulario a mano vuelve a fallar.

## Preguntas para Angela

- **El documento 1 y el documento 6 no dicen lo mismo sobre la prórroga mínima.** La sección 5.5 del
  documento 1 dice que **las dos** validaciones bloquean —«no son advertencias»— y la tabla 5.4 del
  documento 3 la sigue; B.1 y B.3 del documento 6, y el propio enunciado de F1-10, piden advertir con
  fuerza en la prórroga corta hasta que el asesor laboral confirme la aplicación práctica. **Se
  siguió el documento 6**, porque es el que registra lo que aún no está cerrado y porque bloquear una
  prórroga que en realidad es legal deja al cliente sin dónde registrar un contrato ya firmado. **La
  pregunta concreta: ¿se confirma esa lectura y se corrige la sección 5.5, o la prórroga mínima
  también debe bloquear?** Si es lo segundo, es cambiar una palabra en `config/validaciones.php`.
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
  distinguir especialización, maestría y doctorado**, tal como los pediste. Separarlos después es
  agregar casos al enumerado y nada más.
- **D.1 ya no bloquea.** El modal pregunta el tipo de prórroga, con los textos del prototipo:
  «Pactada por escrito» y «Automática por silencio». Si prefieres otro par de textos, se cambian en
  `EventoContractual::prorrogasParaElegir()`.
- **B.1 sigue abierta y ahora tiene consecuencia visible.** El límite de prórrogas cortas advierte y
  no bloquea porque así lo pide ese punto. **Confirmada la regla con el asesor laboral, pasa a
  bloquear con una palabra.**
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años.

## Decisiones para David

- **B.3 quedó resuelta como la sugiere el documento 6**: bloquear el tope de cuatro años, advertir con
  fuerza la prórroga corta. Está construido así y los dos estados son configurables, así que cambiar
  de opinión no cuesta código. **Confírmalo o cámbialo en `config/validaciones.php`.**
- **El mensaje del tope nombra «Cambiar a término indefinido» y esa acción todavía no existe.** Es
  F1-11, la siguiente tarea, y el sitio está libre al lado del botón de renovar. Hasta entonces el
  texto nombra una salida que el usuario no puede pulsar.
- **Tampoco existe «Editar contrato».** El aviso del modal dice «edita el contrato» sin enlazar a
  ninguna parte. Cuando exista, el aviso lleva un enlace.
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
- Abierta de veintiuna sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-11 · Cambiar a término indefinido**, y es la salida que el mensaje del tope ya
  nombra por su nombre. Casi todo está: `registrarPeriodo()` deja el historial y lo vigente alineados
  en la misma transacción, así que basta llamarlo con `EventoContractual::CambioAIndefinido` y
  `TipoContrato::Indefinido` **sin fecha de fin**. La fecha de ingreso no se toca, que es el criterio
  de terminado. El modal es el mismo componente `Modal` y el botón va al lado del de renovar.
- **Toda regla que dependa de una decisión abierta va a `config/validaciones.php`**, con su estado y
  el punto del documento 6 que la sostiene. No se escriben ramas «por ahora advierte» dentro de la
  lógica.
- **`RenovacionDeContrato` es el sitio de cualquier cuenta o regla sobre una prórroga propuesta**, y
  `VigenciaDelContrato` el de las cuentas sobre un contrato existente. No escribas una tercera.
- **Los mensajes de las reglas se escriben una vez.** Si una validación nueva necesita un texto para
  la pantalla y otro para el error, es que están en dos sitios y van a divergir.
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
