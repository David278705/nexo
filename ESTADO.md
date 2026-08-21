# Estado del proyecto

**Última sesión:** 2026-08-21 · Tarea REV-01 · Un solo armazón de listado en el servidor
**Verificación:** verde
**Siguiente tarea:** REV-02 · Un solo armazón de listado en la interfaz. Es el gemelo de esta en Vue,
y va **antes de F2-04** por la misma razón. Después, F2-04 · Incapacidades.

## Qué se hizo

- **`App\Listados\Listado` es el armazón entero de toda pantalla de listado.** Clase base abstracta y
  genérica sobre el modelo (`@template TModelo of Model`), con lo que los tres listados escribían tres
  veces: `consulta()`, `pagina()`, `total()`, `tieneFiltros()`, `paraLaInterfaz()`, `ordenar()` con su
  desempate por `id`, los lectores de la dirección —`filtro()`, `opcion()`, `texto()`, `fecha()`,
  `porPagina()`, `valoresDe()`, `textoDe()`, `enFormatoColombiano()`— y las tres constantes
  `TAMANOS_DE_PAGINA`, `POR_PAGINA_POR_DEFECTO` y `TODOS`.
- **Cada listado declara solo lo que es suyo**: `base()` (qué modelo y con qué relaciones),
  `filtrar()`, `columnasDeOrden()`, `ordenPorDefecto()`, `filtrosParaLaInterfaz()` y `aplicados()`.
  `ListadoDeEmpleados` pasó de 416 a 287 líneas, `ListadoDeContratos` de 414 a 331 y
  `ListadoDeEmpresas` de 321 a 286 —comentarios incluidos—, y ninguno
  contiene ya la palabra `function opcion(`, `function porPagina(`, `function pagina(`,
  `function total(`, `function tieneFiltros(`, `function paraLaInterfaz(` ni `function ordenar(`.
  **Hay una prueba que lee los tres archivos y lo comprueba.**
- **La lista blanca de ordenamientos dejó de ser una segunda declaración.** Antes había una constante
  `ORDENAMIENTOS` y, aparte, un `match` con las columnas: dos listas que había que acordarse de
  mover juntas. Ahora el orden se valida contra **las claves de `columnasDeOrden()`**, que es el mapa
  mismo. Una columna que no está declarada no puede ordenar, y no existe la forma de declararla en un
  sitio y no en el otro.
- **Se migraron los tres listados que existen, no dos.** `ListadoDeEmpresas` del panel del proveedor
  era la tercera copia del mismo patrón, con `opcion()` y `porPagina()` carácter por carácter. Es el
  mismo refactor y hacerlo aparte habría sido dejarlo escrito por tercera vez.
- **`App\Contratacion\OrdenaPorEmpleado`** es el trait con `dePersona()` y `porNombreDeLaPersona()`:
  empleados y contratos ordenaban el nombre por primer apellido y luego primer nombre, con la misma
  subconsulta y el mismo comentario explicando por qué no es un `join`.
- **Dieciséis casos nuevos en `tests/Feature/ListadosTest.php`**, y ninguna prueba existente se tocó.
  Se prueban con un listado de mentira —`Tests\Fixtures\ListadoDePrueba` sobre
  `Tests\Fixtures\RegistroListable`— que declara dos filtros y dos columnas y **nada más**: si el
  armazón no estuviera compartido, esas pruebas no podrían pasar. Entre ellos, que el listado nuevo
  hereda el aislamiento sin escribir ni un `where('tenant_id', ...)`.
- `composer verificar` en verde: Pint, PHPStan sin errores, **596** pruebas de Pest, build y **216**
  de Vitest.

## Decisiones tomadas

- **Clase base abstracta y no trait.** Un trait habría dejado que cada listado siguiera declarando lo
  que quisiera; la clase base **obliga** a declarar las seis piezas y no compila si falta una. Con
  siete listados por venir, la diferencia es que el séptimo no pueda inventarse su propia forma de
  paginar. El genérico `@template TModelo of Model` mantiene PHPStan en nivel 6 sin un solo `mixed`.
- **`fila()` no se heredó y es a propósito.** Cada listado tiene sus columnas y no hay nada que
  compartir; sigue siendo estática en cada clase y la usan su pantalla y su exportación, que es lo que
  impide que digan cosas distintas de la misma fila.
