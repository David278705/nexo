# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F5-04 (suscripciones y cobros)
**Verificación:** verde — Pint, PHPStan sin errores, **1.069** pruebas de Pest, build y 316 de Vitest.
**Siguiente tarea:** F5-05 · Consola de alertas del proveedor.

## Qué se hizo

- **F5-04, suscripciones y cobros** (documento 4, capítulo 6). Las tres tablas del capítulo 17.2:
  `planes` —global, sin `tenant_id` ni RLS, como la regla de aislamiento ya la tenía listada junto a
  los catálogos—, `suscripciones` —una por empresa, con índice único parcial— y `pagos` —colgada de la
  suscripción—. Los modelos `Plan`, `Suscripcion` y `Pago`, los enumerados `EstadoDeSuscripcion`,
  `EstadoDeCobro`, `EstadoDeFactura` y `MedioDePago`, la clase `EscaleraDeMora`, y
  `App\Proveedor\Suscripciones`, que es la única que escribe.
- **El «hecho cuando» está probado de punta a punta: suspender bloquea el acceso sin borrar nada y
  reactivar lo restituye.** `BloquearEmpresaSuspendida` es el middleware nuevo del grupo `web`: manda a
  `/suspendido` a cualquier usuario de una empresa suspendida y deja pasar solo esa pantalla y cerrar
  sesión. El `owner` queda fuera a propósito —si no, la suspensión no tendría marcha atrás—. La prueba
  entra al módulo, suspende, comprueba el desvío, comprueba que **el empleado cargado sigue vivo**,
  reactiva y vuelve a entrar sin necesidad de una sesión nueva.
- **La pantalla «Suscripciones» del panel**, con lo que enseña el prototipo: el ingreso recurrente
  mensual, el aviso de quien está en mora, la tabla de las siete columnas y las tres tarjetas —planes,
  distribución por plan y la escalera—. De ahí salen solo las acciones que son de todos los clientes a
  la vez: el catálogo de planes y abrirle suscripción a una empresa que no tiene ninguna.
- **La pestaña «Suscripción» de la ficha de empresa** dejó de ser un hueco: plan, estado de cobro,
  historial de facturas y las cuatro acciones del cliente concreto —registrar un pago, anularlo,
  cambiar de plan, suspender y reactivar—. Está ahí y no en la pantalla del negocio porque es lo que
  hace el propio prototipo: la única acción de su tabla es «ver historial».
- **La columna «Pago» del listado de empresas y su filtro.** «Al día, por vencer o en mora con los días
  de atraso» sale de la suscripción, y con el filtro de estado de pago los cuatro de 4.1 quedaron
  completos. Una empresa sin suscripción lo dice con palabras: «Sin suscripción».
- **`CatalogosDelSistema` cerró la fila de la mora** —ya no dice «sin decidir, llega con F5-04» sino
  dónde vive y que llegar al peldaño no suspende solo— y estrenó la del «por vencer».

## Decisiones tomadas

- **La escalera de mora avisa; no suspende a nadie sola.** Los números son la propuesta literal del
  capítulo 6 —5, 15 y 30 días— y viven en `config/panel.php`, con el precedente de la salud de uso de
  F1B-03. Pero el capítulo dice «los números son una decisión de ustedes» y 17.3 sigue listando la
  escalera entre lo pendiente, así que **automatizar el corte del servicio de un cliente que paga con
  un número sin confirmar sería tomarle a David una decisión que el documento le atribuye**. Llegar al
  último peldaño pone el aviso; el corte lo ejecuta una persona, con su motivo escrito. Hay prueba de
  que mirar la pantalla no le suspende el servicio a nadie.
- **`planes` nace vacía, y es una decisión, no una tarea a medias.** A.1 sigue aplazada a propósito
  —«antes de construir la tabla de precios hay que calcular el costo real por cliente»— y el propio
  prototipo marca sus cifras como «valores de ejemplo». Sembrar tres planes con precios sería tomar
  A.1 en un `seeder`. Los planes se dan de alta desde el panel, que es además la regla del capítulo 8,
  y la tarjeta dice de qué decisión depende la tabla mientras esté vacía.
- **El plan del catálogo se copia a las columnas `plan_*` de la empresa, en la misma transacción.** No
  es una segunda versión del plan: esas columnas existen desde F1B-02 y de ellas leen la columna
  «Plan» del listado, su filtro, el resaltado de «empleados sobre el plan» y el resumen que se le lee
  al cliente. Desde que existe el catálogo manda la suscripción, así que se copian —también al
  corregir un plan, con `refrescarElPlanEnLasEmpresas()`—. Dos sitios que digan planes distintos era
  el problema a evitar.
- **Un pago adelanta el cobro un mes desde la fecha que tenía, no desde hoy.** Pagar el 5 lo que se
  debía el 1 no corre el calendario de cobro del cliente. `addMonthNoOverflow` para que el cobro del
  31 de enero caiga el 28 de febrero y no el 3 de marzo. Anular deshace exactamente eso, y **la fila
  se queda en el historial**: la regla 4 no hace excepciones con la facturación y un número de factura
  que desaparece no se le explica a un contador.
- **No existe el estado «pendiente» en `pagos`.** Una factura sin pagar no se registra como fila: lo
  que dice que hay plata por cobrar es la próxima fecha de cobro ya vencida, que es exactamente el
  indicador de mora. Registrar además la factura pendiente sería contar el mismo hecho dos veces y
  dejar que las dos versiones se contradigan.
