# Estado del proyecto

**Última sesión:** 2026-08-21 · Tarea F2-02 · Motor de cálculo
**Verificación:** verde
**Siguiente tarea:** F2-03 · Flujo compartido de novedades

## Qué se hizo

- **Los diez casos obligatorios del motor pasan**, que es el «Hecho cuando» de la tarea, y pasan
  **sin que se tocara una sola de las diez pruebas**: lo único que cambió en ese archivo fue el
  comentario de cabecera, que decía que fallaban a propósito.
- **`composer verificar` volvió a correr la suite entera.** Se quitó `--exclude-group=motor`, se
  borró el script `composer motor` y se borró la clase `CalculoSinImplementar`, que se quedó sin
  quien la lanzara. La exclusión temporal de F2-01 ya no existe.
- **`MotorDeCalculo` tiene las tres reglas de conteo con nombres distintos**, como exige el
  documento, más las cuatro funciones auxiliares que ya había decidido F2-01 y una quinta,
  `diasComercialesDelMes()`, que es la que sabe de la base de treinta días.
- **`SaldoDeVacaciones` suma mes calendario a mes calendario y solo los ya cerrados**, empezando el
  día siguiente a la fecha de corte —o en la fecha de ingreso cuando no hay carga inicial—. No lee
  ninguna columna de saldo, que es la regla 2.
- **`PeriodoDePrueba` lee sus tres constantes de `config/reglas_laborales.php`** y distingue el
  periodo del indefinido del tope del fijo, aunque hoy los dos valgan dos meses.
- **Ya no queda ninguna cuenta laboral fuera de `app/Dominio/Calculo/`.** Los dos conteos de días
  corridos escritos a mano —`HistorialDeContrato::duracionTotalEnDias()` y el privado de
  `RenovacionDeContrato`— llaman al motor, y el privado desapareció.
- **El saldo de la ficha del empleado dejó de ser un hueco**: enseña el número calculado. Para
  escribirlo se agregó `Formato::diasDecimales()`, que es lo único que sabe poner «12,75 días».
- **Ocho casos nuevos en la batería del motor**, en su propia sección y detrás de los diez: sábado no
  laboral, novedad de un solo día, mes de veintiocho días cubierto entero, alerta en día de oficina,
  alerta corrida por festivo entre semana, prorrateo del primer mes de quien entra a mitad de mes,
  saldo que deja de crecer cuando la persona sale, y periodo de prueba del indefinido.
- `composer verificar` en verde: Pint, PHPStan sin errores, **565** pruebas de Pest, build y **204**
  de Vitest.

## Decisiones tomadas

- **La base comercial de treinta días quedó con sus dos caras, y la segunda es una deducción.** La
  primera —el día 31 no acumula— la fijó F2-01 leyendo los casos obligatorios. La segunda es que
  **un mes cubierto entero son treinta días aunque tenga veintiocho**: sin ella, una licencia de todo
  febrero dejaría dos días acumulando sin que nadie hubiera trabajado. Es la misma convención vista
  por el otro extremo, pero **ningún documento la escribe**. Está probada y anotada en
  `diasComercialesDelMes()`, y la pregunta está abajo.
- **La fracción del mes del corte se mide contra los días con vínculo, no contra treinta.**
  `acumulacionMes()` ya viene con los días sin vínculo descontados; volver a dividirlos por treinta
  los descontaba dos veces y quien ingresaba el 16 de marzo acumulaba un cuarto de mes en vez de
  medio. Lo encontró una de las pruebas nuevas, no una revisión.
- **`diasComercialesDelMes()` es público y `diasSuspendidosDelMes()` lo llama.** Son la misma
  aritmética —los días de un mes que caen dentro de un rango— y separarlas era copiar el conteo. El
  nombre del documento se conserva porque es el que usa quien registra una licencia.
- **Los días sin vínculo y los días de suspensión se suman antes de una sola llamada a
  `acumulacionProporcional()`.** Son la misma reducción sobre la misma base, y tratarlas como dos
  factores multiplicativos daría un número distinto y equivocado.
- **`diaHabilAnteriorParaAlertas()` devuelve el mismo día si es hábil.** El documento 1, 9.1, solo
  manda correr lo que cae en sábado, domingo o festivo.
- **La quinta parte del periodo de prueba se trunca, no se redondea.** El artículo 78 dice que «no
  puede exceder» esa fracción, así que medio día de más ya la excede.
- **`al()` y `faltantePara()` redondean a cuatro decimales.** 1,25 y sus proporciones no son exactos
  en coma flotante y un saldo de 12,749999999 no se puede comparar con nada. Los dos decimales de
  pantalla los pone `Formato`, no el motor.
