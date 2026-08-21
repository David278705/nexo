# Estado del proyecto

**Última sesión:** 2026-08-21 · Tarea F1B-04 · Catálogos y constantes desde el panel
**Verificación:** verde
**Siguiente tarea:** F2-01 · Pruebas del motor, primero

## Qué se hizo

- **Agregar una EPS ya no requiere desplegar código**, que era el «Hecho cuando» de la tarea. Se da
  de alta desde el panel y se ofrece en el formulario de empleado de **todas** las empresas en la
  petición siguiente, porque `Entidades::paraFormulario()` lee la tabla y no una copia. Hay prueba de
  ese camino completo.
- **La pantalla `Proveedor/Catalogos` tiene las cinco pestañas del prototipo**: constantes legales,
  festivos, EPS y entidades, ciudades y umbrales. Tres escriben y dos solo leen.
- **Las cinco listas de entidades se administran igual y con el mismo código.** `CatalogosDelSistema::ENTIDADES`
  es el único sitio donde está escrito qué listas hay; la pantalla, la validación y las tres acciones
  salen de ahí, así que una lista nueva es un renglón.
- **Nada se borra.** En entidades y municipios la acción es «desactivar»: dejan de ofrecerse y siguen
  leyéndose en los empleados que ya los tenían. Los festivos estrenaron `deleted_at` —eran la única
  tabla de catálogo sin borrado lógico— y **volver a cargar una fecha quitada revive su fila** en vez
  de chocar con el índice único de `fecha`. Lo resuelve `Festivo::registrar()`, el único camino por
  el que la aplicación escribe esa tabla.
- **El calendario avisa a tiempo:** desde octubre si falta el año siguiente, como pide el capítulo 8,
  y en cualquier mes si falta el del año en curso, que es peor. Los festivos que caen en sábado van
  marcados en la tabla: son los que más se olvidan y los únicos que solo cambian el cálculo en las
  empresas con sábado laboral.
- **Los municipios se miran de a un departamento**, como se eligen en el formulario de empleado.
- **La constancia la escribe sola la auditoría**: el observador ya audita cualquier modelo, así que
  cada alta, corrección y desactivación de catálogo queda con su autor y **sin `tenant_id`**, porque
  son datos globales. Hay prueba.
- `composer verificar` en verde: Pint, PHPStan sin errores, **546** pruebas de Pest (27 más), build y
  **204** de Vitest (7 más).

## Decisiones tomadas

- **Las constantes legales se leen y no se editan.** El prototipo describe esa pestaña como «donde se
  ajusta el sistema cuando cambia una ley», pero `.claude/rules/20-calculo-y-reglas-legales.md` dice
  que esos números viven en `config/reglas_laborales.php`. Editarlos desde una pantalla los sacaría
  de ahí, dejaría el archivo mintiendo y quitaría del camino la revisión que hoy pasa cualquier
  cambio de un número legal. **La pantalla lo dice en su propia nota**, no lo calla. Está abajo para
  David.
- **La norma y la fecha de verificación se declararon en `App\Proveedor\ConstantesLegales`.** En el
  archivo de configuración están en comentarios, que sirven a quien abre el código y no a quien abre
  el panel. **Los valores no se copiaron**: se leen de `config()`, y hay prueba de que cambiar la
  configuración cambia lo que la pantalla enseña.
- **La pestaña «Umbrales» declara y no inventa.** El capítulo 8 lista cuatro y **ninguno de los
  cuatro está decidido**: la anticipación de la alerta de tope (D.3), el contrato de obra prolongado
  (D.2), la vigencia del usuario de demostración y los días de mora. La tabla enseña los umbrales que
  ya existen con el archivo en el que viven, y los que faltan con la decisión de la que dependen.
- **Los festivos pasaron a borrado lógico.** La regla 4 no admite excepciones y hasta hoy nadie podía
  tocar esa tabla. Un festivo que se quita cambia el conteo de días hábiles de todas las empresas a
  la vez: tiene que quedar rastro.
- **Aquí no hay listado paginado y es a propósito.** Las tres listas se miran de a una —una entidad
  de las cinco, un departamento de los treinta y tres, un año de festivos— y ninguna pasa de unas
  decenas de filas. Copiar por cuarta vez el armazón de filtros, orden y paginación antes de que
  `REV-01` lo unifique habría hecho esa tarea más cara, no más barata.
- **El año de un festivo se deduce de la fecha, no se pregunta.** Preguntarlo dejaría escribir un
  festivo de julio archivado en el año anterior, y ese error no se nota hasta que un conteo sale mal
  meses después.
