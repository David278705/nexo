# Estado del proyecto

**Última sesión:** 2026-08-24 · REV-03 (editar y eliminar movimientos y terminaciones), cerrada a mano
por David después de que el bucle se cortara por el límite de uso a mitad del trabajo.
**Verificación:** verde — Pint, PHPStan sin errores, **1.166** pruebas de Pest, build y **384** de Vitest.
**Siguiente tarea:** REV-07 · La restauración nocturna llena la auditoría del ambiente de demostración.

**El backlog de las seis fases está terminado.** Quedan tres tareas `REV-` y dos `[!]` que no son
código: F3-06b (entregabilidad real, la hace David) y F6-07 (operación, **ya desbloqueada** — A.5 se
resolvió con Railway el 23 de agosto, ver más abajo).

## Cómo quedó REV-03, cerrada fuera del bucle automático

El backend, sus `FormRequest` y las pruebas de Pest —incluidas las dos nuevas de
`AislamientoTest.php`— ya estaban completos y en verde cuando la sesión se cortó: el registro de esa
vuelta decía literalmente «You've hit your session limit · resets 7:20pm», una frase que
`scripts/vibe.ps1` no tenía en su lista de patrones, así que el script la reportó como un error
genérico de código 1 en vez de la pausa normal por límite de uso. **Ya está corregida** —se agregó
`session limit` a `$patronLimite`— para que la próxima vez lo diga bien.

Lo único que faltaba era el frontend: una prueba de Vitest en `Terminaciones/Index.test.js` buscaba el
campo «Tipo de salida» en todo el documento en vez de dentro del modal abierto, y como la barra de
filtros tiene un campo con la misma etiqueta, encontraba ese en vez del formulario de edición
—devolviendo siempre «todos»—. **Era un defecto de la prueba, no del componente**: el componente ya
guardaba y mostraba bien el tipo y el motivo. Corregido acotando la búsqueda al `[role="dialog"]`
cuando hay uno abierto, siguiendo el mismo patrón que ya usaba `abrirYSeleccionar()` en ese archivo.
Verificado con `composer verificar` completo en verde antes de marcar la tarea y commitear.

## Qué se hizo

- **F6-08, la revisión final**, con el skill `revisar` completo. Alcance: las trece tareas desde el
  último punto de control —la fase 5 entera, que no tuvo el suyo, y la fase 6— más el barrido de las
  cinco secciones sobre todo lo construido. El informe está en `bitacora/revision-2026-08-24.md`.
- **Aislamiento y motor, sin hallazgos.** Trece tablas de negocio, trece políticas RLS, trece casos en
  `AislamientoTest`; las nueve tablas nuevas del panel del proveedor sin trait y **cada una diciendo por
  qué en su propia migración**; los diez casos obligatorios pasando y ninguna cuenta laboral fuera de
  `app/Dominio/Calculo/`.
- **Tres arreglos**, los tres con prueba nueva:
  - **Revocar un acceso de prueba no echaba a quien ya estaba dentro.** El middleware miraba la fecha
    `expira_en` copiada a la sesión al entrar, así que revocar cortaba el enlace para quien todavía no
    lo había usado y no hacía nada con la visita abierta: el panel decía «quedó revocado» mientras el
    prospecto seguía navegando el ambiente de demostración.
  - **Los errores de la aplicación se copiaban a la auditoría del cliente.** Las tres tablas de F5-06
    no estaban en `SIN_AUDITAR`, y en el caso de `errores_aplicacion` la línea tomaba la empresa en
    contexto: el mensaje interno de un error acababa en la única tabla que el cliente puede leer entera.
  - **La acción por fila del panel estaba copiada seis veces en cuatro pantallas**, que es la deuda que
    el skill nombra literalmente. Quedó en `resources/js/Utilidades/usarAccionPorFila.js`.
- **Una tarea nueva, `REV-07`**, delante de `REV-04`.

## Decisiones tomadas

- **La vigencia de un acceso de prueba se pregunta a la fila, no a una fecha copiada.** Lo que se guarda
  en la sesión al entrar es **cuál** es el acceso; el middleware —renombrado a
  `CerrarAccesoDePruebaNoVigente`, porque cerrar ya no es solo por vencimiento— llama a
  `AccesoDePrueba::estaVigente()`, el mismo método que responde al entrar. Las dos mitades de la
  vigencia se contestan en un solo sitio en vez de en dos con distinto criterio. Cuesta una consulta por
  petición y solo en las sesiones de demostración: una sesión normal nunca trae esa marca.
