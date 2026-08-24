# Estado del proyecto

**Última sesión:** 2026-08-24 · Tarea F5-07 (bitácora y acceso al entorno del cliente)
**Verificación:** verde — Pint, PHPStan sin errores, **1.135** pruebas de Pest, build y 342 de Vitest.
**Siguiente tarea:** F6-01 · Estados vacíos. **Con F5-07 se cierra la fase 5 entera.**

## Qué se hizo

- **F5-07, bitácora y acceso al entorno del cliente** (documento 4, 4.3 y 4.4). Dos cosas en la misma
  pestaña de la ficha, y son lo contrario en visibilidad: la bitácora es interna del equipo y el
  cliente no la ve nunca; la visita al entorno existe **para que él la vea**.
- **Dos tablas nuevas, las dos del lado del proveedor**, sin trait ni RLS, como `suscripciones`:
  `notas_cliente` —la que el documento 2, 3.5 tenía anotada desde el principio— y
  `visitas_al_entorno`, que no estaba en ninguna lista y es la que sostiene las dos condiciones no
  negociables del 4.4.
- **La constancia la escribe el observador y no el código.** La fila de la visita lleva la empresa
  visitada, así que la línea cae en la auditoría **de esa empresa** con el `owner` como autor y el
  motivo dentro. Salir la cierra y deja una segunda línea: sin ella la constancia diría que el
  proveedor entró y nunca se fue.
- **De solo lectura, en el servidor y por verbo.** `EntornoDeSoloLectura` va en la pila del grupo
  `web`: durante una visita solo pasan `GET`, `HEAD` y `OPTIONS`, más salir del entorno y cerrar
  sesión. Puesto por verbo y no por pantalla, un módulo construido dentro de un año queda protegido
  sin tocar ese archivo. La prueba lo comprueba con una escritura de verdad —registrar unas
  vacaciones—, no mirando si hay un botón.
- **Activar la escritura es expreso y deja un registro aparte.** La casilla del modal no crea la
  visita ya abierta: se crea en modo consulta y se activa en una segunda escritura, que es una
  segunda línea de auditoría. Dos hechos, dos líneas, las dos visibles para el cliente.
- **El contexto de empresa lo sigue decidiendo el servidor.** La sesión guarda solo el identificador
  de la visita; de qué empresa es lo dice la fila. `ResolverTenant` la lee **después** de intentar
  resolver la empresa del usuario, así que ningún usuario de empresa puede caer en otra por este
  camino, y olvida lo resuelto al empezar cada petición por lo mismo que ya olvidaba el contexto.
- **`PoliticaDeModulos` perdió su comentario de "cuando exista F5-07".** El `owner` ve todos los
  módulos **solo durante una visita**, como el superadministrador de esa empresa: entra a resolver un
  problema que el cliente no sabe describir y no se sabe de antemano en qué módulo está.
- **`FueraDeUnaVisita`** cierra el panel mientras se está dentro —o se está en el panel, o se está
  dentro de un cliente— y **`BloquearEmpresaSuspendida` estrenó excepción para el `owner`**, que desde
  hoy sí tiene empresa en contexto: lo que la suspensión bloquea es el acceso del cliente, y el
  cliente suspendido es justo el que llama a reclamar.
- **`NotaCliente` es el primer modelo que entra a `SIN_AUDITAR` por confidencialidad y no por ruido**:
  la línea llevaría el texto de la nota a la única tabla que el cliente puede leer entera.
- **La franja permanente** (`FranjaDeVisita.vue`, en el armazón del cliente) dice de quién son los
  datos, en qué modo se entró y por dónde se sale. Es nula en toda petición normal: el cliente no la
  ve nunca.
- Pruebas nuevas en `BitacoraYEntornoTest.php` (19 casos), `FranjaDeVisita.test.js` y cinco más en
  `FichaDeEmpresa.test.js`. `Formato::minutos()` es nuevo, para la duración de una visita.