- **La ficha enseña el saldo aunque falten módulos, y no es una aproximación.** Las vacaciones
  disfrutadas son F2-05 y las suspensiones F2-07: hoy **no existe ni una fila** de ninguna de las
  dos, así que el número que sale es el saldo completo. El día que existan, el saldo las descuenta
  solo y la ficha no cambia una línea.

## Preguntas para Angela

- **Los meses comerciales de treinta días siguen deducidos, no confirmados, y ahora tienen una cara
  más.** La primera pregunta sigue igual: **¿la acumulación de vacaciones se calcula sobre meses de
  treinta días, y el día 31 no acumula?** La segunda es nueva y la trajo febrero: **¿un mes de
  veintiocho o veintinueve días cubierto entero por una licencia cuenta como treinta días de
  suspensión —acumulación cero— o como veintiocho?** Se implementó lo primero, porque lo segundo
  dejaría acumulando dos días que nadie trabajó. Cambiarlo es una línea y una prueba.
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
- **B.1 sigue abierta y ahora tiene consecuencia visible.** El límite de prórrogas cortas advierte y
  no bloquea porque así lo pide ese punto. **Confirmada la regla con el asesor laboral, pasa a
  bloquear con una palabra.**
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años. **Ya tienen su sitio
  reservado**: son dos renglones de la pestaña «Umbrales» que hoy dicen «Sin decidir».

## Decisiones para David

- **El saldo de vacaciones ya se ve en la ficha y es un número real.** Sale del motor y no de ninguna
  columna. Conviene que lo mires contra un caso conocido antes de enseñarlo a un cliente: la cuenta
  está probada, pero la carga inicial —saldo de corte y su fecha— todavía se digita a mano y es F5-02.
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
- **La fecha de salida no existe todavía, y ahora pesa más.** `Vinculacion::fechaDeSalida()` devuelve
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
  2025, 2026 y 2027, quince minutos, y se corrigen desde el panel. **Ya no es teoría**: el motor
  cuenta los días hábiles de vacaciones leyendo esa tabla y corre las alertas al día hábil anterior
  con ella.
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

- **La siguiente es F2-03**, el flujo compartido de novedades: el modal de documento con validación
  al salir del campo y el modal grande con la ficha del empleado, **un solo componente para los cinco
  módulos** (documento 3, sección 5.5). `ResumenDeEmpleado` ya existe y es lo que va dentro del modal
  grande: se escribió en F1-07 pensando en esto.
- **REV-01 y REV-02 siguen pendientes y el plazo se acerca: antes de F2-04.** Es decir, después de
  F2-03. Son el armazón de listado repetido, en el servidor y en la interfaz.
- **Lo que el motor deja preparado para los módulos que vienen.** F2-04 cuenta con
  `MotorDeCalculo::diasCorridos()`; F2-05 con `diasHabilesVacaciones()` y con
  `SaldoDeVacaciones::al()`; F2-06 con `faltantePara()`, que ya devuelve «1,25» en su caso
  obligatorio; F2-07 con `acumulacionProporcional()`, que es la aritmética ya probada.
- **F2-05 y F2-07 tienen su punto de entrada señalado con nombre y apellido.** El saldo descuenta las
  vacaciones disfrutadas en `SaldoDeVacaciones::acumulacionHasta()` y los días de suspensión en
  `MotorDeCalculo::diasDeSuspensionDelMes()`, que hoy devuelve cero y explica por qué. **El parámetro
  `suspensiones_afectan_vacaciones` se lee ahí dentro**, para que ninguna pantalla pueda olvidarse.
- **Ninguna pantalla ni ningún modelo cuenta días.** Si aparece una resta de fechas fuera de
  `app/Dominio/Calculo/`, es un error: hoy no queda ninguna.
- **`App\Presentacion\Formato` es el único sitio donde se escribe una fecha, un número de días o un
  peso.** El motor devuelve números; quien los pinta es `Formato`. Los días con fracción son
  `diasDecimales()` y los enteros `dias()`: son dos porque «6,00 días» de una incapacidad se lee mal.
- **Los helpers de prueba están repartidos y Pest los comparte**: `enLaEmpresa()` en
  `PersonasYVinculacionesTest.php`, `usuarioDe()` en `RolesYPermisosTest.php`, `ownerVerificable()`
  en `PanelDelProveedorTest.php`, `ownerEnElPanel()` y `datosDeEmpresa()` en `CrearEmpresaTest.php`,
  `enEmpresa()` con `empresaConEmpleados()` en `PanelDeEmpresasTest.php`,
  `departamentoConMunicipio()` en `PanelDeCatalogosTest.php` y `enLaEmpresaDelMotor()` con
  `vinculacionConSaldoDeCorte()` en `MotorDeCalculoTest.php`. **Una prueba que se ejecute sola con
  `--filter` no los encuentra: hay que correr la suite.**
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
- **No queda ninguna tarea bloqueada en el backlog.** Sí quedan dos `REV-` al final.
