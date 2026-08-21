# Estado del proyecto

**Última sesión:** 2026-08-21 · Tarea F1B-03 · Listado y ficha de empresas
**Verificación:** verde
**Siguiente tarea:** F1B-04 · Catálogos y constantes desde el panel

## Qué se hizo

- **El panel ya enseña el negocio entero.** `Proveedor/Empresas` es el listado del documento 4,
  sección 4.1, con sus ocho columnas, sus filtros, su orden y su paginación resueltos en el servidor,
  como los del entorno del cliente.
- **Los empleados de cada empresa se cuentan de verdad.** No salen de `tenants.empleados_cargados`
  —ese contador lo refresca el proceso diario, que es F3-01, y hoy diría que todos los clientes tienen
  cero—: se cuentan **dentro del contexto de cada empresa**, con `ContextoTenant::paraTenant()`, que
  es lo mismo que hará el recorrido de empresas del proceso diario. Así el conteo pasa por las dos
  capas de aislamiento en vez de saltárselas.
- **El indicador de salud de uso existe y es real.** Sale del último ingreso de cualquier usuario de
  la empresa, que es lo que el capítulo 16 llama «la señal más temprana de cancelación». La columna
  `ultimo_acceso_en` **la escribe ahora el propio inicio de sesión**, en `RegistroDeAccesos`, que es
  cuando el hecho ocurre; no espera a un proceso nocturno que todavía no existe.
- **«Pago» se declara en vez de inventarse.** Las suscripciones son F5-04, así que la columna dice
  «Sin cobros todavía» y **su filtro no se ofrece**: un filtro que no puede distinguir nada estorba
  más de lo que ayuda. Es el mismo criterio del inicio del panel.
- **El resaltado de «es una conversación de venta»** cuando los empleados cargados superan los del
  plan, con palabras además del color, lo decide el servidor.
- **La ficha de la empresa, con sus siete pestañas.** Cuatro con contenido —Resumen, Parámetros,
  Usuarios y Uso— y tres que dicen de qué tarea depende lo suyo: Suscripción (F5-04), Alertas (fase 3)
  y Bitácora (F5-07).
- **Los parámetros se editan desde el panel** y el cambio queda en la auditoría **de esa empresa**,
  con el `owner` como autor: es la «constancia del cambio» que pide 4.3 y es lo que el cliente puede
  ver en su propia auditoría. Hay prueba de las dos cosas.
- **Nadie ve una contraseña, y no porque la pantalla no la pinte: porque no existe nada que la
  devuelva.** Las dos acciones sobre un usuario son reenviar la invitación y mandarle el enlace de
  restablecer. Las dos comprueban que el usuario sea de esa empresa y responden 404 si no lo es.
- **El certificado de Cámara de Comercio ya tiene dónde consultarse**: la pestaña Resumen de la ficha,
  que era el pendiente que dejó F1B-02.
- `EstadoDeEmpresa` y `SaludDeUso` son los enumerados nuevos. Las tres constantes de estado de
  `Tenant` ahora salen del enumerado, así que la lista de estados posibles está escrita una sola vez.
- `composer verificar` en verde: Pint, PHPStan sin errores, **519** pruebas de Pest (16 más), build y
  **197** de Vitest (11 más).

## Decisiones tomadas

- **Los umbrales de la salud de uso van a `config/panel.php`, no dentro de la lógica.** El documento
  nombra los tres estados y **no dice a cuántos días empieza cada uno**. Como no es una regla legal
  que se pueda consultar sino una decisión comercial, se sigue el patrón de `config/validaciones.php`:
  una propuesta escrita con su porqué, en un solo sitio, y anotada abajo para David. **Siete días
  para «activa», treinta para «sin uso».**
- **«Nunca ha entrado nadie» es «sin uso» y no un cuarto estado.** Para la conversación de venta, la
  empresa que no ha entrado nunca y la que lleva dos meses sin entrar necesitan la misma llamada. Lo
  que las distingue se lee en la columna de al lado, que dice «Nunca».
- **El último ingreso se escribe al iniciar sesión, no en un proceso nocturno.** La migración creó la
  columna para que la refrescara el proceso diario; escribirla donde ocurre el hecho es exacto, cuesta
  una consulta y **hace que el panel no enseñe nunca un dato de ayer**. Va con `update()` del
  constructor de consultas para no dejar una línea de auditoría por cada entrada de cada usuario: eso
  ya lo cuenta la tabla `accesos`.
- **Los empleados se cuentan y los usuarios también; el contador guardado no se usa.** La diferencia
  con el último ingreso es deliberada: el ingreso es un hecho con **un solo escritor**, así que
  anotarlo es exacto; los empleados cambian por muchos caminos y un contador que alguno olvide
  actualizar miente sin que nadie lo note.
- **El filtro de pago no se construye.** Está en el documento, pero no hay dos valores distintos que
  ofrecer hasta F5-04.
- **La ficha no ordena por «Empleados».** Ese número se cuenta empresa por empresa dentro de su
  contexto, así que la base de datos no podría ordenarlo sin sacar el dato de su aislamiento.
