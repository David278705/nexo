# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F3-06 (cerrada: entregabilidad y registro de envíos)
**Verificación:** verde — Pint, PHPStan sin errores, **876** pruebas de Pest, build y 276 de Vitest.
**Siguiente tarea:** F4-01 · Exportación y filtros por módulo. **Con F3-06 termina la fase 3**: las
trece alertas del capítulo 9 están construidas, salen por un proveedor transaccional y quedan
consultables.

## Qué se hizo

- **Se recuperó el trabajo a medias de F3-06 sin `git stash pop`.** El comando no estaba disponible en
  esta sesión, así que los quince archivos del stash se reconstruyeron leyéndolos con `git show` de
  los dos commits del stash —el de los modificados y el de los no seguidos— y escribiéndolos a mano.
  El resultado es el mismo contenido, con dos diferencias deliberadas que se explican abajo.
- **`ProveedorDeCorreo`**: si el transporte configurado es transaccional y si el remitente ya salió del
  dominio de ejemplo. Es lo único del proveedor que se puede comprobar desde dentro; SPF, DKIM y DMARC
  viven en el DNS y ningún código los ve. Advierte y no bloquea.
- **`.env.example` explica el proveedor de producción**: las cinco líneas de Postmark, Resend o SES
  —los tres hablan SMTP, así que cambiar de uno a otro es cambiarlas—, los tres registros de DNS que
  faltan y el comando para comprobarlo.
- **`alertas:prueba <empresa o NIT> <correo>`**, el comando de `ProbarEntregabilidad`: manda el correo
  de prueba **por el mismo camino que las alertas de verdad** y de forma síncrona, para poder mirar el
  resultado ahí mismo sin arrancar un trabajador de la cola.
- **`AlertaPorEnviar::deEntregabilidad()`**, con clave propia que lleva la hora. Es el único envío del
  sistema que no es idempotente por día.
- **`TipoDeAlerta::PruebaDeEntregabilidad` y `esDelCalendario()`**: un caso que no es una alerta,
  debajo de la línea, del módulo `Configuracion`.
- **El panel de alertas**: el módulo `Alertas` en el grupo «Sistema», `AlertasDelUsuario`,
  `ListadoDeAlertas` sobre el armazón de `Listado`, `AlertaController`, la ruta `GET /alertas` y la
  pantalla `Alertas/Index` sobre `useListado`, con el detalle en un modal. Ninguna ruta de escritura.
- **`Formato::fechaYHora()`**, la única fecha de la casa con hora.
- **`tests/Feature/PanelDeAlertasTest.php`, 25 casos**, más el de `EstructuraTest` que comprueba que la
  sección de alertas lleva a su panel y no a una pantalla pendiente: el «hecho cuando», el comando, el registro de
  envíos, el canal alterno con sus cinco casos de visibilidad y aislamiento, los filtros, el proveedor
  y las dos guardas del tipo que no es del calendario.

## Decisiones tomadas

- **El fallo que la sesión pasada dejó anotado en `PanelDeAlertasTest.php:240` era de la prueba, no del
  código, y se entendió antes de tocarlo.** El caso comparaba «Ana Torres» contra el nombre completo
  que enseña la columna «Sobre», y `PersonaFactory` rellena el segundo nombre y el segundo apellido, así
  que `nombreCompleto()` devolvía cuatro palabras. La corrección es que el ayudante
  `empleadaConNombre()` use el estado `sinSegundosNombres()` que la fábrica ya tenía: si la prueba
  dice de qué nombre habla, tiene que conocer el nombre entero. **No se tocó `nombreCompleto()` ni la
  fila del listado**, que hacen lo correcto.
- **`esDelCalendario()` no se quedó sin consumidor.** El stash lo traía declarado y sin usar; en vez de
  borrarlo se le dio la guarda para la que existe: una prueba comprueba que **ninguna regla registrada
  en el catálogo dispare un tipo que no es del calendario**, que es el error con consecuencias reales
  —una regla registrada con este tipo mandaría correos de prueba a los clientes todas las mañanas— y
  otra comprueba que sea el único caso fuera del calendario.
- **El correo de prueba se guarda en `alertas_enviadas` y no en una tabla aparte.** Esa tabla *es* el
  registro de envíos de la sección 5.5, y un correo de prueba que no dejara rastro no probaría
  justamente lo que se está probando: que lo enviado queda registrado.
- **La clave del correo de prueba lleva la hora, y eso rompe la idempotencia a propósito.** La regla
  5.2 protege de que el proceso diario mande dos veces lo que nadie le pidió. Aquí ocurre lo
  contrario: quien está corrigiendo el SPF de su dominio manda, mira la bandeja, corrige el registro
  DNS y vuelve a mandar, todo la misma mañana, y cada intento tiene que dejar su propia fila.
- **El panel enseña exactamente lo que el correo habría entregado.** `AlertasDelUsuario` usa el mismo
  `TipoDeAlerta::modulo()` que `DestinatariosDeAlertas`, así que el canal alterno y el buzón no pueden
  discrepar. Tener el módulo `Alertas` es el permiso para entrar a la pantalla; lo que hay dentro lo
  siguen decidiendo los módulos que el usuario ya tenía. El filtro ofrece solo los tipos visibles:
  una lista completa contaría qué alertas existen y dejaría deducir por el número de resultados si a
  alguien le llegaron.
