# Estado del proyecto

**Última sesión:** 2026-08-20 · Tarea F1-13 (punto de control, skill `revisar`)
**Verificación:** verde
**Siguiente tarea:** F1B-01 · Base del panel del proveedor

## Qué se hizo

- **La revisión completa de la fase 1**, con el informe en `bitacora/revision-2026-08-20.md`. Se
  recorrieron los cinco frentes del skill: aislamiento, motor de cálculo, interfaz, seguridad y datos,
  y deuda.
- **Aislamiento, interfaz, seguridad y datos: sin hallazgos.** Un solo `where('tenant_id', …)` a mano
  en todo el código —el de `Auditoria`, justificado y documentado—, las cinco tablas de negocio con
  trait, política RLS y caso de aislamiento, ni un color literal en un componente, ni un borrado
  físico, toda ruta bajo `auth` y `modulo:`, y la auditoría escrita en un solo sitio por el observador.
- **Un hallazgo de deuda, arreglado**: `fecha()` estaba copiada palabra por palabra en **cinco**
  clases y la pluralización de días en **cinco** sitios. Ahora existe **`App\Presentacion\Formato`**
  con `fecha()`, `fechaLarga()`, `dias()`, `dinero()` y la constante `VACIO`; los diez sitios que
  formateaban `d/m/Y` a mano la llaman, se borraron cinco métodos privados y quedó
  `tests/Unit/FormatoTest.php` con nueve casos.
- **Dos tareas nuevas**, `REV-01` y `REV-02`: el armazón de los listados está escrito dos veces, en el
  servidor y en la interfaz. No se extrajo ahora a propósito (ver decisiones).
- **Una nota añadida a F2-02**: los dos conteos de días corridos escritos a mano —
  `HistorialDeContrato::duracionTotalEnDias()` y `RenovacionDeContrato::diasCorridos()`— pasan a
  llamar al motor cuando exista, y la tarea no se da por terminada dejándolos.
- `composer verificar` en verde: Pint, PHPStan sin errores, **448** pruebas de Pest (nueve más), build
  y 171 de Vitest.

## Decisiones tomadas

- **Se arregló la duplicación pequeña y se convirtió en tarea la grande.** Los formateadores eran
  copias idénticas de tres líneas: extraerlos es mecánico y las pruebas existentes lo comprueban sin
  tocarlas. El armazón de listado es un refactor de estructura, y un armazón diseñado con dos ejemplos
  y estrenado con el tercero sale mejor que uno inventado hoy sobre los mismos dos. **`REV-01` va
  antes de F2-04**, que es el primer listado nuevo.
- **`Formato` vive en `App\Presentacion` y no en `App\Contratacion`.** Los cinco duplicados estaban
  todos en contratación por casualidad —es el único módulo construido—; las novedades de la fase 2
  van a pedir las mismas fechas y el mismo dinero.
- **`Formato::VACIO` es una constante y no la raya escrita a mano.** Aparece en una docena de sitios
  como valor de un dato que no existe, y la raya larga y el guión corto se confunden a la vista.
- **`Formato` no cuenta ni un día.** Es presentación: convierte una cuenta ya hecha en un texto. El
  conteo es del motor de cálculo, y meter `diasCorridos()` aquí habría sido crear un segundo motor con
  otro nombre.
- **La aritmética de fechas que no es regla laboral se queda donde está.** Antigüedad, vigencia del
  enlace de invitación y los rangos de los filtros son distancias de calendario, no reglas del CST, y
  cada clase explica por qué no vive en el motor. Solo los dos conteos de días corridos se mueven.

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

- **La regla de «la fecha entre paréntesis» está escrita más ancha de lo que la sostiene el
  documento, y hay que acotarla o cumplirla.** `.claude/rules/30-interfaz.md` la enuncia para toda la
  interfaz; su fuente —documento 1, sección 9.1— la enuncia **solo para los mensajes de las alertas**.
  En pantalla no se cumple del todo y el prototipo tampoco la cumple: el encabezado del detalle del
  contrato dice «Vence en 26 días» sin fecha, igual que `prototipos/sistema.html` línea 449. **No se
  cambió nada**, porque sería contradecir el prototipo por una lectura mía de una regla derivada.
  **Se recomienda acotar la regla del archivo a los correos** y dejar para pantalla la versión débil:
  una referencia relativa necesita su fecha absoluta visible, en el mismo sitio o al lado. **En la
  fase 3 deja de ser cosmético**: en los correos la regla es literal y no hay ninguna columna al lado.
- **`User`, `Acceso` e `Invitacion` no llevan `SoftDeletes`.** Hoy no importa, porque nada los borra.
  Importará cuando exista «Usuarios y permisos»: dar de baja a un usuario tiene que ser lógico, o se
  pierde de quién eran las líneas de auditoría que él escribió.
- **Los parámetros de cálculo todavía no tienen quien los lea.** Sábado laboral y efecto de las
  suspensiones ya se guardan y ya se pueden cambiar, pero **el único consumidor construido es el del
  perfil sociodemográfico**: el motor que los usará es F2-02.
- **El menú del cliente no tiene «Usuarios y permisos» ni «Facturación» todavía.** Son dos de las
  cuatro pestañas de Configuración y **ninguna tiene tarea en el backlog**. Crear usuarios se puede
  hacer hoy solo desde el panel del proveedor (F1B-02). Si el cliente debe poder crear a sus propios
  administradores antes de vender, es una tarea nueva.
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
- Abierta de veinticuatro sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1B-01, la base del panel del proveedor**: aplicación separada bajo su propia
  ruta, solo para el rol `owner`, con segundo factor obligatorio. Antes de construirla, abre
  `prototipos/panel-proveedor.html` y lee el capítulo 2 del documento 4.
- **`App\Presentacion\Formato` es el único sitio donde se escribe una fecha, un número de días o un
  peso.** No escribas `format('d/m/Y')` en ninguna parte; si hace falta un formato nuevo, se agrega
  ahí con su caso en `FormatoTest`.
- **`REV-01` va antes de F2-04.** Es el armazón de listado del servidor, y F2-04 es el primer listado
  nuevo: hacerlo después significa copiar `opcion()` una tercera vez.
- **Todo parámetro nuevo de la empresa se agrega en `ParametrosDeLaEmpresa::CAMPOS`** y en las cuatro
  tablas de textos de ese archivo. La pantalla, la validación y el guardado lo recogen solos; lo único
  que hace falta aparte es la columna.
- **Los parámetros se leen de `tenant_actual()`, nunca de una copia.** Es lo que hace cierta la
  promesa de que un cambio se refleja de inmediato.
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
- **No queda ninguna tarea bloqueada en el backlog.** Sí quedan dos `REV-` al final.
