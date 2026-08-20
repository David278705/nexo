# Estado del proyecto

**Última sesión:** 2026-08-19 · Tarea F1-09
**Verificación:** verde
**Siguiente tarea:** F1-10 · Validaciones de prórroga

## Qué se hizo

- **`App\Contratacion\RenovacionDeContrato`**, el objeto nuevo de la sesión: responde qué pasaría si
  se guardara una prórroga con esa fecha y ese tipo —cuánto dura, en cuánto queda el vínculo
  acumulado, qué número de prórroga sería y cuántos cupos cortos quedarían—. Devuelve el texto ya
  escrito y su tono. También decide si la pantalla ofrece la acción y desde qué día corre el tramo
  nuevo, que es el día siguiente al vencimiento vigente.
- **`Vinculacion::renovar()`** y, debajo, **`registrarPeriodo()` ahora mueve también `tipo_contrato` y
  `fecha_fin` de la vinculación**, en la misma transacción que crea el tramo. Era la nota que dejó
  F1-08: el historial y lo vigente se mueven a la vez o quedan desalineados. Solo guarda si algo
  cambió, así que el periodo inicial que abre el observador no ensucia la auditoría con una edición
  que nadie hizo.
- **`RenovarContrato`**, la validación: solo término fijo (403 en lo demás), el tipo de prórroga
  obligatorio y solo las dos que existen, y la fecha posterior al vencimiento vigente con el mensaje
  que dice qué hacer si lo que había era un error de digitación.
- **`ModalDeRenovacion`** y el botón «Renovar contrato» en el detalle. El modal no cuenta ni un día:
  al cambiar la fecha o el tipo pide al servidor solo la prop `renovacion` con una recarga parcial de
  Inertia, y pinta las líneas que llegan.
- **`HistorialDeContrato` aprendió a contar cupos cortos**: `contratoInicialEsInferiorAUnAnio()`,
  `prorrogasCortas()` y `prorrogasCortasRestantes()`, que es el insumo exacto de F1-10.
  `PeriodoContractual::esInferiorAUnAnio()` es la única definición de «prórroga corta» del sistema.
- **`RenovacionTest` con 33 casos** —la fila nueva, que editar la fecha no la crea, el día de inicio,
  el vencimiento movido, los dos tipos, la auditoría, las cuatro validaciones, los 403, las tres
  cuentas, los cupos por tipo, el aviso del tope— más un caso de aislamiento por la ruta real de
  renovar y 10 de Vitest.
- `composer verificar` en verde: 376 pruebas de Pest, PHPStan sin errores, build y 152 de Vitest.

## Decisiones tomadas

- **D.1 se resolvió como la propone el documento 6: el modal pregunta el tipo de prórroga.** No fue
  una invención: el documento 3, sección 5.4, ya lista «el tipo de prórroga» entre los campos del
  modal, el prototipo lo enseña con las dos opciones literales, y el propio enunciado de F1-09 pide
  registrarlo. La alternativa de aplicar siempre la regla más estricta habría avisado una prórroga
  antes a quien sí formaliza por escrito.
- **El tramo nuevo empieza el día siguiente al vencimiento vigente.** Es lo que hace el prototipo
  —tramo que termina el 14/04 y siguiente que empieza el 15/04— y es lo correcto: una prórroga
  continúa el mismo contrato, así que no puede contar un día dos veces ni dejar un día sin cubrir.
- **El límite de prórrogas cortas cuenta solo las prórrogas cortas, y por tipo.** Una prórroga de dos
  años no gasta ninguno de los cuatro cupos. «Corta» es una sola cosa en todo el sistema: que el
  tramo dure menos de un año, contando los dos extremos.
- **El tope de cuatro años y el cupo corto se advierten, no bloquean.** Es el reparto del backlog:
  F1-09 calcula y enseña, F1-10 bloquea. Las pruebas dicen expresamente que hoy se permite guardar,
  así que cuando F1-10 lo convierta en bloqueo, esas expectativas cambian a propósito y no por
  descuido.
- **Las cuentas del modal se calculan en el servidor y no en el navegador.** Repetirlas en Vue habría
  sido más rápido de escribir y se habría separado de la versión del servidor a la primera
  corrección; el número que el usuario lee tiene que ser el mismo que se va a guardar.
