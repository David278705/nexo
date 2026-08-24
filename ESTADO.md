# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F5-02 (ejecución de la carga)
**Verificación:** verde — Pint, PHPStan sin errores, **1.018** pruebas de Pest, build y 296 de Vitest.
**Siguiente tarea:** F5-03 · Ambiente de demostración.

## Qué se hizo

- **F5-02, la ejecución de la carga inicial.** `EjecucionDeCarga`, `ResultadoDeLaCarga`,
  `CargaRechazada`, `HistorialDeCargas`, la tabla `cargas_iniciales` con su modelo `RegistroDeCarga`
  y su factory, el `escribir()` de las seis plantillas y el comando
  `carga:ejecutar {empresa} {plantilla} {archivo}`.
- **La pantalla de puesta en marcha del panel**, que era la que F5-01 dejó sin construir a propósito:
  `CargaInicialController` con sus tres rutas, `App\Proveedor\PuestaEnMarcha`, el `FormRequest`
  `CargarPlantilla` y `Pages/Proveedor/PuestaEnMarcha.vue` con su prueba de Vitest. Los seis pasos del
  documento 2, 6.1, están completos de punta a punta: subir, validar sin escribir, ver el informe por
  fila, confirmar, la transacción y la constancia.
- **El «hecho cuando» está probado literalmente.** Con el corte a fin de mes, quien informa 17,25 días
  el 31 de agosto tiene 17,25 ese día y 21,0 el 30 de noviembre: el saldo de la plantilla más 1,25 por
  cada uno de los tres meses cerrados desde el corte.
- **Se aprovechó el trabajo sin commitear que había en `app/CargaInicial/`** —la ejecución, el
  registro, el historial y los `escribir()`—, que estaba bien razonado y le faltaba todo lo de
  alrededor: las pruebas, el caso de aislamiento, la pantalla y sus rutas. Lo único que se le corrigió
  fueron dos usos innecesarios de `?->` que dejaban PHPStan en rojo.

## Decisiones tomadas

- **La ejecución no recibe el informe que se enseñó: recibe el archivo y lo vuelve a simular entero.**
  Entre validar y confirmar pasan minutos, y en esos minutos alguien pudo desactivar una EPS, subir
  otro archivo o cargar la misma plantilla desde otra pestaña. Lo que se escribe es exactamente lo que
  se acaba de validar. De paso es lo que permite no guardar el Excel en ninguna parte (documento 1,
  13.1): llega, se lee dos veces y se va. El precio es que el archivo se sube dos veces, y la pantalla
  lo hace sin volver a pedirlo porque el navegador conserva el que ya está elegido.
- **Cada plantilla escribe por donde escribe su módulo**, por el mismo motivo por el que valida con
  las reglas de su formulario: una fila cargada y una digitada tienen que acabar idénticas.
  `PlantillaDeMovimientos` es la única excepción y está razonada en su propio comentario:
  `Vinculacion::registrarMovimiento()` actualiza además el cargo y el salario vigentes tomando el
  «anterior» de lo que rige hoy, y en un histórico eso es exactamente al revés.
- **La dependencia entre plantillas se comprueba contra `cargas_iniciales`, no contra la planta.** Una
  empresa puede tener empleados registrados por pantalla sin haber pasado nunca por la carga inicial;
  contra la planta, la comprobación diría que sí a algo que nunca ocurrió.
- **Volver a pasar la plantilla de empleados se rechaza fila por fila**, con el documento ya vinculado
  como error del archivo. Cargarla dos veces no falla por sí solo: abre una segunda vinculación activa
  a cada persona, y eso no se nota hasta que el listado enseña la planta entera repetida.
- **El corte del saldo acota también el disfrute, no solo la acumulación.** Los «días disponibles hoy»
  que informa la empresa ya vienen con lo disfrutado descontado, así que una vacación anterior al
  corte cargada en el histórico se restaría dos veces. Sin esto, cargar la plantilla 4 —que es
  opcional— le habría cambiado el saldo a todo el mundo.