- **Las empresas sin ningún ingreso van al final del orden por «Último ingreso», en los dos
  sentidos.** Son el caso que hay que mirar, no una fecha vieja.
- **El singular y el plural de los estados se escriben en el enumerado.** «1 suspendidas» delata una
  frase armada con una concatenación, y «en prueba» no lleva ese.
- **La ficha usa `ContextoTenant::paraTenant()` para el aviso del perfil sociodemográfico**, que
  necesita contar los empleados ya cargados. En el panel no hay `tenant_actual()` y no lo va a haber.

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

- **Los umbrales de la salud de uso son una propuesta tuya de confirmar.** Están en
  `config/panel.php`: **siete días** para que una empresa siga contando como «activa» y **treinta**
  para pasarla a «sin uso». El documento pide los tres estados y no da los números. Confirmarlos o
  cambiarlos es editar ese archivo y nada más: ni la consulta, ni el filtro, ni la pantalla cambian.
- **A.1 sigue abierta.** El plan de una empresa son hoy `plan_nombre`, `plan_empleados_incluidos`,
  `plan_usuarios_incluidos` y `plan_inicia_el`, todas en `tenants`. **El día que existan los niveles
  con su precio, esas cuatro columnas se sustituyen por `plan_id` y la suscripción.** El filtro de
  plan del listado nuevo se alimenta hoy de los nombres que se han usado, así que ese día también
  cambia de fuente —es una línea—.
- **La escalera de mora sigue sin números** y ahora tiene una columna esperándola: «Pago» dice «Sin
  cobros todavía» en todas las filas. El capítulo 6 propone aviso a los 5 días, segundo aviso a los 15
  y suspensión a los 30, y dice que «los números son una decisión de ustedes».
- **El prototipo pide dos campos que el documento no lista y no se construyeron**: «valor mensual»
  —precio, A.1— y «número de empleados declarado», que **es otra cosa que los empleados incluidos en
  el plan**: es lo que el cliente dice que tiene, y es de lo que se alimentaría la tarjeta «clientes
  sin arrancar» del capítulo 3. Si hace falta, es una columna y un campo.
- **`empleados_cargados` y `ultima_novedad_en` siguen sin escritor.** La primera ya no hace falta para
  el listado —se cuenta—, y la segunda no puede tenerlo hasta que existan las novedades. Cuando F3-01
  construya el proceso diario, decide si se quedan como caché o se borran: **hoy son dos columnas que
  nadie llena y de las que nadie depende**.
- **Un código de segundo factor fallido no queda en la tabla `accesos`.** Ahí solo queda el acceso con
  contraseña, que sí se registra. Si interesa el rastro, es un valor nuevo de `ResultadoDeAcceso`.
- **No hay forma de reemitir un segundo factor perdido.** Hoy se arregla borrando las dos columnas del
  usuario en la base de datos. Con una sola cuenta de proveedor es aceptable; **con equipo, no**.
- **La regla de «la fecha entre paréntesis» está escrita más ancha de lo que la sostiene el
  documento.** `.claude/rules/30-interfaz.md` la enuncia para toda la interfaz; su fuente —documento
  1, sección 9.1— solo para los mensajes de las alertas. **Se recomienda acotar la regla del archivo a
  los correos. En la fase 3 deja de ser cosmético.**
- **`User`, `Acceso` e `Invitacion` no llevan `SoftDeletes`.** Importará cuando exista «Usuarios y
  permisos»: dar de baja a un usuario tiene que ser lógico, o se pierde de quién eran las líneas de
  auditoría que él escribió. **La pestaña «Usuarios» de la ficha ya enseña a todos los usuarios de una
  empresa, así que ahí se vería primero.**
- **Los parámetros de cálculo todavía no tienen quien los lea.** Ya se pueden cambiar desde los dos
  lados —el cliente y el panel—, pero **el único consumidor construido es el del perfil
  sociodemográfico**: el motor que los usará es F2-02.
- **El menú del cliente no tiene «Usuarios y permisos» ni «Facturación», y ninguna tiene tarea en el
  backlog.** Crear el superadministrador ya se puede desde el panel; **crear sus administradores,
  todavía no desde ninguna parte**. Si el cliente debe poder hacerlo antes de vender, es una tarea
  nueva.
- **Sigue sin existir «Editar contrato».** El aviso del modal de renovación dice «edita el contrato»
  sin enlazar a ninguna parte.
- **El texto del hueco del saldo de vacaciones se lee en pantalla y sigue siendo tuyo.** Está en
  `FichaDeEmpleado::saldoDeVacaciones()`.
- **La fecha de salida no existe todavía.** `Vinculacion::fechaDeSalida()` devuelve `fecha_fin` de una
  vinculación inactiva. Está anotado en F2-09.
- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Hay nueve EPS intervenidas por la Supersalud. **Es una lista global: si está mal, está mal para
  todos los clientes a la vez.**
- **Verificar el calendario de festivos antes de vender.** Cincuenta y tres fechas, quince minutos,
  mismo riesgo global que las EPS.
- **Contrastar los 1.101 municipios contra el archivo del DANE.** Ese mismo cotejo trae los códigos
  DANE que hoy están nulos.