- **Se separaron `filtro()` y `opcion()`.** El filtro de lista admite «todos» y se cae a su valor por
  defecto; la opción cerrada —el orden y la dirección— no admite «todos», porque la consulta necesita
  un valor sí o sí. **De paso se cerró un agujero pequeño:** antes `?orden=todos` dejaba `orden` en
  «todos» y caía al `default` del `match` por casualidad; hoy no pasa la lista blanca y cae al orden
  por defecto, con su prueba.
- **La interpolación de `nulls last` sigue siendo segura y ahora en un solo sitio.** Los dos
  ordenamientos con `orderByRaw` reciben la dirección ya cotejada contra `['asc', 'desc']` por el
  armazón; `ListadoDeContratos` la re-saneaba a mano por si acaso y eso desapareció, porque el único
  camino por el que llega ya la valida.
- **Los constructores quedaron `protected` y no `private`.** PHP no deja estrechar la visibilidad del
  constructor heredado. `desde()` sigue siendo el único que crea un listado.

## Preguntas para Angela

- **Los meses comerciales de treinta días siguen deducidos, no confirmados, y tienen dos caras.** La
  primera: **¿la acumulación de vacaciones se calcula sobre meses de treinta días, y el día 31 no
  acumula?** La segunda la trajo febrero: **¿un mes de veintiocho o veintinueve días cubierto entero
  por una licencia cuenta como treinta días de suspensión —acumulación cero— o como veintiocho?** Se
  implementó lo primero, porque lo segundo dejaría acumulando dos días que nadie trabajó. Cambiarlo
  es una línea y una prueba.
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
  nueva —tabla, columna, seeder, un campo en el paso 2 del asistente y un renglón en la ficha—, y
  también **una entrada más en `CatalogosDelSistema::ENTIDADES`**, que es un renglón.
- Menor: nivel educativo quedó con **«Técnico / Tecnólogo» unidos** y **«Posgraduado(a)» sin
  distinguir especialización, maestría y doctorado**, tal como los pediste.
- **B.1 sigue abierta y tiene consecuencia visible.** El límite de prórrogas cortas advierte y no
  bloquea porque así lo pide ese punto. **Confirmada la regla con el asesor laboral, pasa a bloquear
  con una palabra.**
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años. **Ya tienen su sitio
  reservado**: son dos renglones de la pestaña «Umbrales» que hoy dicen «Sin decidir».

## Decisiones para David

- **El texto del empleado inactivo es nuevo y no sale de ningún documento.** Dice: «*Fulana* está
  registrada en el sistema, pero su vinculación ya no está activa. No se registran novedades sobre
  una persona inactiva; si volvió a la empresa, regístrala como un ingreso nuevo». Está en
  `EmpleadoDeNovedad::INACTIVO`, con su prueba. **Si prefieres el mensaje único del prototipo, se
  borra la constante y sobra una prueba.**
- **El saldo de vacaciones ya se ve en la ficha del empleado y también en el modal de novedades.**
  Sale del motor y no de ninguna columna. Conviene mirarlo contra un caso conocido antes de
  enseñarlo a un cliente: la carga inicial —saldo de corte y su fecha— todavía se digita a mano y es
  F5-02.
- **La pantalla de constantes legales quedó de solo lectura.** El prototipo la describe como el sitio
  donde se ajusta el sistema cuando cambia una ley; la regla del proyecto dice que esos números viven
  en `config/reglas_laborales.php`. **Se siguió la regla.** Si quieres que sean editables desde el
  panel, es una tarea nueva —tabla, migración y un lector que sustituya a `config()`— y hay que
  decidir antes quién puede tocarlos y qué revisión pasan: hoy pasan por un `git diff`.
- **Los umbrales de la salud de uso siguen siendo una propuesta tuya de confirmar.** Están en
  `config/panel.php`: **siete días** para «activa» y **treinta** para «sin uso».
- **Los cuatro umbrales del capítulo 8 siguen sin números** y la pestaña lo dice fila por fila. Los
  dos primeros los decide Angela con el asesor laboral (D.2 y D.3); **la vigencia del usuario de
  demostración y los días de mora son tuyos**.
- **A.1 sigue abierta.** El plan de una empresa son hoy `plan_nombre`, `plan_empleados_incluidos`,
  `plan_usuarios_incluidos` y `plan_inicia_el`, todas en `tenants`. **El día que existan los niveles
  con su precio, esas cuatro columnas se sustituyen por `plan_id` y la suscripción.**