- **El detalle viaja con la fila.** El asunto, el cuerpo y los destinatarios son los que se guardaron
  el día del envío y no cambian nunca más, así que una segunda vuelta al servidor traería lo mismo. Y
  son los que se enviaron, no los que se calcularían hoy.
- **El listado abre por lo más reciente**, al revés que los otros ocho, porque un registro de envíos se
  consulta siempre por lo de ayer o lo de la semana pasada.
- **`Alertas` es el único módulo que no está en la tabla 4.2 del documento 3**, y no es un olvido: lo
  pide el documento 2, sección 5.5, que se escribió después. Va en «Sistema» al lado de Auditoría
  porque es lo mismo que ella: un registro de lo que el sistema hizo, de solo lectura.
- **No se agregó un caso nuevo a `AislamientoTest`** porque `alertas_enviadas` ya tiene el suyo desde
  F3-01, con los cuatro caminos. El aislamiento de la ruta real `/alertas` se prueba en
  `PanelDeAlertasTest`, como hacen los otros módulos con la suya.

## Preguntas para Angela

- **Del correo de prueba, para confirmar y sin que frene nada: su texto.** Lo redactó el sistema y va a
  la bandeja del cliente cuando se le configura el dominio, así que dice qué es, para qué sirve y pide
  marcarlo como legítimo si cayó en no deseado. La decisión de fondo —que exista un correo de prueba y
  que quede registrado— es del documento 2, no suya.
- **Heredadas, las tres de sesiones anteriores**: los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».

## Decisiones para David

- **El proveedor transaccional está preparado pero no elegido.** El código funciona con los tres
  candidatos sin cambiar una línea —los tres hablan SMTP— y `.env.example` dice qué poner en cada
  caso, pero **hay que escoger uno, comprar o apuntar el dominio y crear SPF, DKIM y DMARC**. Hasta que
  eso pase, `advertencia()` seguirá diciendo que lo configurado no sirve para producción, que es la
  verdad. Es una decisión comercial y de infraestructura, no de código, y es la única parte del «hecho
  cuando» que ninguna prueba puede cubrir: que llegue a **bandeja de entrada** y no a no deseado se
  comprueba mandando el correo de verdad.
- **Todas las heredadas siguen abiertas**: la regla de la fecha entre paréntesis fuera de los correos,
  meses comerciales de treinta días, prórroga mínima, plantilla de carga, fondo de cesantías, la
  prueba intermitente de `CatalogosTest`, el texto del empleado inactivo, PHP 8.2 contra el 8.3 que
  pide `CLAUDE.md`, la frase del prototipo sobre el saldo negativo, y el resto de la lista larga de
  REV-02.

## Notas para la próxima sesión

- **`stash@{0}` sigue en el repositorio y ya no sirve para nada. Se puede borrar.** Su contenido está
  en el commit de F3-06, reconstruido a mano porque `git stash pop` no estaba disponible en esta
  sesión; `git stash drop` tampoco lo estaba. **No hay que aplicarlo**: traería la versión vieja de
  `TipoDeAlerta` —con `ObraOLaborProlongada`— y volvería a meter el fallo del nombre completo en
  `PanelDeAlertasTest`.
- **Con F3-06 se cierra la fase 3.** Las trece alertas del capítulo 9 están construidas y probadas, y
  ahora además salen por el camino que pide la sección 5.5 y quedan consultables.
- **F4-01 es la siguiente y ya tiene deuda esperándola por escrito.** Tres módulos de novedades
  —incapacidades, vacaciones y licencias—, movimientos y terminaciones dejaron anotado que les faltan
  los filtros «Empleado» y «Cargo» del documento 1, y a incapacidades además la búsqueda por nombre.
  Todo eso es F4-01. El panel de alertas **no** entra: no exporta y sus tres filtros son los suyos.
- **El panel de alertas no tiene exportación y es una decisión, no un olvido.** El documento 2, 5.5
  pide un registro «consultable», y el capítulo 11 no lo lista entre los módulos que exportan. Si
  F4-01 decide que sí, `ListadoDeAlertas` ya arma la consulta única que necesitaría.
- **El sitio que hay que mirar cuando llegue una empresa grande** sigue siendo el mismo: los dos
  resúmenes de vacaciones y `CercaniaAlTope`, que recorren a todos los activos porque el saldo es una
  función y no una columna. F4-02 —la caché de saldos— es lo que lo alivia.
- **`REV-03`, `REV-04` y `REV-05` siguen al final del backlog. `REV-05` no es opcional**: hoy el
  sistema le manda a un empleado de obra o labor las alertas de periodo de prueba, que no le
  corresponden por ley. Y ahora se nota más, porque el panel de alertas las enseña en pantalla al lado
  de las que sí le tocan.
- **Aviso para F5-03:** el ambiente de demostración nace `en_prueba` y hoy no recibe alertas. Si tiene
  que enseñar correos, esa tarea cambia una línea en
  `ProcesoDiarioDeAlertas::empresasQueRecibenAlertas()`.
- **Aviso para F5-05 y F5-06:** la consola del proveedor —todas las empresas, con reenvío— y la tarjeta
  de salud del sistema ya tienen de dónde leer. `ProveedorDeCorreo::advertencia()` es la tarjeta, y
  `AlertaEnviada` guarda destinatarios, estado, intentos y motivo del fallo desde F3-01.