- **Vigencia del enlace de invitación: siete días.** En `Invitaciones::DIAS_DE_VIGENCIA`. Ahora se lee
  en dos sitios: la confirmación de «empresa creada» y la de «invitación reenviada». **Confírmalo.**
- **El rol de conexión de PostgreSQL.** `.env` se conecta como `postgres`, superusuario con
  `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace falta un rol
  corriente con `SELECT, INSERT, UPDATE, DELETE`.
- Abierta de veintisiete sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1B-04, catálogos y constantes desde el panel**: capítulo 8 del documento 4. Las
  tablas y sus datos ya existen desde F0-11 y **`docs/06-decisiones-pendientes.md` lo nombra
  expresamente** en su línea 328: hasta esa tarea, las listas se actualizan recargando el seeder.
- **REV-01 y REV-02 siguen pendientes y ahora hay un tercer listado.** `ListadoDeEmpresas` volvió a
  escribir `opcion()`, `porPagina()`, `pagina()`, `tieneFiltros()`, `paraLaInterfaz()`, `TODOS` y
  `TAMANOS_DE_PAGINA`, y `Proveedor/Empresas.vue` volvió a escribir `pedir()`, `ordenar()`, `quitar()`
  y `limpiar()`. **Se hizo así a propósito: extraer el armazón es una tarea del backlog y mezclarla
  con esta habría dejado un cambio imposible de revisar.** El plazo que fijó la revisión sigue en pie:
  **antes de F2-04**, y ahora con tres ejemplos en vez de dos, que es mejor material para decidir qué
  es común de verdad.
- **`SaludDeUso` tiene la regla escrita dos veces a propósito** —`segunElUltimoIngreso()` clasifica una
  fila y `filtrar()` filtra muchas—, y por eso viven pegadas la una a la otra en el mismo archivo. Si
  cambia una, cambia la otra, o el filtro «sin uso» devolverá empresas que la tabla pinta en verde.
- **`ListadoDeEmpresas::empleadosDe()` es el único sitio donde se cuentan los empleados de otra
  empresa.** Cualquier cifra nueva del panel que mire tablas de negocio se pide igual, dentro de
  `paraTenant()`.
- **Toda pestaña nueva de la ficha se declara en `FichaDeEmpresa::PESTANAS`** y, si todavía no tiene
  contenido, en `PENDIENTES` con la tarea de la que depende.
- **`CreacionDeEmpresa` es el sitio de cualquier cosa sobre dar de alta un cliente** y
  **`FichaDeEmpresa` el de cualquier cosa que la ficha enseñe.** No escribas una tercera.
- **Toda pantalla nueva del panel se envuelve en `ArmazonProveedor`**, nunca en `Armazon` a pelo.
- **`Armazon` ya pinta la prop compartida `estado`.** Un `->with('estado', …)` del servidor se ve
  solo, en las dos aplicaciones. No agregues una `Notificacion` por pantalla.
- **`SeccionDelPanel::PREFIJO` es el único sitio donde está escrito `proveedor`** —salvo las rutas de
  empresas, que lo llevan en su nombre de ruta—. Una sección que estrene contenido sale de
  `SeccionDelPanel::pendientes()`, como acaba de salir «Empresas».
- **`App\Presentacion\Formato` es el único sitio donde se escribe una fecha, un número de días o un
  peso.**
- **Todo parámetro nuevo de la empresa se agrega en `ParametrosDeLaEmpresa::CAMPOS`** y en las cuatro
  tablas de textos de ese archivo. Las **dos** pantallas que los editan —la del cliente y la de la
  ficha del panel—, la validación y el guardado lo recogen solos; lo único aparte es la columna.
- **`CambioAIndefinido` es el sitio de cualquier cosa sobre el cambio de modalidad**,
  `RenovacionDeContrato` el de una prórroga propuesta y `VigenciaDelContrato` el de las cuentas sobre
  un contrato existente.
- **Las frases que la pantalla promete se escriben donde vive el efecto.**
- **Toda regla que dependa de una decisión abierta va a `config/`**, con su estado y el punto que la
  sostiene: `validaciones.php` para las legales, `panel.php` para las comerciales.
- **Los helpers de prueba están repartidos y Pest los comparte**: `enLaEmpresa()` en
  `PersonasYVinculacionesTest.php`, `usuarioDe()` en `RolesYPermisosTest.php`, `ownerVerificable()` en
  `PanelDelProveedorTest.php`, `ownerEnElPanel()` y `datosDeEmpresa()` en `CrearEmpresaTest.php`, y
  `enEmpresa()` con `empresaConEmpleados()` en `PanelDeEmpresasTest.php`. **Una prueba que se ejecute
  sola con `--filter` no los encuentra: hay que correr la suite.**
- **La tabla del historial sigue sin las columnas «Duración» y «Registrado» del prototipo.** La arma
  `FichaDeEmpleado::filasDelHistorial()`.
- **`OpcionesDeLista` lo usan ahora seis enumerados**, los cuatro de F1-06 y los dos del panel.
- **No queda ninguna tarea bloqueada en el backlog.** Sí quedan dos `REV-` al final.
