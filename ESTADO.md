# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F3-01
**Verificación:** verde
**Siguiente tarea:** F3-02 · Corrimiento al día hábil

## Qué se hizo

- **La infraestructura del motor de alertas**, que es la promesa que se vende. Con ella arranca la
  fase 3 y aparecen tres cosas que el proyecto no tenía: una tabla de registro de envíos, un comando
  programado y un job en cola.
- **La tabla `alertas_enviadas`** con su índice único `(tenant_id, clave)`, su política RLS y su
  caso en `AislamientoTest`. Guarda el asunto y el cuerpo **ya escritos**: lo que se envió no se
  vuelve a calcular al consultarlo.
- **El catálogo de alertas**, `TipoDeAlerta`, con las trece del resumen del capítulo 9 —nueve
  individuales y cuatro de resumen mensual—, cada una con su módulo, que es lo que decide a quién le
  llega. Las tres «en pantalla, al momento» no están y no deben estar: ya viven en sus pantallas.
- **`ProcesoDiarioDeAlertas`**, que recorre las empresas activas una por una con
  `ContextoTenant::paraTenant()`, evalúa las reglas registradas, registra lo que corresponde y encola
  el correo. No envía: enviar es del trabajador de la cola.
- **`RegistroDeAlertas`**, la idempotencia. No comprueba nada antes de insertar: intenta escribir y
  captura el rechazo del índice único, dentro de su propio savepoint.
- **`EnviarAlerta`**, el job: tres intentos con espera creciente de 60, 300 y 900 segundos,
  restableciendo el contexto de empresa al arrancar y limpiándolo al terminar aunque falle.
- **La plantilla base de correo**, `correos/alerta.blade.php`, una sola para las once alertas:
  encabezado con el nombre del sistema, cuerpo y pie con el nombre de la empresa y el enlace.
- **El comando `alertas:diarias`**, programado a las 06:00 de `America/Bogota` con
  `withoutOverlapping()`, con `--fecha` para reponer un día caído.
- `composer verificar` en verde: Pint, PHPStan sin errores, **760** pruebas de Pest (33 nuevas), build
  y 275 de Vitest. Ninguna prueba existente se tocó.

## Decisiones tomadas

- **La clave de idempotencia se arma con la fecha de envío, no con la del hecho.** Es lo que dice la
  sección 5.2 y es lo que hace que dos corridas del mismo día choquen. La fecha del hecho se guarda
  aparte, en `fecha_del_hecho`, porque es la que va entre paréntesis en el texto y con corrimiento
  las dos son distintas.
- **El proceso evalúa cada día su fecha y las de los días no hábiles que vienen detrás.** Un viernes
  evalúa viernes, sábado, domingo y el lunes si es festivo. La otra cara de la misma regla es que un
  domingo el proceso no evalúa **nada**: ninguna alerta puede tocar un domingo, así que correr ese
  día solo podría mandar un correo el día en que la regla dice que no se manda.
- **Ajustar el texto al adelanto es de F3-02 y no se adelantó aquí.** La regla de disparo recibe las
  dos fechas ya resueltas —la del disparo y la del envío— y con eso puede decidir si escribe «mañana»
  o «en dos días». Lo que falta es la regla, no el dato.
- **`esDiaHabilParaAlertas()` se agregó al motor**, no al proceso. Es una pregunta del calendario
  laboral y la regla del motor prohíbe contar días fuera de `app/Dominio/Calculo/`. Es
  `diaHabilAnteriorParaAlertas()` vista al derecho y las dos comparten implementación.
- **«Empresas activas» se leyó como el estado `activa` de `EstadoDeEmpresa`.** Deja fuera a las
  suspendidas —la suspensión es por mora— y también al ambiente de demostración, que nace `en_prueba`
  y es de F5-03. Está anotado abajo como aviso para esa tarea.
- **Sin destinatarios no se registra ni se encola.** Un registro sin nadie a quien mandárselo no es
  evidencia de nada y además quemaría la clave del día: la alerta quedaría marcada como enviada sin
  haber salido.
- **`alertas_enviadas` no se audita**, por lo mismo que `accesos`: ya es el registro de lo que pasó,
  lo escribe el proceso y no una persona, y ninguna pantalla lo edita. Auditarlo dejaría tres líneas
  sin autor por cada correo.
- **El catálogo de reglas está vacío a propósito.** Es un singleton en `AppServiceProvider` donde
  cada tarea de la fase 3 suma una línea. El armazón se prueba con `Tests\Fixtures\ReglaDePrueba`,
  la gemela de `RegistroDePrueba` en el aislamiento.

## Preguntas para Angela

- (vacío, ninguna nueva.)

## Decisiones para David

- **La regla de «la fecha entre paréntesis» sigue sin acotar y ya no tiene margen: la primera
  plantilla existe.** `.claude/rules/30-interfaz.md` la enuncia para toda la interfaz; su fuente
  —documento 1, 9.1— la enuncia solo para los mensajes de las alertas. La plantilla base de F3-01 no
  redacta nada, así que la decisión sigue barata; los textos literales los escribe F3-03, y ahí ya
  cuesta.
- **Todas las heredadas siguen abiertas**: meses comerciales de treinta días, prórroga mínima,
  plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, la frase del prototipo sobre el saldo
  negativo, y el resto de la lista larga de REV-02.

## Notas para la próxima sesión

- **La siguiente es F3-02, el corrimiento al día hábil.** La mitad del calendario ya está hecha: el
  proceso adelanta la evaluación y `fecha_programada` sale corrida. Lo que falta es el **texto**, y
  eso vive en las reglas de disparo, que todavía no existen. Habrá que decidir si F3-02 se resuelve
  con una prueba sobre `ReglaDePrueba` y una utilidad de redacción compartida —del estilo de
  `MotorDeCalculo::diasDeAnticipacion()`, que ya está escrito y probado desde F2-02— o si se apoya en
  la primera regla real.
- **El punto que hay que mirar en el próximo control:** el contexto de empresa en segundo plano.
  `EnviarAlerta` serializa dos identificadores y no un modelo, justamente porque el trabajador
  arranca sin empresa y el global scope no encontraría la fila. Está probado —el job corre sin
  contexto y lo deja limpio al terminar— pero es el sitio donde una fuga sería más fácil.
- **Aviso para F5-03:** el ambiente de demostración nace `en_prueba` y hoy no recibe alertas. Si el
  ambiente tiene que enseñar correos, esa tarea cambia una línea en
  `ProcesoDiarioDeAlertas::empresasQueRecibenAlertas()`, no el resto del proceso.
- **`REV-03` ya no está bloqueada**: David dejó escrita la decisión en el propio backlog —eliminar es
  volver al pasado, y solo se puede deshacer el último registro del historial—.
- **`REV-04`, la pantalla de auditoría, sigue al final del backlog** y F5-07 la da por hecha.
- Las notas de arquitectura de REV-01/REV-02 y las de la fase 2 sobre el flujo compartido de novedades
  siguen vigentes y no se repiten aquí.