- **El aviso proyectado es ámbar cuando hay una consecuencia que la empresa debe conocer** —se pasa
  del tope o se gasta el último cupo corto— y azul cuando la prórroga no tiene ninguna. El rojo queda
  para F1-10, cuando algo esté mal de verdad porque no se pueda guardar.
- **Renovar es una dirección propia con verbo propio**, `POST /contratos/{contrato}/renovar`, y no un
  campo más de un formulario de edición. Es la exigencia de la sección 5.5: que una acción no se pueda
  usar en lugar de la otra.

## Preguntas para Angela

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
- **B.1 se vuelve bloqueante en la próxima tarea.** F1-10 convierte en bloqueo el límite de prórrogas
  cortas, y el documento 6 pide confirmar su aplicación práctica con el asesor laboral antes de
  impedir un guardado: **si el sistema bloquea algo que en realidad es legal, el cliente no puede
  operar**. Hoy está construido como advertencia, que es exactamente lo que el documento propone
  mientras llega la confirmación.
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años.

## Decisiones para David

- **B.3, ¿bloquear o advertir?, es la decisión que abre F1-10.** La sugerencia del documento 6 es
  bloquear el tope de cuatro años —pasado el tope el contrato **es** indefinido, no puede ser otra
  cosa— y advertir con fuerza, permitiendo guardar, en la prórroga corta después del límite, porque
  ahí sí puede haber un contrato ya firmado que haya que registrar. Los dos textos ya están escritos
  en `RenovacionDeContrato` y hoy salen como advertencia; convertir el primero en bloqueo es agregar
  una regla a `RenovarContrato`.
- **El detalle del contrato todavía no tiene «Cambiar a término indefinido».** Es F1-11 y el sitio
  está libre, al lado del botón de renovar, que ya existe. El modal de esa acción es el mismo
  componente `Modal` y el aviso del tope ya nombra la acción por su nombre.
- **Tampoco tiene «Editar contrato».** El aviso del modal de renovación dice «edita el contrato» sin
  enlazar a ninguna parte, porque esa pantalla no existe. Cuando exista, el aviso lleva un enlace.
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
- Abierta de veinte sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide 8.3+**.
  `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-10 · Validaciones de prórroga**, y casi todo su insumo está construido:
  `HistorialDeContrato::superaElTopeLegal($fechaFin)` y `prorrogasCortasRestantes($tipo)` responden
  las dos preguntas, y `RenovacionDeContrato` ya escribe los dos mensajes con las palabras del
  prototipo. **Lo único que F1-10 agrega es la regla en `RenovarContrato`** y el cambio de tono a
  rojo en el aviso del modal.
- **El backlog pide que las dos validaciones sean «configurables por estado».** No está dicho en el
  documento 1 ni en el 3 qué significa eso exactamente —¿un parámetro de empresa, como los cuatro de
  F1-12?—. Si al leer la tarea no queda claro, es una pregunta para David antes de inventar un
  parámetro.
- **`RenovacionDeContrato` es el sitio de cualquier cuenta sobre una prórroga propuesta**, igual que
  `VigenciaDelContrato` lo es de las cuentas sobre un contrato existente. No escribas una tercera.
- **`registrarPeriodo()` ya deja el historial y lo vigente alineados**, así que F1-11 solo tiene que
  llamarlo con `EventoContractual::CambioAIndefinido` y `TipoContrato::Indefinido`, sin fecha de fin.
  La fecha de ingreso no se toca, que es el criterio de terminado de esa tarea.
- **El helper `enLaEmpresa()` de las pruebas vive en `PersonasYVinculacionesTest.php`** y Pest lo
  comparte con todos los archivos. Sin él, cualquier consulta hecha desde una prueba fuera de una
  petición HTTP devuelve vacío, porque el global scope falla cerrado.
- **La tabla del historial sigue sin las columnas «Duración» y «Registrado» del prototipo.** La arma
  `FichaDeEmpleado::filasDelHistorial()` y la enseñan las dos pantallas, así que agregarlas es un solo
  sitio. No hacía falta para F1-09.
- **`OpcionesDeLista` sigue solo en los cuatro enumerados de F1-06.** `Genero`, `TipoDocumento`,
  `TipoContrato`, `TipoRiesgo`, `TipoVivienda`, `EstadoVinculacion` y `EventoContractual` mantienen
  sus métodos escritos a mano. Unificarlos es un rato y no rompe nada.
- **No queda ninguna tarea bloqueada en el backlog.**
