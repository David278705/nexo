# Estado del proyecto

**Última sesión:** 2026-08-21 · Tarea F2-03 · Flujo compartido de novedades
**Verificación:** verde
**Siguiente tarea:** REV-01 · Un solo armazón de listado en el servidor. **Su momento llegó**: dice
«antes del primer listado de la fase 2 (F2-04)», y F2-04 es lo que sigue. Después REV-02, y luego
F2-04 · Incapacidades.

## Qué se hizo

- **`ModalDeNovedad.vue` es el flujo entero de la sección 5.5 en un solo componente.** Los cinco
  pasos: el listado lo abre, el modal pequeño pide **solo** el documento, la consulta sale **al salir
  del campo** —no al pulsar Continuar—, el error del servidor se pinta debajo del campo y deja
  Continuar deshabilitado, y cuando el empleado existe el modal pequeño **se reemplaza** por el
  grande, con la ficha arriba y el formulario del módulo en un `slot`.
- **La búsqueda del empleado la resuelve el servidor y es una sola.**
  `App\Novedades\EmpleadoDeNovedad` la responde y se comparte desde `HandleInertiaRequests` como prop
  opcional; el modal la pide con `router.reload({ only: ['empleadoDeNovedad'] })`. **Sin `axios` ni
  `fetch`**, que es la regla de interfaz, y sin que el navegador tenga que conocer la lista de
  empleados de la empresa.
- **`App\Novedades\TipoDeNovedad` guarda los cinco títulos del prototipo** —«Nueva incapacidad»,
  «Registrar vacaciones», «Nueva licencia o ausencia», «Nuevo movimiento salarial», «Registrar
  terminación»— y el módulo de cada uno. Una prueba comprueba que no se les cambió una coma.
- **La ficha del modal grande es `FichaDeEmpleado::resumenParaNovedad()`**: empleado, documento,
  cargo, fecha de ingreso y saldo de vacaciones, los cinco de la sección 5.5. La pinta el mismo
  `ResumenDeEmpleado` de la ficha del empleado, y el saldo sale del motor.
- **El aislamiento y el permiso se comprueban en este camino también.** El mismo documento en dos
  empresas devuelve el de cada una, y a quien no puede registrar **ninguna** de las cinco novedades
  no se le resuelve la búsqueda: la ficha lleva cargo y saldo, y no es un dato público de la empresa.
- **Dieciséis casos nuevos**: nueve de Pest en `tests/Feature/FlujoDeNovedadTest.php` y trece de
  Vitest en `ModalDeNovedad.test.js`.
- `composer verificar` en verde: Pint, PHPStan sin errores, **580** pruebas de Pest, build y **216**
  de Vitest.

## Decisiones tomadas

- **La prop se comparte en el middleware y no la sirve cada controlador.** Es la decisión de fondo de
  la tarea. El flujo es el mismo en los cinco módulos; cinco controladores armando la misma búsqueda
  son cinco fichas que se separan a la primera corrección. `flujoDeNovedad` son cadenas fijas y
  `empleadoDeNovedad` es `Inertia::optional()`: **no cuesta una consulta en la carga de ninguna
  pantalla**, solo cuando el modal la pide. Hay una prueba que lo defiende.
- **Como la prop la tiene toda pantalla del cliente, el permiso se comprueba dentro.** No basta con
  el `modulo:` de la ruta, porque la ruta puede ser la de empleados. Se resuelve solo a quien pueda
  entrar a alguno de los cinco módulos de novedades, preguntándole a `PoliticaDeModulos`, que es la
  misma que responde al middleware.
- **El paso 4 llega al pulsar Continuar, no automáticamente al encontrar al empleado.** El
  documento dice «si existe, el modal pequeño se reemplaza por uno grande» sin decir cuándo; el
  prototipo lo hace con el botón Continuar y se siguió el prototipo.
- **El componente descarta una respuesta que ya no corresponde a lo que hay en el campo.** Sin eso,
  borrar dos cifras del documento dejaba en pantalla la ficha del empleado anterior y dejaba
  continuar con él. Tiene su prueba.
- **La persona inactiva tiene mensaje propio.** El documento pide un solo mensaje —«si no existe o no
  está activo»— y el prototipo solo escribe el de «no existe». Se separó porque decirle a quien
  digita que la persona **sí** está en el sistema le ahorra volver a buscarla, y porque el remedio es
  otro: registrar un ingreso nuevo, no corregir el número. Es un texto de interfaz, no una regla
  legal.
- **La novedad que usa el componente hoy es la de la prueba, y a propósito.** El «hecho cuando» pide
  que «una novedad de prueba lo use»: la de Vitest registra una incapacidad de mentira por el `slot`,
  con su empleado resuelto. **No se adelantó ni un pedazo de F2-04**: no hay tabla, ni listado, ni
  guardado, y un modal que no puede guardar en una pantalla real habría sido peor que ninguno. El
  primer llamador de producción es F2-04, y le cuesta dos líneas.

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
- **El saldo de vacaciones ya se ve en la ficha del empleado y ahora también en el modal de
  novedades.** Sale del motor y no de ninguna columna. Conviene mirarlo contra un caso conocido antes
  de enseñarlo a un cliente: la carga inicial —saldo de corte y su fecha— todavía se digita a mano y
  es F5-02.
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

- **La siguiente es REV-01**, y su propio enunciado dice que va **antes de F2-04**. Es extraer el
  armazón común de `ListadoDeEmpleados` y `ListadoDeContratos`; REV-02 es lo mismo en la interfaz,
  con `Pages/Empleados/Index.vue` y `Pages/Contratos/Index.vue`. Hacerlo con dos ejemplos es barato;
  con cinco, ya no.
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
  `vinculacionConSaldoDeCorte()` en `MotorDeCalculoTest.php` y `empleadoParaNovedad()` con
  `empleadoDeNovedad()` en `FlujoDeNovedadTest.php`. **Una prueba que se ejecute sola con `--filter`
  no los encuentra: hay que correr la suite.**
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
- **No queda ninguna tarea bloqueada en el backlog.** Sí quedan dos `REV-`, y la primera toca ahora.
