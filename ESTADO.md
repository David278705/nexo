# Estado del proyecto

**Última sesión:** 2026-08-21 · Tarea F2-01 · Pruebas del motor, primero
**Verificación:** verde
**Siguiente tarea:** F2-02 · Motor de cálculo

## Qué se hizo

- **Los diez casos obligatorios de la sección 4.2 están escritos y los diez fallan**, que es
  exactamente el «Hecho cuando» de la tarea. Están en `tests/Feature/MotorDeCalculoTest.php`.
- **Fallan por falta de implementación y no por sintaxis, y se puede demostrar.** Las firmas del
  motor ya existen en `app/Dominio/Calculo/` —`MotorDeCalculo`, `SaldoDeVacaciones` y
  `PeriodoDePrueba`— y cada método lanza `CalculoSinImplementar`, cuyo mensaje nombra el método y la
  tarea que lo va a escribir. Los diez fallos son esa excepción, ninguno es un error de PHP ni una
  clase que no existe.
- **La verificación quedó verde sin mentir sobre el estado del motor.** Los diez van en el grupo
  `motor`; `composer verificar` corre `pest --exclude-group=motor` y el script nuevo `composer motor`
  los corre solos y los enseña en rojo.
- **Las tres reglas de conteo quedaron con nombres que no se pueden confundir**, como exige el
  documento: `diasHabilesVacaciones(inicio, fin, empresa)`, `diasCorridos(inicio, fin)` y
  `acumulacionMes(vinculacion, mes)`. Ninguna es la otra con un parámetro distinto.
- **El corrimiento de alertas se llama `diaHabilAnteriorParaAlertas()`** para que no se pueda
  confundir con el conteo de vacaciones: ahí el sábado nunca es hábil, tenga la empresa el sábado
  laboral o no.
- `composer verificar` en verde: Pint, PHPStan sin errores, **546** pruebas de Pest, build y **204**
  de Vitest. Los diez del motor, aparte y en rojo, con `composer motor`.

## Decisiones tomadas

- **La acumulación se cuenta en meses comerciales de treinta días.** No es una regla inventada: sale
  de leer dos casos obligatorios juntos. El de los cuatro días de licencia dice «proporcional a 26
  días, no a 30», que fija la base en treinta; y el de la licencia del 16 de marzo al 15 de abril
  pide media acumulación en los dos meses, lo que solo se cumple si el 31 de marzo no cuenta —del 16
  al 31 son dieciséis días naturales, y quince en base comercial—. Está anotado en la prueba y en el
  motor.
- **El mes de vacaciones se causa completo al cerrarse, no día a día.** También sale de dos casos
  leídos juntos: el saldo al 31 de julio y el proyectado al 28 de agosto son el mismo número, 12,75.
  Si agosto acumulara en proporción a sus días transcurridos, el segundo caso no daría «faltan 1,25».
- **La fecha de corte del saldo es el último día ya incluido en el saldo inicial**, y la acumulación
  empieza al mes siguiente. Es lo que hace que el escenario de los dos casos del saldo no dependa de
  ningún mes a medias: corte al 31 de enero con 5,25 días, seis meses cerrados a 1,25, y 12,75 al 31
  de julio.
- **F2-01 escribe las firmas además de las pruebas, y es a propósito.** Sin ellas los diez fallarían
  con «clase no encontrada», que es un error de escritura y no el fallo que la tarea pide demostrar.
  Escribir la firma es decidir la API que F2-02 va a implementar, no implementarla.
- **Dos funciones más de las tres del documento, y por un motivo concreto.** `diasSuspendidosDelMes()`
  y `acumulacionProporcional()` existen porque los casos 3 y 4 miden la aritmética de la
  proporcionalidad, y el módulo de licencias y suspensiones es F2-07: hoy no hay tabla desde la cual
  armar el escenario. `acumulacionMes()` conserva la firma del documento y terminará llamando a
  `acumulacionProporcional()` con los días que lea de esa tabla. Ninguna de las dos compite con las
  tres reglas de conteo ni recibe un rango que se pueda pasar mal.
- **`diasDeAnticipacion()` es función propia y no una resta suelta.** Del viernes al domingo faltan
  dos días y son tres días corridos: es la confusión que haría que un correo correcto dijera algo
  falso, y el caso obligatorio la nombra.
- **Ninguna prueba del saldo depende de tablas de novedades.** El escenario es saldo inicial más
  acumulación, sin vacaciones disfrutadas, así que los dos casos del saldo se pueden poner en verde
  en F2-02 sin esperar a F2-05.

## Preguntas para Angela

- **El documento 1 y el documento 6 no dicen lo mismo sobre la prórroga mínima.** La sección 5.5 del
  documento 1 dice que **las dos** validaciones bloquean —«no son advertencias»— y la tabla 5.4 del
  documento 3 la sigue; B.1 y B.3 del documento 6, y el propio enunciado de F1-10, piden advertir con
  fuerza en la prórroga corta hasta que el asesor laboral confirme la aplicación práctica. **Se
  siguió el documento 6.** **La pregunta concreta: ¿se confirma esa lectura y se corrige la sección
  5.5, o la prórroga mínima también debe bloquear?** Si es lo segundo, es cambiar una palabra en
  `config/validaciones.php`.
- **Los meses comerciales de treinta días quedaron deducidos, no confirmados.** Es la convención
  laboral corriente y es la única lectura que hace cuadrar los casos obligatorios 3 y 4 a la vez,
  pero **ningún documento la escribe**. **La pregunta concreta: ¿se confirma que la acumulación de
  vacaciones se calcula sobre meses de treinta días, y que el día 31 no acumula?** Si la respuesta
  fuera otra, cambia una prueba y una función, y conviene saberlo antes de F2-02.
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