- **El texto del panel y el del modal se ajustaron a lo que ahora ocurre de verdad**: el enlace deja de
  servir y, si el prospecto está dentro, se le cierra la sesión. Antes el modal prometía «de inmediato»
  algo que solo valía para quien aún no había entrado.
- **`REV-07` no se arregló en esta sesión y no es por falta de tiempo.** La lista `SIN_AUDITAR` no sirve
  para este caso —`Persona` y `Vinculacion` sí tienen que auditarse en las empresas reales—, así que la
  salida es un mecanismo para escribir sin auditar y la decisión de quién puede usarlo. Eso es una tarea
  con su propio alcance, no el remate de otra.
- **`usarAccionPorFila` devuelve un `ref` por llamada y no uno compartido**, con prueba: dos acciones de
  la misma pantalla no pueden compartir indicador o desactivar una entidad encendería la ruedita del
  municipio.

## Lo que falta para poder vender

La tabla G del documento 6, casilla por casilla. Son dieciséis: **cuatro están cerradas, dos están a
medias y diez no las puede cerrar el repositorio.** Ninguna de esas diez está bloqueada por el código.

### Cerradas

| Casilla | Estado |
|---|---|
| Pruebas de aislamiento entre empresas en verde | **Hecho.** Trece tablas de negocio, trece casos, y quitar un trait a propósito pone la batería en rojo. |
| Pruebas del motor de cálculo con las tres reglas de conteo | **Hecho.** Los diez casos del documento 2, 4.2, más ocho, y ninguna cuenta laboral fuera del motor. |
| Calendario de festivos del año cargado y verificado, con sábados incluidos | **Hecho.** 2025, 2026 y 2027, con los de sábado. El panel avisa desde octubre si falta el año siguiente. **Hay que cargar 2028 antes de octubre de 2027.** |
| Ambiente de demostración poblado con datos ficticios | **Hecho** (F5-03). Se restaura solo cada noche a las tres. |

### A medias

| Casilla | Qué hay y qué falta |
|---|---|
| Módulo de auditoría activo | El observador escribe desde F0-08 y no se le escapa ninguna tabla. **Lo que no existe es la pantalla**: hoy nadie puede mirar lo registrado. Es `REV-04`, y `REV-07` va delante. |
| Plantillas de carga inicial listas **y probadas con datos reales** | Las seis están construidas (F5-01 y F5-02), con modo simulación y carga en una sola transacción, y probadas con datos de mentira. **Probarlas con un archivo real de una empresa no lo puede hacer el repositorio**, y F5-01 dejó tres preguntas para Angela sobre celdas concretas de las plantillas 1, 2 y 3 —siguen abiertas, abajo—. |

### No las puede cerrar el repositorio

| Casilla | De quién y de qué depende |
|---|---|
| Sociedad constituida y facturación electrónica habilitada | **David y Angela.** Documento 6, A.6. |
| Contrato de prestación del servicio revisado por abogado | **Abogado.** A.2. Los componentes que debe tener ya están identificados; el texto no existe. |
| Acuerdo de tratamiento de datos revisado y listo para firmar | **Abogado.** B.4. El borrador está en «Documentos legales». |
| Plantilla de autorización para que el cliente la use con sus empleados | **Abogado.** B.4, Anexo 3 del mismo borrador, con la autorización expresa para datos de salud. |
| Reglas de contrato a término fijo validadas con asesor laboral | **Asesor laboral.** B.1 ya la respondió Angela —tres prórrogas cortas en una sola cuenta, y así está programado—. Siguen abiertas **B.2** (periodo de prueba en cambio de modalidad) y **B.3** (si la prórroga mínima bloquea o solo advierte; hoy advierte, y cambiarlo es una palabra en `config/validaciones.php`). |
| Copias de seguridad automáticas y una restauración probada | **David, y depende de A.5**, la decisión de infraestructura. Es F6-07, bloqueada. Sin saber dónde vive el sistema no hay dónde producir la copia ni cómo restaurarla. |
| Correo llegando a bandeja de entrada desde dominio propio | **David.** Es F3-06b: proveedor transaccional, `.env`, DNS con SPF/DKIM/DMARC y correr `alertas:prueba`. **Todo el motor de alertas está construido y probado; hasta que esto se haga, ningún correo sale de la máquina.** Es lo único que separa al producto de cumplir su promesa central. |
| Costo por cliente calculado y tabla de precios definida | **David y Angela.** A.1. El catálogo de planes existe y nace vacío justamente por esto. |
| Pasarela de pagos con cobro recurrente operativa | **David y Angela.** A.6. El módulo de suscripciones registra pagos a mano (F5-04); el cobro automático depende de esto. |
| Guion de la reunión de onboarding documentado y cronometrado | **Angela.** No hay nada escrito y no es trabajo del repositorio. |