## Decisiones tomadas

- **La visita es una fila y no una marca en la sesión.** La regla 4 exige que la constancia la escriba
  un observador y no el código a mano; la única forma de que el observador escriba es que haya un
  modelo que guardar. De paso, el historial de la ficha sale de esa tabla.
- **El motivo es obligatorio, con mínimo de diez caracteres**, como el de una suspensión. No lo exige
  el documento con esas palabras, pero sí el prototipo en la ayuda del campo —«escriba algo que tenga
  sentido dentro de seis meses»—, y es lo que el cliente va a leer en su auditoría.
- **La escritura se activa al entrar y no desde dentro.** Es lo único que ofrece el prototipo, y una
  activación a mitad de la visita sería una segunda pantalla sin fuente que la respalde.
- **Una nota no se edita, se quita.** Es un registro de lo que pasó: lo que se escribió en agosto
  tiene que seguir diciendo en febrero lo que se escribió en agosto.
- **`visitas_al_entorno` no lleva borrado lógico**, como `auditoria` y `accesos`: ninguna pantalla la
  borra, así que la columna sobraría.

## Preguntas para Angela

- Las tres de F5-01 siguen abiertas: «Sura» contra «Sura EPS» y «ARL Sura» en la fila de ejemplo de la
  plantilla 1; el código de diagnóstico que la plantilla 3 marca opcional y el sistema exige; y el
  rango «Menos de 30 min» de la plantilla 2, que el sistema parte en dos.
- Heredadas, las cinco de sesiones anteriores: los tres nombres del filtro «Estado del contrato», el
  texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».
- D.12 sigue formalmente abierta: no hay un número sugerido de referencia para la vigencia de un
  acceso de prueba; hoy el formulario no ofrece ninguno y quien lo genera decide caso por caso.

## Decisiones para David

- **La tercera condición del 4.4 es tuya y es de papel**: «el contrato y el acuerdo de tratamiento de
  datos deben mencionarlo». El software ya cumple las dos suyas; **el acceso al entorno de un cliente
  no debería usarse con un cliente real hasta que ese párrafo exista** (A.2 y A.6).
- **El mínimo del motivo (diez caracteres) y que sea obligatorio son propuesta**, en
  `EntrarAlEntornoDelCliente`. Es el mismo criterio que el motivo de una suspensión.
- **El umbral de «clientes en riesgo» de F5-06 sigue siendo una propuesta**, en `config/panel.php`,
  clave `riesgo_de_cancelacion`.
- **La escalera de mora sigue siendo tuya**, en `config/panel.php`, clave `cobro.escalera_de_mora`.
- **La tabla de precios (A.1) sigue sin decidir** y el catálogo de planes nace vacío.
- **Un contrato a término fijo cargado con prórrogas anteriores arranca contando cero prórrogas.**
- **La plantilla 1 sigue sin país de expedición del documento.**
- **`REV-06` sigue tuya: acotar la regla de «la fecha entre paréntesis».**
- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Hasta que eso se haga, ningún correo sale de la máquina
  y la salud del sistema sigue sin poder decir si el correo llega.
- **A.5, infraestructura y copias de seguridad, sigue abierta** y sigue bloqueando la tarjeta «Copias
  de seguridad» de la salud del sistema.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **La fase 5 quedó cerrada.** La siguiente tarea es **F6-01, estados vacíos**, la primera de la fase
  6 (pulido): los siete listados con su texto propio y su botón de primera acción, del documento 3,
  6.3.
- **`REV-04` se volvió más urgente con esta tarea.** El acceso del proveedor deja constancia en la
  auditoría del cliente, y esa constancia **el cliente todavía no la puede mirar en ninguna pantalla**:
  el módulo `Auditoria` sigue siendo el último `SeccionPendiente` del entorno del cliente. Hoy la
  prueba la lee por el mismo camino que usará esa pantalla, pero mientras REV-04 no exista la promesa
  del documento está cumplida a medias.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