- **La unicidad del nombre de una entidad ignora si está desactivada.** Una EPS liquidada sigue en la
  tabla; darla de alta otra vez dejaría dos filas con el mismo nombre el día que alguien reactive la
  vieja. El mensaje de error dice que se active en vez de agregarla.

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
  nueva —tabla, columna, seeder, un campo en el paso 2 del asistente y un renglón en la ficha—, y
  ahora también **una entrada más en `CatalogosDelSistema::ENTIDADES`**, que es un renglón.
- Menor: nivel educativo quedó con **«Técnico / Tecnólogo» unidos** y **«Posgraduado(a)» sin
  distinguir especialización, maestría y doctorado**, tal como los pediste.
- **B.1 sigue abierta y ahora tiene consecuencia visible.** El límite de prórrogas cortas advierte y
  no bloquea porque así lo pide ese punto. **Confirmada la regla con el asesor laboral, pasa a
  bloquear con una palabra.**
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años. **Ya tienen su sitio
  reservado**: son dos renglones de la pestaña «Umbrales» que hoy dicen «Sin decidir».

## Decisiones para David

- **La pantalla de constantes legales quedó de solo lectura.** El prototipo la describe como el sitio
  donde se ajusta el sistema cuando cambia una ley; la regla del proyecto dice que esos números viven
  en `config/reglas_laborales.php`. **Se siguió la regla.** Si quieres que sean editables desde el
  panel, es una tarea nueva —tabla, migración y un lector que sustituya a `config()`— y hay que
  decidir antes quién puede tocarlos y qué revisión pasan: hoy pasan por un `git diff`.
- **Los umbrales de la salud de uso siguen siendo una propuesta tuya de confirmar.** Están en
  `config/panel.php`: **siete días** para «activa» y **treinta** para «sin uso». Ahora se leen además
  en la pestaña «Umbrales» del panel, marcados como «Propuesto, sin confirmar».
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
- **Los códigos DANE de los municipios siguen nulos.** Ahora **se pueden completar uno por uno desde
  el panel**, pero cotejar los 1.101 contra el archivo del DANE a mano no es razonable: si quieres
  hacerlo de una vez, es una carga masiva y es una tarea nueva. La pantalla lo dice.
- **Un código de segundo factor fallido no queda en la tabla `accesos`.**
- **No hay forma de reemitir un segundo factor perdido.** Hoy se arregla borrando las dos columnas
  del usuario en la base de datos.
- **La regla de «la fecha entre paréntesis» está escrita más ancha de lo que la sostiene el
  documento.** `.claude/rules/30-interfaz.md` la enuncia para toda la interfaz; su fuente —documento
  1, sección 9.1— solo para los mensajes de las alertas. **Se recomienda acotar la regla del archivo
  a los correos. En la fase 3 deja de ser cosmético.**
- **`User`, `Acceso` e `Invitacion` no llevan `SoftDeletes`.** Importará cuando exista «Usuarios y
  permisos». **`Festivo` ya no está en esa lista: lo estrenó en esta tarea.**
- **Los parámetros de cálculo todavía no tienen quien los lea.** El motor que los usará es F2-02, que
  es justo lo que empieza la sesión siguiente.
- **El menú del cliente no tiene «Usuarios y permisos» ni «Facturación», y ninguna tiene tarea en el
  backlog.** Crear sus administradores no se puede desde ninguna parte.
- **Sigue sin existir «Editar contrato».** El aviso del modal de renovación dice «edita el contrato»
  sin enlazar a ninguna parte.
- **El texto del hueco del saldo de vacaciones se lee en pantalla y sigue siendo tuyo.** Está en
  `FichaDeEmpleado::saldoDeVacaciones()` y **deja de ser un hueco en F2-02**.
- **La fecha de salida no existe todavía.** `Vinculacion::fechaDeSalida()` devuelve `fecha_fin` de
  una vinculación inactiva. Está anotado en F2-09.
- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Hay nueve EPS intervenidas por la Supersalud. **Ya no hace falta desplegar para corregirlas**: se
  editan desde la pestaña «EPS y entidades». Sigue siendo una lista global: si está mal, está mal
  para todos los clientes a la vez.
- **Verificar el calendario de festivos antes de vender.** Cincuenta y tres fechas cargadas para
  2025, 2026 y 2027, quince minutos, y **ahora se corrigen desde el panel**.
- **Vigencia del enlace de invitación: siete días.** En `Invitaciones::DIAS_DE_VIGENCIA`, y ahora
  también visible en la pestaña «Umbrales». **Confírmalo.**
