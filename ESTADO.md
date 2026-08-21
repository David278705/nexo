# Estado del proyecto

**Última sesión:** 2026-08-20 · Tarea F1-12
**Verificación:** verde
**Siguiente tarea:** F1-13 · PUNTO DE CONTROL — revisión (ejecuta el skill `revisar`)

## Qué se hizo

- **`ParametrosDeLaEmpresa`**, el objeto de la pantalla: los cuatro parámetros del capítulo 4 del
  documento 1 con su título, su explicación en lenguaje claro, sus dos opciones dichas con palabras,
  sus reglas de validación y la escritura. Todo en un archivo.
- **Tres parámetros se guardan y el cuarto se deduce.** Sábado laboral, efecto de las suspensiones y
  perfil sociodemográfico son columnas de `tenants`, que ya existían desde F0-03. «Correos que reciben
  las alertas» no es columna de nada: el capítulo 4 dice que sus opciones son «administradores con
  acceso a cada módulo», así que la lista sale de los usuarios y de sus permisos.
- **`ConfiguracionController`** con las dos acciones, `GuardarParametros` con las reglas, y las rutas
  `GET /configuracion` y `PUT /configuracion/parametros` bajo `modulo:configuracion`.
- **`Tenant::usuarios()`**, la relación que faltaba. `User` no lleva el trait a propósito, y sin la
  relación la lista de destinatarios habría necesitado el `where('tenant_id', …)` que la regla de
  aislamiento prohíbe.
- **La pantalla `Configuracion/Parametros`** con las cuatro pestañas del prototipo. «Parámetros» es un
  solo formulario con un solo botón; «Datos de la empresa» es de solo lectura con la nota de que los
  cambia el proveedor; las otras dos dicen que llegan en su propia tarea.
- **El aviso del capítulo 4 aparece solo al activar**, con el número de empleados ya registrados
  escrito por el servidor, y desaparece cuando el bloque ya estaba habilitado.
- `ParametrosDeLaEmpresaTest` con 26 casos: quién entra, los cuatro parámetros en pantalla, guardar,
  la validación, la auditoría, el efecto inmediato, el aviso, los destinatarios y el aislamiento.
  Nueve casos de Vitest de la pantalla.
- `composer verificar` en verde a la primera: Pint, PHPStan sin errores, 439 pruebas de Pest, build y
  171 de Vitest.

## Decisiones tomadas

- **«Correos que reciben las alertas» no se guarda en ninguna parte.** Es de solo lectura y se cambia
  dando o quitando módulos en Usuarios y permisos. Guardar aparte una lista de correos sería una
  segunda fuente para la misma pregunta, y las dos se separarían la primera vez que un usuario cause
  baja: se seguiría enviando a alguien que ya no tiene acceso.
- **Un administrador sin módulos también aparece en la lista, diciendo que no recibe ninguna alerta.**
  Esconderlo dejaría al superadministrador buscando al usuario que acaba de crear.
- **Sí y No viajan como el texto `si` y `no`, no como booleanos.** Un `select` de HTML manda cadenas y
  la cadena «false» es verdadera en PHP: es el error clásico de un booleano en un formulario. La
  conversión ocurre en un solo sitio, en `guardar()`.
- **Los tres parámetros son un solo formulario y un solo botón**, no tres interruptores que guardan
  solos. Cambiar cómo se cuentan las vacaciones de toda la empresa se confirma a propósito, y así
  queda **una** línea de auditoría con lo que cambió y no una por selector.
- **Guardar es `PUT`.** Se reemplazan tres valores que ya existen; no se crea ningún hecho nuevo.
- **`GuardarParametros::authorize()` vuelve a preguntar por el módulo** aunque la ruta ya lo exija.
  `Configuracion` es el único módulo que no se puede asignar a nadie y esta es la única acción del
  entorno del cliente que escribe en `tenants`: si un día la ruta pierde su middleware por una edición
  distraída, sigue respondiendo 403.
- **El aviso del perfil devuelve nulo en la empresa sin empleados.** Sin nadie registrado no advierte
  de nada, y es justo cuando el parámetro se activa sin consecuencias: en el onboarding.
- **La pestaña «Datos de la empresa» se construyó, de solo lectura.** No tiene tarea propia en el
  backlog y la sección 5.7 del documento 3 la define completa en una línea; dejarla como pestaña
  muerta al lado de la de parámetros era peor que las dos etiquetas y la nota que costó.

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

- **Los parámetros de cálculo todavía no tienen quien los lea.** Sábado laboral y efecto de las
  suspensiones ya se guardan y ya se pueden cambiar, pero **el único consumidor construido es el del
  perfil sociodemográfico**: el motor que los usará es F2-02. El criterio de terminado se probó con
  ese, y las pruebas de los otros dos comprueban que el valor viaja, no que un saldo cambie.
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
- Abierta de veintitrés sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-13, el punto de control de la fase 1**: se ejecuta el skill `revisar`, el
  informe va a `bitacora/` y lo que salga se arregla o se convierte en tareas `REV-` al final del
  backlog. Con la fase 1 completa hay doce tareas que mirar de una vez.
- **Todo parámetro nuevo de la empresa se agrega en `ParametrosDeLaEmpresa::CAMPOS`** y en las cuatro
  tablas de textos de ese archivo. La pantalla, la validación y el guardado lo recogen solos; lo único
  que hace falta aparte es la columna.
- **Los parámetros se leen de `tenant_actual()`, nunca de una copia.** Es lo que hace cierta la
  promesa de que un cambio se refleja de inmediato. Si algún día un cálculo necesita cachearlos, el
  criterio de terminado de F1-12 deja de cumplirse.
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
- **No queda ninguna tarea bloqueada en el backlog.**