- **`composer verificar` excluye el grupo `motor` y eso es temporal por diseño.** Es lo que permite
  cumplir a la vez las dos reglas que aquí chocan: que los diez casos fallen y que el repositorio
  nunca quede en rojo. **F2-02 quita la exclusión en su primer paso**, y así está escrito en el
  backlog. Si esa tarea se aplaza, la exclusión no puede quedarse suelta: `composer motor` es lo que
  la mantiene visible.
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
- **El texto del hueco del saldo de vacaciones se lee en pantalla y sigue siendo tuyo.** Está en
  `FichaDeEmpleado::saldoDeVacaciones()` y **deja de ser un hueco en F2-02**, que es la sesión
  siguiente.
- **La fecha de salida no existe todavía.** `Vinculacion::fechaDeSalida()` devuelve `fecha_fin` de
  una vinculación inactiva. Está anotado en F2-09.
- **El menú del cliente no tiene «Usuarios y permisos» ni «Facturación», y ninguna tiene tarea en el
  backlog.** Crear sus administradores no se puede desde ninguna parte.
- **Sigue sin existir «Editar contrato».** El aviso del modal de renovación dice «edita el contrato»
  sin enlazar a ninguna parte.
- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Hay nueve EPS intervenidas por la Supersalud. Se editan desde la pestaña «EPS y entidades».
- **Verificar el calendario de festivos antes de vender.** Cincuenta y tres fechas cargadas para
  2025, 2026 y 2027, quince minutos, y se corrigen desde el panel. **Ahora pesa más que ayer**: el
  motor de la fase 2 cuenta días hábiles de vacaciones leyendo esa tabla.
- **Vigencia del enlace de invitación: siete días.** En `Invitaciones::DIAS_DE_VIGENCIA`, y también
  visible en la pestaña «Umbrales». **Confírmalo.**
- **El rol de conexión de PostgreSQL.** `.env` se conecta como `postgres`, superusuario con
  `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace falta un rol
  corriente con `SELECT, INSERT, UPDATE, DELETE`.
- Abierta de veintinueve sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- **La siguiente es F2-02 y su primer paso está escrito en el backlog: quitar `--exclude-group=motor`
  del script `verificar` y borrar el script `motor`.** A partir de ahí, la verificación exige los
  diez en verde, que es como debe ser.
- **F2-02 no toca `tests/Feature/MotorDeCalculoTest.php`.** Si una prueba estorba, la conversación es
  sobre la regla, no sobre la prueba. Lo que sí cambia es `app/Dominio/Calculo/`: cada
  `throw CalculoSinImplementar` se sustituye por su cuenta, y la clase de la excepción se borra
  cuando se quede sin quien la lance.
- **Las firmas ya decididas**, para no rediseñarlas a mitad: `MotorDeCalculo::diasHabilesVacaciones()`,
  `diasCorridos()`, `acumulacionMes()`, `diasSuspendidosDelMes()`, `acumulacionProporcional()`,
  `cumpleMesesEl()`, `diaHabilAnteriorParaAlertas()` y `diasDeAnticipacion()`; más
  `SaldoDeVacaciones::de()->al()` y `->faltantePara()`, y `PeriodoDePrueba::de()->dias()` y
  `->terminaEl()`.
- **`acumulacionMes()` es la única firma que hoy no se puede poner en verde entera**: le faltan los
  días de suspensión, que viven en una tabla que llega con F2-07. Su caso de hoy es el mes sin
  suspensiones, que da 1,25. La aritmética de la proporción sí está cubierta por
  `acumulacionProporcional()`.
- **Hay dos conteos de días corridos escritos a mano esperando al motor**, uno en
  `HistorialDeContrato::duracionTotalEnDias()` y otro en `RenovacionDeContrato::diasCorridos()`,
  idénticos y los dos anotados. Cuando exista `diasCorridos()`, los dos pasan a llamarlo y el privado
  de `RenovacionDeContrato` desaparece.
- **El motor va a leer `Festivo`, y `Festivo` ya tiene borrado lógico.** `esFestivo()` y
  `fechasDelAnio()` excluyen solos a los quitados, así que no hace falta acordarse en cada consulta.
- **REV-01 y REV-02 siguen pendientes.** El plazo que fijó la revisión sigue en pie: **antes de
  F2-04**, es decir después de F2-02 y F2-03.
- **Los helpers de prueba están repartidos y Pest los comparte**: `enLaEmpresa()` en
  `PersonasYVinculacionesTest.php`, `usuarioDe()` en `RolesYPermisosTest.php`, `ownerVerificable()`
  en `PanelDelProveedorTest.php`, `ownerEnElPanel()` y `datosDeEmpresa()` en `CrearEmpresaTest.php`,
  `enEmpresa()` con `empresaConEmpleados()` en `PanelDeEmpresasTest.php`,
  `departamentoConMunicipio()` en `PanelDeCatalogosTest.php` y ahora `enLaEmpresaDelMotor()` con
  `vinculacionConSaldoDeCorte()` en `MotorDeCalculoTest.php`. **Una prueba que se ejecute sola con
  `--filter` no los encuentra: hay que correr la suite.**
- **`App\Presentacion\Formato` es el único sitio donde se escribe una fecha, un número de días o un
  peso.** El motor devuelve números; quien los pinta es `Formato`.
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