- **El rol de conexión de PostgreSQL.** `.env` se conecta como `postgres`, superusuario con
  `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace falta un rol
  corriente con `SELECT, INSERT, UPDATE, DELETE`.
- Abierta de veintiocho sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- **La siguiente es F2-01 y arranca la fase 2: los diez casos obligatorios del motor, que tienen que
  fallar.** Están en `docs/02-arquitectura-y-datos.md`, sección 4.2. La regla es explícita: **la
  prueba va primero** y los diez fallan por falta de implementación, no por error de sintaxis.
- **Hay dos conteos de días corridos escritos a mano esperando al motor**, uno en
  `HistorialDeContrato::duracionTotalEnDias()` y otro en `RenovacionDeContrato::diasCorridos()`,
  idénticos y los dos anotados. Cuando exista `diasCorridos()`, los dos pasan a llamarlo.
- **El motor va a leer `Festivo`, y `Festivo` ya tiene borrado lógico.** `esFestivo()` y
  `fechasDelAnio()` excluyen solos a los quitados, así que no hace falta acordarse en cada consulta.
- **`Festivo::registrar()` es el único camino por el que se escribe el calendario.** Cualquier carga
  nueva —el seeder de un año, la carga masiva del DANE si algún día llega— pasa por ahí o se choca
  con el índice único de `fecha`.
- **REV-01 y REV-02 siguen pendientes, y esta tarea no agregó un cuarto listado a propósito.** El
  plazo que fijó la revisión sigue en pie: **antes de F2-04**.
- **`CatalogosDelSistema` es el sitio de cualquier cosa que la pantalla de catálogos enseñe** y
  **`ConstantesLegales` el de la tabla de constantes.** Una lista nueva es un renglón de
  `CatalogosDelSistema::ENTIDADES` y nada más: la pantalla, la validación y las tres acciones salen
  de ahí.
- **`TablaDeCatalogo` y `ModalDeCatalogo` son de las tres pestañas que escriben.** La tabla es la
  misma para entidades y municipios; el modal, el mismo para las tres altas, con los campos
  declarados desde la pantalla.
- **Toda pestaña nueva de la ficha de empresa se declara en `FichaDeEmpresa::PESTANAS`** y, si
  todavía no tiene contenido, en `PENDIENTES` con la tarea de la que depende.
- **Toda pantalla nueva del panel se envuelve en `ArmazonProveedor`**, nunca en `Armazon` a pelo, y
  **su sección sale de `SeccionDelPanel::pendientes()`** el día que estrene contenido, como acaba de
  salir «Catálogos».
- **`Armazon` ya pinta la prop compartida `estado`.** Un `->with('estado', …)` del servidor se ve
  solo, en las dos aplicaciones.
- **`App\Presentacion\Formato` es el único sitio donde se escribe una fecha, un número de días o un
  peso.**
- **Todo parámetro nuevo de la empresa se agrega en `ParametrosDeLaEmpresa::CAMPOS`** y en las cuatro
  tablas de textos de ese archivo.
- **Toda regla que dependa de una decisión abierta va a `config/`**, con su estado y el punto que la
  sostiene: `validaciones.php` para las legales, `panel.php` para las comerciales. **Y ahora se
  enseña sola en la pestaña «Umbrales»** si se le agrega su renglón.
- **Las frases que la pantalla promete se escriben donde vive el efecto.**
- **Los helpers de prueba están repartidos y Pest los comparte**: `enLaEmpresa()` en
  `PersonasYVinculacionesTest.php`, `usuarioDe()` en `RolesYPermisosTest.php`, `ownerVerificable()`
  en `PanelDelProveedorTest.php`, `ownerEnElPanel()` y `datosDeEmpresa()` en `CrearEmpresaTest.php`,
  `enEmpresa()` con `empresaConEmpleados()` en `PanelDeEmpresasTest.php` y
  `departamentoConMunicipio()` en `PanelDeCatalogosTest.php`. **Una prueba que se ejecute sola con
  `--filter` no los encuentra: hay que correr la suite.**
- **La prueba del segundo factor del panel ya no usa «Catálogos» como sección vacía**: ahora usa
  «Salud del sistema». La siguiente sección que estrene contenido tendrá que mover esa línea otra
  vez.
- **La tabla del historial sigue sin las columnas «Duración» y «Registrado» del prototipo.**
- **No queda ninguna tarea bloqueada en el backlog.** Sí quedan dos `REV-` al final.