- **La escalera de mora sigue sin números.** El capítulo 6 propone aviso a los 5 días, segundo aviso
  a los 15 y suspensión a los 30, y dice que «los números son una decisión de ustedes».
- **El prototipo pide dos campos que el documento no lista y no se construyeron**: «valor mensual»
  —precio, A.1— y «número de empleados declarado».
- **`empleados_cargados` y `ultima_novedad_en` siguen sin escritor.** Cuando F3-01 construya el
  proceso diario, decide si se quedan como caché o se borran.
- **Los códigos DANE de los municipios siguen nulos.** Se pueden completar uno por uno desde el
  panel, pero cotejar los 1.101 contra el archivo del DANE a mano no es razonable: si quieres hacerlo
  de una vez, es una carga masiva y es una tarea nueva.
- **Un código de segundo factor fallido no queda en la tabla `accesos`.**
- **No hay forma de reemitir un segundo factor perdido.** Hoy se arregla borrando las dos columnas
  del usuario en la base de datos.
- **La regla de «la fecha entre paréntesis» está escrita más ancha de lo que la sostiene el
  documento.** `.claude/rules/30-interfaz.md` la enuncia para toda la interfaz; su fuente —documento
  1, sección 9.1— solo para los mensajes de las alertas. **Se recomienda acotar la regla del archivo
  a los correos. En la fase 3 deja de ser cosmético.**
- **`User`, `Acceso` e `Invitacion` no llevan `SoftDeletes`.** Importará cuando exista «Usuarios y
  permisos».
- **La fecha de salida no existe todavía, y pesa.** `Vinculacion::fechaDeSalida()` devuelve
  `fecha_fin` de una vinculación inactiva, y **el saldo de vacaciones ya la usa** para dejar de
  acumular. En un indefinido terminado devuelve nulo, así que hoy el saldo de esa persona seguiría
  creciendo. Se arregla en F2-09, que ya lo tiene anotado.
- **El menú del cliente no tiene «Usuarios y permisos» ni «Facturación», y ninguna tiene tarea en el
  backlog.** Crear sus administradores no se puede desde ninguna parte.
- **Sigue sin existir «Editar contrato».** El aviso del modal de renovación dice «edita el contrato»
  sin enlazar a ninguna parte.
- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Hay nueve EPS intervenidas por la Supersalud. Se editan desde la pestaña «EPS y entidades».
- **Verificar el calendario de festivos antes de vender.** Cincuenta y tres fechas cargadas para
  2025, 2026 y 2027, quince minutos, y se corrigen desde el panel. El motor cuenta los días hábiles
  de vacaciones leyendo esa tabla y corre las alertas al día hábil anterior con ella.
- **Vigencia del enlace de invitación: siete días.** En `Invitaciones::DIAS_DE_VIGENCIA`, y también
  visible en la pestaña «Umbrales». **Confírmalo.**