### Y además, fuera de la tabla G

- **D.4 sigue siendo el único pendiente marcado BLOQUEANTE del documento 6** y deja dos indicadores de
  Composición sin construir: los cortes de los rangos de edad y de antigüedad.
- **La cláusula del documento 4, 4.4** —que el contrato y el acuerdo de datos mencionen el acceso del
  proveedor al entorno del cliente— **es de papel y sigue sin existir.** El software ya cumple las dos
  condiciones suyas. No debería usarse con un cliente real hasta que ese párrafo exista (A.2 y A.6).

## Preguntas para Angela

- Las tres de F5-01: «Sura» contra «Sura EPS» y «ARL Sura» en la fila de ejemplo de la plantilla 1; el
  código de diagnóstico que la plantilla 3 marca opcional y el sistema exige; y el rango «Menos de 30
  min» de la plantilla 2, que el sistema parte en dos. **Bloquean la mitad que falta de la casilla
  «plantillas probadas con datos reales».**
- Heredadas, las cinco de sesiones anteriores: los tres nombres del filtro «Estado del contrato», el
  texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».
- D.12 sigue formalmente abierta: no hay un número sugerido de referencia para la vigencia de un acceso
  de prueba; hoy el formulario no ofrece ninguno y quien lo genera decide caso por caso.
- **D.4, los rangos de edad y de antigüedad.** Es la única marcada BLOQUEANTE.

## Decisiones para David

- **Lo que falta para vender está arriba, casilla por casilla.** Nueve de las dieciséis son tuyas o de
  Angela y ninguna la puede cerrar el repositorio.
- **`REV-06` sigue tuya y es la cuarta revisión que la levanta**: acotar en `.claude/rules/30-interfaz.md`
  la regla de «la fecha entre paréntesis» a los correos, o repasar el panel de inicio y las nueve
  pantallas de listado para cumplirla literal. La recomendación lleva escrita en la tarea desde F4-05.
- **Verificar a mano en un navegador el flujo de F6-06 sigue pendiente.** No hay Playwright ni
  `user-event`, así que lo probado es el mecanismo del atrapado de foco, no una sesión de teclado real.
- **El menú desplegable de la barra lateral en pantallas pequeñas no atrapa el foco**, a diferencia de
  los modales. El patrón de `Modal.vue` es reutilizable si alguna vez se cierra ese hueco.
- **El mínimo del motivo (diez caracteres) de una visita al entorno sigue siendo propuesta**, en
  `EntrarAlEntornoDelCliente`.
- **El umbral de «clientes en riesgo» sigue siendo propuesta**, en `config/panel.php`, clave
  `riesgo_de_cancelacion`. **La escalera de mora también**, clave `cobro.escalera_de_mora`.
- **Un contrato a término fijo cargado con prórrogas anteriores arranca contando cero prórrogas.**
- **La plantilla 1 sigue sin país de expedición del documento.**
- **`User`, `Acceso` e `Invitacion` siguen sin `SoftDeletes`**, abierto desde F1-13 y sin tarea en el
  backlog. Va a doler el día que exista «Usuarios y permisos»: dar de baja a un usuario tiene que ser
  lógico, o se pierde de quién eran las líneas de auditoría que él escribió.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el saldo
  negativo.

## Notas para la próxima sesión

- **La siguiente tarea es `REV-03`, editar y eliminar movimientos y terminaciones.** Está lista desde
  F4-05: David respondió las dos preguntas y la regla viene escrita en la propia tarea —eliminar es
  volver al pasado, y solo se puede deshacer el último de la lista—. No hay que decidir nada.
- **Después van `REV-07`, `REV-04` y `REV-06`, en ese orden.** `REV-07` va delante de `REV-04` a
  propósito: es el ruido que la pantalla de auditoría enseñaría el día que exista.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
