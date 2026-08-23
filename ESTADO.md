# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F4-05 (punto de control, revisión de la fase 4)
**Verificación:** verde — Pint, PHPStan sin errores, **964** pruebas de Pest, build y 286 de Vitest.
**Siguiente tarea:** F5-01 · Lector de plantillas.

## Qué se hizo

- **F4-05, el punto de control.** Informe en `bitacora/revision-2026-08-23.md`. El alcance fueron
  **once tareas**, no cuatro: la fase 3 entera —el motor de alertas, que nunca tuvo punto de control
  propio—, `REV-05` y las cuatro de la fase 4.
- **Aislamiento, seguridad y datos: sin hallazgos.** Las doce tablas de negocio con trait, RLS y caso
  propio. Lo que F2-11 dejó señalado como el riesgo de la fase 3 —el contexto de empresa en segundo
  plano— está bien resuelto: `EnviarAlerta` no usa `SerializesModels` y restablece el contexto a mano
  desde dos números, `failed()` hace lo mismo, y el proceso diario recorre empresa por empresa.
- **Arreglado: «Días disfrutados en agosto» contaba los días pactados y no los vividos.**
  `IndicadoresDeVacaciones` recortaba el periodo solo por la fecha de inicio, así que una vacación del
  20 al 30 de agosto aportaba sus ocho días hábiles el día 23. Ahora el fin se recorta contra hoy, con
  el mismo idioma que `IndicadoresDeAusentismo` ya usaba para una incapacidad abierta. Caso nuevo en
  `PanelDeIndicadoresTest`: del 10 al 21 de agosto, mirado el lunes 17, son seis días y no diez.
- **Arreglado: la guardia de aislamiento del panel vigilaba uno de los seis bloques.** Solo comprobaba
  Planta. Hay un caso nuevo que siembra la misma escena en dos empresas —las ocho tablas de las que
  leen los seis bloques— y comprueba las nueve cifras.
- **Documentado: las dos cuentas de días de `VigenciaDelContrato`.** Cuentan fuera del motor y la clase
  no decía por qué. Ahora lo dice: son distancias con signo que solo alimentan texto de pantalla, y
  ninguna cifra con consecuencia legal se cuenta ahí.
- **`REV-06` es la tarea nueva**, al final del backlog.

## Decisiones tomadas

- **El recorte de una novedad en curso se decide igual en los dos bloques que lo necesitan, pero sin
  compartir función de conteo.** Ausentismo cuenta días corridos y vacaciones días hábiles: fundirlas
  en una función con un parámetro sería la regla 3 al revés. Lo que se igualó es la decisión —hasta
  hoy, no hasta el fin del periodo—, no la aritmética.
- **`VigenciaDelContrato::diasParaVencer()` no se movió al motor.** Necesita una distancia **con
  signo**, que ni `diasTranscurridos()` ni `diasDeAnticipacion()` dan, y añadir una tercera dejaría
  cuatro funciones de calendario casi sinónimas: la manera de que algún día se escoja la equivocada.
  Solo alimenta texto de pantalla; el número que va en un correo lo sigue calculando el motor.
- **El `delete()` de `CacheDeSaldos` es la única excepción a la regla 4 y no es un descuido:** está
  declarada y argumentada en su migración, junto con la de no auditarse.

## Preguntas para Angela

- **Heredadas, las cinco de sesiones anteriores**: los tres nombres del filtro «Estado del contrato»,
  el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».

## Decisiones para David

- **`REV-06`, nueva y tuya: acotar la regla de «la fecha entre paréntesis».** Es la **tercera**
  revisión que la levanta. La regla de `.claude/rules/30-interfaz.md` la enuncia para toda la interfaz;
  su fuente, el documento 1, 9.1, solo para los correos, donde el código la cumple literalmente. En
  pantalla no la ha cumplido nunca y la fase 4 amplió la distancia. La recomendación está escrita en la
  tarea, y también lo que costaría decidir lo contrario.
- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Todo el motor de alertas está construido y probado;
  hasta que eso se haga, ningún correo sale de la máquina. Es lo único que separa al producto de
  cumplir su promesa.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), la plantilla de carga, el fondo de cesantías, la prueba intermitente de
  `CatalogosTest`, el texto del empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la
  frase del prototipo sobre el saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F5-01, el lector de plantillas.** Arranca la fase 5 y es el primer sitio
  donde entran datos que nadie digitó. `saldo_inicial` y `fecha_corte_saldo` existen en el motor desde
  F2-02 justamente para eso: la carga no debe inventar periodos falsos para cuadrar un saldo.
- **`REV-03` ya no está bloqueada.** David respondió las dos preguntas y la tarea trae la regla
  escrita: eliminar es volver al pasado, y solo se puede deshacer el último de la lista. Sigue al final
  del backlog, ahora con todo lo que necesita.
- **`REV-04` y `REV-06` también siguen al final**, sin dependencias.
- **`User`, `Acceso` e `Invitacion` siguen sin `SoftDeletes`**, abierto desde F1-13.
  `DestinatariosDeAlertas` le dio un motivo nuevo: la lista de quién recibe cada alerta se deduce de
  los usuarios de la empresa, y hoy no hay forma de sacar a alguien de ella sin borrarle la fila.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
