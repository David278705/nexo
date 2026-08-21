# Estado del proyecto

**Última sesión:** 2026-08-20 · Tarea F1B-01 · Base del panel del proveedor
**Verificación:** verde
**Siguiente tarea:** F1B-02 · Crear empresa

## Qué se hizo

- **El panel del proveedor está de pie**, bajo su propio prefijo `/proveedor`, con las **nueve
  secciones** del capítulo 2 del documento 4 (`SeccionDelPanel`), su barra lateral, su franja
  superior y una pantalla por sección. Trece rutas, todas con nombre `proveedor.*`.
- **Solo entra el rol `owner`.** El middleware `SoloProveedor` responde **403** a cualquier usuario de
  empresa, superadministrador incluido, en las nueve direcciones y también en las cuatro del segundo
  factor. Es el criterio de terminado de la tarea y tiene prueba.
- **Segundo factor obligatorio**, con TOTP escrito en el proyecto (`App\Autenticacion\SegundoFactor`,
  RFC 6238) y comprobado con los **vectores de prueba del propio RFC**. Quien no lo tiene activo no ve
  el panel: se le desvía a activarlo; quien lo tiene escribe su código una vez por sesión.
- El secreto se guarda **cifrado** en `users.segundo_factor_secreto` y la fecha de confirmación aparte,
  porque emitido no es activo.
- **Los intentos del código se cuentan aparte de los de la contraseña** (`LimitadorDeIntentos` ahora
  tiene ámbito). Compartirlos habría dejado un agujero real: entrar bien con la contraseña llama a
  `limpiar()` y habría borrado los fallos del código.
- **El `owner` que entra va al panel**, no al inicio del cliente, donde recibe 403 en todo.
- **Reutilización, no copia**: `Armazon` y `BarraLateral` sirven ahora a las dos aplicaciones con props
  de rótulo, secciones y tono. `ArmazonProveedor` son doce líneas. El resaltado del menú pasó a elegir
  **la coincidencia más específica**, que es lo que hace posible que la raíz del panel sea `/proveedor`
  y las demás secciones sean hijas suyas.
- `Formato::diaCompleto()` recoge la fecha del encabezado de las **dos** pantallas de inicio.
- `composer verificar` en verde: Pint, PHPStan sin errores, **489** pruebas de Pest (41 más), build y
  **177** de Vitest (6 más).

## Decisiones tomadas

- **El TOTP se escribió, no se trajo.** Setenta líneas contra una dependencia más en la puerta de
  entrada del sistema. El algoritmo no cambia y sus propios vectores de prueba lo comprueban. Si algún
  día hace falta un código QR o llaves de seguridad, ahí sí conviene una biblioteca.
- **No hay código QR.** Dibujarlo pediría una dependencia para el mismo dato que ya va en el enlace
  `otpauth://` y en la clave escrita en grupos de cuatro, que es lo que aceptan todas las
  aplicaciones.
- **El secreto se guarda al enseñarlo y no al confirmarlo.** Si se guardara al confirmar habría que
  llevarlo y traerlo por el formulario, y un secreto que vuelve desde el navegador es un secreto que
  el navegador puede cambiar. Lo que decide si está activo es la fecha de confirmación.
- **La verificación dura lo que dura la sesión.** No se inventó una caducidad de horas: el documento no
  la fija, y `session()->invalidate()` al salir la borra. Está en un solo sitio,
  `SegundoFactorDeLaSesion`, con el identificador del usuario dentro para que falle cerrado.
- **`proveedor` va antes que `segundo-factor` en la cadena.** Al contrario, un superadministrador
  recibiría un desvío a activar un segundo factor que no le corresponde en lugar del 403 que sí.
- **El panel no es un módulo de `PoliticaDeModulos`.** Esa política responde qué ve un usuario dentro
  de su empresa y su primera línea es que el `owner` no ve nada de eso. El panel es la pregunta
  simétrica y se contesta en su propio middleware.