- **La pantalla enseña la tabla de las seis plantillas en vez de los cuatro pasos del prototipo.** Una
  puesta en marcha no es una fila de pasos que se recorre una vez: es el mismo ciclo repetido hasta
  seis veces, a veces en dos reuniones. Lo que hace falta al abrirla no es saber en qué paso se quedó,
  sino qué plantillas de esa empresa ya entraron, con su fecha y sus filas. Eso es además el «resumen
  final» del paso 5 del documento 3, 5.8.
- **Validar y cargar son dos rutas distintas y no una con una casilla.** La única diferencia que
  importa en toda la pantalla es que una escribe y la otra no; una casilla mal marcada sería una carga
  que entra sin que nadie la haya confirmado.
- **El archivo se comprueba por su extensión y no por su tipo MIME.** Un `.xlsx` es un zip por dentro
  y el olfateo del sistema lo dice, así que `mimes:xlsx` rechazaría archivos correctos según la
  máquina donde corra. La comprobación de verdad es que el lector lo abra.
- **`HistorialDeCargas` recorre las empresas una por una**, con `ContextoTenant::paraTenant()`. No es
  solo disciplina: `cargas_iniciales` tiene RLS, así que una consulta global no devolvería nada. El
  `owner` cruza empresas entrando en cada una, no saltándose la puerta.

## Preguntas para Angela

- **Las tres de F5-01 siguen abiertas**: «Sura» contra «Sura EPS» y «ARL Sura» en la fila de ejemplo
  de la plantilla 1; el código de diagnóstico que la plantilla 3 marca opcional y el sistema exige; y
  el rango «Menos de 30 min» de la plantilla 2, que el sistema parte en dos.
- **Heredadas, las cinco de sesiones anteriores**: los tres nombres del filtro «Estado del contrato»,
  el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».

## Decisiones para David

- **Un contrato a término fijo cargado con prórrogas anteriores arranca contando cero prórrogas.** Las
  dos columnas de la plantilla 1 —«Fecha de inicio del contrato vigente» y «Prórrogas ya realizadas»—
  se leen y se comprueban, pero no se convierten en tramos de `periodos_contractuales`: con un número
  y una sola fecha habría que inventarse las fechas de los tramos intermedios, y 13.1 lo prohíbe con
  esas palabras. Lo que esto afecta es el cupo de tres prórrogas cortas y el tope de cuatro años en
  quien ya venía renovando. La salida limpia es agregarle a la plantilla las fechas de cada tramo, no
  deducirlas.
- **La plantilla 1 sigue sin país de expedición del documento**, así que la carga solo admite
  municipios de Colombia en esa columna. Quien tenga el documento expedido en el exterior habrá que
  completarlo por pantalla.
- **`REV-06` sigue tuya: acotar la regla de «la fecha entre paréntesis».**
- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Hasta que eso se haga, ningún correo sale de la máquina.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F5-03, el ambiente de demostración** (documento 4, capítulo 10): una empresa
  ficticia poblada, un proceso que la restaura cada noche y accesos de prueba con vigencia limitada.
  La carga inicial ya construida es la vía natural para poblarla, pero **el `escribir()` de cada
  plantilla no es un seeder**: espera filas ya validadas y un archivo de Excel detrás.
- **La sección «Demostración» del panel sigue siendo una pantalla pendiente**, igual que
  Suscripciones, Alertas, Salud y Equipo. `SeccionDelPanel::pendientes()` es la lista y de ella salió
  `PuestaEnMarcha` en esta sesión.
- **El historial de cargas no ofrece «Ver informe» de una carga anterior**, que el capítulo 5 del
  documento 4 menciona. No se construyó porque el informe no se guarda: el Excel no se conserva y de
  la carga solo queda el nombre del archivo y el número de filas. Guardar el informe de errores de una
  carga que sí entró tampoco tendría contenido —entra cuando no hay errores—; lo que tendría sentido
  es guardar el de una simulación fallida, y eso es una decisión de producto, no un olvido.
- **`User`, `Acceso` e `Invitacion` siguen sin `SoftDeletes`**, abierto desde F1-13.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a la anterior.