- **Ni `suscripciones` ni `pagos` llevan trait ni RLS**, igual que `invitaciones` y `accesos_de_prueba`:
  son tablas del panel del proveedor, se leen con el `owner` —que no tiene empresa en contexto— y
  ninguna pantalla del cliente las muestra. Lo que sí llega al cliente es la constancia: el observador
  de auditoría escribe cada suspensión en la auditoría **de esa empresa**, porque la línea toma el
  `tenant_id` del propio registro. Hay prueba de eso y de que un usuario de empresa recibe 403 en la
  sección y en la acción de suspender.
- **El motivo es obligatorio y sin lista de opciones.** «Con motivo y constancia» pide el documento;
  los motivos de una suspensión —mora, cierre, acuerdo incumplido— no son una lista cerrada, y un
  motivo por omisión es un motivo que nadie escribió. El mínimo de diez caracteres es para que no pase
  un «ok»: esto lo lee el cliente en su auditoría dentro de seis meses.
- **`EstadoDeSuscripcion` tiene dos casos y no tres.** No hay «cancelada»: la salida de un cliente es
  la sección A.4 del documento 6 —exportación, periodo de gracia, eliminación y certificado— y ninguno
  de esos pasos existe como acción del panel.
- **Suspender no cierra la sesión de quien está dentro.** Ve la explicación y a quién llamar, no una
  pantalla de acceso sin motivo. Y el estado se lee de la empresa en cada petición, nunca de la sesión:
  por eso reactivar devuelve el acceso en la petición siguiente.

## Preguntas para Angela

- **Las tres de F5-01 siguen abiertas**: «Sura» contra «Sura EPS» y «ARL Sura» en la fila de ejemplo
  de la plantilla 1; el código de diagnóstico que la plantilla 3 marca opcional y el sistema exige; y
  el rango «Menos de 30 min» de la plantilla 2, que el sistema parte en dos.
- **Heredadas, las cinco de sesiones anteriores**: los tres nombres del filtro «Estado del contrato»,
  el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».
- **D.12 sigue formalmente abierta**: no hay un número sugerido de referencia para la vigencia de un
  acceso de prueba; hoy el formulario no ofrece ninguno y quien lo genera decide caso por caso.

## Decisiones para David

- **La escalera de mora sigue siendo tuya, y ahora está escrita en un solo sitio.**
  `config/panel.php`, clave `cobro.escalera_de_mora`: 5, 15 y 30 días, que es lo que propone el
  capítulo 6. Confirmar o cambiar los tres números es editar ese archivo y nada más. Lo mismo con
  `cobro.dias_por_vencer`, los 5 días con los que la columna «Pago» pasa a ámbar antes del cobro, que
  el documento no fija en ninguna parte.
- **Los dos avisos de la escalera —el de los 5 días y el de los 15— no mandan ningún correo, y no es
  un olvido.** Los correos del sistema son el motor de alertas del capítulo 9, que es de empresas y
  empleados; un aviso de cobro al cliente es otra cosa y no tiene tarea en ninguna fase. Hoy la
  escalera se ve en la pantalla y alguien llama.
- **La tabla de precios (A.1) sigue sin decidir y el catálogo nace vacío.** Mientras no haya un plan
  dado de alta, «Abrir suscripción» no se ofrece: no hay nada que contratar. El camino sigue siendo el
  del documento —costo por cliente primero, niveles después—.
- **Un contrato a término fijo cargado con prórrogas anteriores arranca contando cero prórrogas.** Las
  dos columnas de la plantilla 1 se leen y se comprueban, pero no se convierten en tramos de
  `periodos_contractuales`: con un número y una sola fecha habría que inventarse las fechas
  intermedias, y 13.1 lo prohíbe. Afecta al cupo de tres prórrogas cortas y al tope de cuatro años en
  quien ya venía renovando. La salida limpia es agregarle a la plantilla las fechas de cada tramo.
- **La plantilla 1 sigue sin país de expedición del documento**, así que la carga solo admite
  municipios de Colombia en esa columna.
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

- **La siguiente tarea es F5-05, la consola de alertas del proveedor** (documento 4, capítulo 7): el
  listado de todo lo enviado con sus filtros, el reenvío puntual y la vista de entregabilidad. La tabla
  `alertas_enviadas` ya guarda desde F3-01 fecha programada, fecha de envío, destinatarios, estado e
  intentos, que son exactamente las seis columnas del capítulo 7; el panel de alertas del **cliente**
  ya existe (`AlertaController`), así que la consola del proveedor es el mismo dato visto sin filtro de
  empresa, con reenvío. Ojo con la entregabilidad: «rebotada» es un estado que hoy nadie escribe
  porque no hay proveedor transaccional (F3-06b).
- **`SeccionDelPanel::pendientes()` perdió otra sección.** Quedan Alertas, Salud y Equipo.
- **El inicio del panel (capítulo 3) sigue sin sus cifras de negocio y ahora ya podría tenerlas:**
  «clientes en riesgo» y «ingreso recurrente» salen de `PanelDeSuscripciones`. Es F5-06 y no se tocó.
- **La pestaña «Suscripción» de la ficha muestra el motivo de la suspensión y de la última
  reactivación**, pero **no un histórico de suspensiones**: la historia completa está en la auditoría de
  la empresa, cuya pantalla es `REV-04` y no tiene tarea en ninguna fase.
- **El ambiente de demostración no tiene suscripción y queda fuera de la lista de empresas a las que
  se les puede abrir una**: es una empresa ficticia del proveedor y cobrarle no significa nada.
- **`User`, `Acceso` e `Invitacion` siguen sin `SoftDeletes`**, abierto desde F1-13.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