- **Dentro del panel no hay permisos por sección.** Se entra a todo o no se entra. Los dos niveles
  internos del equipo —completo y soporte sin datos de empleados ni facturación— son del capítulo 10 y
  llegan con la sección «Equipo»; cuando existan, el filtro va en `SeccionDelPanel`.
- **El inicio del panel no enseña ni un número.** Las seis tarjetas de atención y los siete
  indicadores del capítulo 3 se alimentan de suscripciones, consola de envíos y métricas de uso, que
  no existen. Un indicador inventado en el panel del negocio es peor que un hueco declarado, porque
  ese panel se revisa cada mes para decidir con él.
- **La barra lateral del panel va en el azul oscuro de la marca**, como su prototipo. No es adorno:
  quien tiene las dos aplicaciones abiertas necesita distinguirlas de un vistazo.

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
- **Los planes de F1B-02 necesitan nombres y cifras.** El paso 3 de «crear empresa» pide nivel
  contratado, empleados y usuarios incluidos, y A.1 del documento 6 dice que la tabla de precios no
  existe todavía. **F1B-02 se puede construir con el plan como texto libre y los dos números a mano**;
  si se prefiere una tabla `planes` con niveles cerrados, hacen falta los niveles.

## Decisiones para David

- **La escalera de mora sigue sin números.** El capítulo 6 del documento 4 propone aviso a los 5 días,
  segundo aviso a los 15 y suspensión a los 30, y dice expresamente que «los números son una decisión
  de ustedes». Bloquea la sección «Suscripciones», que es de la fase 5, no F1B-02.
- **El panel no tiene todavía dónde enseñar un mensaje de confirmación.** El armazón del cliente
  tampoco: `Notificacion` existe como componente pero ningún armazón pinta la prop compartida
  `estado`. Por eso la activación del segundo factor no deja mensaje, solo lleva al panel. **Cuando
  F1B-02 confirme «empresa creada», hace falta resolverlo**, y conviene resolverlo en `Armazon`, una
  vez para las dos aplicaciones.
- **Un código de segundo factor fallido no queda en la tabla `accesos`.** Ahí solo queda el acceso con
  contraseña, que sí se registra. `ResultadoDeAcceso` tiene tres valores —exitoso, fallido,
  bloqueado— y meter el fallo del código como «fallido» diría que falló la contraseña. Si interesa el
  rastro, es un valor nuevo del enumerado.
- **No hay forma de reemitir un segundo factor perdido.** Hoy se arregla borrando las dos columnas del
  usuario en la base de datos. Con una sola cuenta de proveedor es aceptable; **con equipo, no**, y
  entra en la sección «Equipo» del capítulo 10.
- **La regla de «la fecha entre paréntesis» está escrita más ancha de lo que la sostiene el
  documento, y hay que acotarla o cumplirla.** `.claude/rules/30-interfaz.md` la enuncia para toda la
  interfaz; su fuente —documento 1, sección 9.1— la enuncia **solo para los mensajes de las alertas**.
  En pantalla no se cumple del todo y el prototipo tampoco la cumple: el encabezado del detalle del
  contrato dice «Vence en 26 días» sin fecha, igual que `prototipos/sistema.html` línea 449. **Se
  recomienda acotar la regla del archivo a los correos** y dejar para pantalla la versión débil: una
  referencia relativa necesita su fecha absoluta visible, en el mismo sitio o al lado. **En la fase 3
  deja de ser cosmético**: en los correos la regla es literal y no hay ninguna columna al lado.
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
- Abierta de veinticinco sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1B-02, crear empresa**: tres pasos —datos, parámetros, plan y cuenta— con resumen
  final copiable e invitación por correo. Todas las piezas existen ya: `PasosDelAsistente`,
  `ParametrosDeLaEmpresa::CAMPOS`, `Invitaciones` y el registro de empleado como ejemplo de asistente.
  Lee 4.2 del documento 4 y abre `prototipos/panel-proveedor.html`.