- **El rol de conexión de PostgreSQL.** `.env` se conecta como `postgres`, superusuario con
  `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace falta un rol
  corriente con `SELECT, INSERT, UPDATE, DELETE`.
- Abierta de treinta sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- **La siguiente es REV-02**, el gemelo de esta en la interfaz: extraer
  `resources/js/Utilidades/useListado.js` de `Pages/Empleados/Index.vue` y `Pages/Contratos/Index.vue`
  —`pedir()`, `ordenar()`, `quitar()`, `limpiar()`, `urlExportar` y `textoExportar`—. **Ojo:
  `Pages/Proveedor/Empresas.vue` es la tercera copia**, igual que `ListadoDeEmpresas` lo era en el
  servidor; conviene mirarla antes de decidir la forma del composable.
- **Cómo se agrega el listado de una novedad, que es lo que va a necesitar F2-04.** Se extiende
  `App\Listados\Listado`, se declaran las seis piezas —`base()`, `filtrar()`, `columnasDeOrden()`,
  `ordenPorDefecto()`, `filtrosParaLaInterfaz()`, `aplicados()`—, se escribe su `fila()` estática y su
  `desde(Request)` con los lectores heredados (`texto()`, `filtro()`, `fecha()`, `ordenPedido()`,
  `direccionPedida()`, `porPagina()`). **Nada de paginación, orden ni conteo: eso ya está.**
  `Tests\Fixtures\ListadoDePrueba` es el ejemplo más corto de los cuatro que hay.
- **La lista blanca del orden son las claves de `columnasDeOrden()`.** Si una columna nueva tiene que
  ser ordenable, se agrega al mapa y ya es ordenable; no hay una segunda lista que actualizar.
- **Cómo enchufa un módulo el flujo de novedades, en dos líneas.** La pantalla del listado importa
  `ModalDeNovedad`, le pasa `:titulo` del tipo, `:flujo="flujoDeNovedad"` y
  `:empleado="empleadoDeNovedad"` —las dos props ya llegan compartidas, no hay que tocar el
  controlador—, y mete sus campos en el `slot`, que recibe el empleado resuelto con el
  `vinculacion` que va a guardar. El evento `guardar` es el del botón.
- **Ninguna pantalla ni ningún modelo cuenta días.** El modal de novedades tampoco: los días de la
  incapacidad y del disfrute los calcula el servidor y el módulo los pinta en su `slot`. Si aparece
  una resta de fechas fuera de `app/Dominio/Calculo/`, es un error: hoy no queda ninguna.
- **Lo que el motor deja preparado para los módulos que vienen.** F2-04 cuenta con
  `MotorDeCalculo::diasCorridos()`; F2-05 con `diasHabilesVacaciones()` y con
  `SaldoDeVacaciones::al()`; F2-06 con `faltantePara()`, que ya devuelve «1,25» en su caso
  obligatorio; F2-07 con `acumulacionProporcional()`.
- **F2-05 y F2-07 tienen su punto de entrada señalado con nombre y apellido.** El saldo descuenta las
  vacaciones disfrutadas en `SaldoDeVacaciones::acumulacionHasta()` y los días de suspensión en
  `MotorDeCalculo::diasDeSuspensionDelMes()`, que hoy devuelve cero y explica por qué. **El parámetro
  `suspensiones_afectan_vacaciones` se lee ahí dentro**, para que ninguna pantalla pueda olvidarse.
- **`App\Presentacion\Formato` es el único sitio donde se escribe una fecha, un número de días o un
  peso.** Los días con fracción son `diasDecimales()` y los enteros `dias()`.
- **Los helpers de prueba están repartidos y Pest los comparte**: `enLaEmpresa()` en
  `PersonasYVinculacionesTest.php`, `usuarioDe()` en `RolesYPermisosTest.php`, `ownerVerificable()`
  en `PanelDelProveedorTest.php`, `ownerEnElPanel()` y `datosDeEmpresa()` en `CrearEmpresaTest.php`,
  `enEmpresa()` con `empresaConEmpleados()` en `PanelDeEmpresasTest.php`,
  `departamentoConMunicipio()` en `PanelDeCatalogosTest.php`, `enLaEmpresaDelMotor()` con
  `vinculacionConSaldoDeCorte()` en `MotorDeCalculoTest.php`, `empleadoParaNovedad()` con
  `empleadoDeNovedad()` en `FlujoDeNovedadTest.php` y `listadoDePrueba()` con `titulosDelListado()` en
  `ListadosTest.php`. **Una prueba que se ejecute sola con `--filter` no los encuentra: hay que
  correr la suite.**
- **Una recarga parcial en una prueba de Pest necesita las cuatro cabeceras**, incluida
  `X-Inertia-Version` preguntada al middleware: si no coincide con el manifiesto compilado, Inertia
  responde 409 y la prueba mide otra cosa. El molde está en `FlujoDeNovedadTest::consultarEmpleado()`
  y en `AdvertenciaDeReingresoTest`.
- **Todo parámetro nuevo de la empresa se agrega en `ParametrosDeLaEmpresa::CAMPOS`** y en las cuatro
  tablas de textos de ese archivo.
- **Toda regla que dependa de una decisión abierta va a `config/`**, con su estado y el punto que la
  sostiene: `validaciones.php` para las legales, `panel.php` para las comerciales, y se enseña sola
  en la pestaña «Umbrales» si se le agrega su renglón.
- **`CatalogosDelSistema` es el sitio de cualquier cosa que la pantalla de catálogos enseñe** y
  **`ConstantesLegales` el de la tabla de constantes.**
- **Toda pantalla nueva del panel se envuelve en `ArmazonProveedor`** y **su sección sale de
  `SeccionDelPanel::pendientes()`** el día que estrene contenido.
- **La tabla del historial sigue sin las columnas «Duración» y «Registrado» del prototipo.**
- **No queda ninguna tarea bloqueada en el backlog.** Queda un `REV-`, el de la interfaz, y toca
  ahora.