- **Toda pantalla nueva del panel se envuelve en `ArmazonProveedor`**, nunca en `Armazon` a pelo: el
  rótulo, las secciones y el tono oscuro están ahí en un solo sitio.
- **`SeccionDelPanel::PREFIJO` es el único sitio donde está escrito `proveedor`.** Si algún día el
  panel se muda a un subdominio, se cambia ahí y en el grupo de rutas.
- **El armazón y la barra lateral son de las dos aplicaciones.** Si una necesita algo que la otra no,
  es una prop con el valor del cliente por defecto, no un componente nuevo.
- **`App\Presentacion\Formato` es el único sitio donde se escribe una fecha, un número de días o un
  peso.** No escribas `format('d/m/Y')` ni `translatedFormat` en ninguna parte; si hace falta un
  formato nuevo, se agrega ahí con su caso en `FormatoTest`.
- **`REV-01` va antes de F2-04.** Es el armazón de listado del servidor, y F2-04 es el primer listado
  nuevo: hacerlo después significa copiar `opcion()` una tercera vez. **El listado de empresas de
  F1B-03 es el tercer listado del proyecto**, así que conviene mirar `REV-01` antes de escribirlo.
- **Todo parámetro nuevo de la empresa se agrega en `ParametrosDeLaEmpresa::CAMPOS`** y en las cuatro
  tablas de textos de ese archivo. La pantalla, la validación y el guardado lo recogen solos; lo único
  que hace falta aparte es la columna.
- **Los parámetros se leen de `tenant_actual()`, nunca de una copia.** Es lo que hace cierta la
  promesa de que un cambio se refleja de inmediato. **En el panel del proveedor no hay
  `tenant_actual()`**: el `owner` no tiene empresa y `ResolverTenant` no le fija ninguna, así que
  cuando F1B-03 edite los parámetros de una empresa desde el panel habrá que entrar a su contexto con
  `ContextoTenant::paraTenant()`.
- **`CambioAIndefinido` es el sitio de cualquier cosa sobre el cambio de modalidad**,
  `RenovacionDeContrato` el de una prórroga propuesta y `VigenciaDelContrato` el de las cuentas sobre
  un contrato existente. No escribas una cuarta.
- **Las frases que la pantalla promete se escriben donde vive el efecto.** Si una confirmación afirma
  algo que el servidor hace, el texto va en el servidor: así una prueba puede comprobar las dos cosas
  a la vez.
- **Toda regla que dependa de una decisión abierta va a `config/validaciones.php`**, con su estado y
  el punto del documento 6 que la sostiene. No se escriben ramas «por ahora advierte» dentro de la
  lógica.
- **El helper `enLaEmpresa()` de las pruebas vive en `PersonasYVinculacionesTest.php`** y `usuarioDe()`
  en `RolesYPermisosTest.php`; Pest los comparte con todos los archivos. Sin el primero, cualquier
  consulta hecha desde una prueba fuera de una petición HTTP devuelve vacío, porque el global scope
  falla cerrado.
- **La tabla del historial sigue sin las columnas «Duración» y «Registrado» del prototipo.** La arma
  `FichaDeEmpleado::filasDelHistorial()` y la enseñan las dos pantallas, así que agregarlas es un solo
  sitio.
- **`OpcionesDeLista` sigue solo en los cuatro enumerados de F1-06.** `Genero`, `TipoDocumento`,
  `TipoContrato`, `TipoRiesgo`, `TipoVivienda`, `EstadoVinculacion`, `EventoContractual` y ahora
  `SeccionDelPanel` mantienen sus métodos escritos a mano. Unificarlos es un rato y no rompe nada.
- **No queda ninguna tarea bloqueada en el backlog.** Sí quedan dos `REV-` al final.
