# Estado del proyecto

**Última sesión:** 2026-08-19 · Tarea F1-05
**Verificación:** verde
**Siguiente tarea:** F1-06 · Perfil sociodemográfico

## Qué se hizo

- **`App\Contratacion\AdvertenciaDeReingreso`**, la sección 5.6 del documento 1. Los dos textos
  —la advertencia y la nota— son **constantes del servidor**, no cadenas dentro de un componente
  Vue, para que una prueba pueda compararlos carácter a carácter con el documento. Devuelve `null`
  cuando no hay nada que advertir, y así el aviso no existe en lugar de existir vacío.
- **La prop opcional `reingreso`** en `EmpleadoController::nuevo()`. No se calcula al abrir la
  pantalla: solo cuando el asistente la pide. `Inertia::optional()` es exactamente eso.
- **`AvisoDeReingreso.vue`**, componente compartido. Se muestra dos veces en el asistente: pegado al
  campo del documento, que es donde se digita, y otra vez en el resumen, que es donde ya se conoce la
  fecha de ingreso y donde es la última oportunidad antes de guardar.
- **`Vinculacion::fechaDeSalida()`**, el único sitio del que sale la fecha de la salida anterior.
- **`AdvertenciaDeReingresoTest`** con 17 casos, incluidos los dos lados de la ventana de 30 días
  —día 30 dentro, día 31 fuera— y el aislamiento entre empresas. Seis casos nuevos de Vitest en el
  asistente y cinco del componente.
- `composer verificar` en verde: 270 pruebas de Pest, PHPStan sin errores, build y 108 de Vitest.

## Decisiones tomadas

- **La comprobación es del servidor y viaja por Inertia**, no por `axios`. `router.reload({ only:
  ['reingreso'] })` al salir del campo, con `preserveState`, así que el formulario a medio llenar no
  se pierde. Es el único camino por el que el navegador puede saber si un documento ya estuvo
  vinculado sin que se le entregue la lista de documentos de la empresa.
- **La advertencia aparece aunque la vinculación anterior siga activa.** El documento dice «ya tuvo
  un registro anterior», no «ya salió»: dos vinculaciones simultáneas de la misma persona son
  igualmente algo que quien registra tiene que ver.
- **El énfasis se pregunta dos veces.** En el paso 1 no existe todavía la fecha de ingreso, así que
  el reingreso se mide contra hoy; al salir del campo de fecha de ingreso, en el paso 3, se vuelve a
  preguntar y el aviso del resumen ya sale con la cuenta correcta.
- **Un ingreso anterior a la salida no lleva énfasis.** Es un dato mal digitado, no un reingreso
  cercano, y marcarlo en rojo enseñaría a ignorar el aviso.
- **El tono es advertencia, nunca error.** Un reingreso es legítimo; lo que se pide es verificar. El
  énfasis de los 30 días va **en palabras además de en negrilla** —cambia el título del aviso—,
  porque el color no puede ser el único portador de la información.
- **`fechaDeSalida()` es provisional y está en un solo método a propósito.** Ver abajo.

## Preguntas para Angela

- **La columna «Cesantías» del archivo de listas sigue sin dónde ir.** Trae Porvenir, Protección,
  Colfondos, Skandia, el Fondo Nacional del Ahorro y «Otro», pero **el fondo de cesantías no aparece
  en la sección 5.1 de la especificación funcional**. La pregunta concreta: **¿se agrega «fondo de
  cesantías» como campo obligatorio del formulario de empleado?** Si la respuesta es sí, es una tarea
  nueva —tabla, columna, seeder y un campo más en el paso 2 del asistente, que ya está construido.
- Menor, del mismo archivo: nivel educativo quedó con **«Técnico / Tecnólogo» unidos** y
  **«Posgraduado(a)» sin distinguir especialización, maestría y doctorado**. La propia hoja de notas
  dice que es fácil de separar después. Se construye así en F1-06 salvo aviso.
- **D.1 sigue abierta: ¿se registra el tipo de prórroga?** No ha bloqueado nada todavía, pero la
  respuesta cambia el formulario de «Renovar» de F1-09: o pregunta cuál de las dos es, o no pregunta.
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años.

## Decisiones para David

- **La fecha de salida no existe todavía, y el énfasis de los 30 días depende de ella.** Es un campo
  del módulo de terminaciones (documento 1, 7.1), que es F2-09. Hoy `Vinculacion::fechaDeSalida()`
  devuelve `fecha_fin` de una vinculación inactiva, que en un término fijo expirado **es** la fecha
  de salida y en las demás modalidades es nula. Consecuencia acotada a propósito: **la advertencia se
  muestra siempre igual**; lo único que se pierde cuando no hay fecha es el énfasis. Cuando F2-09
  registre la salida de verdad, se cambia ese método y nadie más se entera. **Está anotado en F2-09.**
- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Lo advierte la propia hoja «Notas y fuentes» del archivo: hay nueve EPS intervenidas por la
  Supersalud —siguen operando, por eso se cargaron— y el sector está en movimiento. Las fuentes son
  Supersalud, Fasecolda, la Superintendencia del Subsidio Familiar y la Superintendencia Financiera.
  **Es una lista global: si está mal, está mal para todos los clientes a la vez.**
- **Verificar el calendario de festivos antes de vender.** Cincuenta y tres fechas, quince minutos,
  mismo riesgo global que las EPS.
- **Contrastar los 1.101 municipios contra el archivo del DANE.** Ese mismo cotejo trae los códigos
  DANE que hoy están nulos.
- **B.3, ¿bloquear o advertir?**, deja de ser teórica en F1-09. La sugerencia del documento 6 es
  bloquear el tope de cuatro años y advertir con fuerza en la prórroga corta.
- **Vigencia del enlace de invitación: siete días.** Abierta desde F0-07, en
  `Invitaciones::DIAS_DE_VIGENCIA`, en un solo sitio. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`. Ya son cuatro las tablas de negocio que
  dependen de ello.
- Abierta de dieciséis sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-06 · Perfil sociodemográfico**: bloque opcional activable por parámetro de
  empresa, con **todos sus campos opcionales aunque el bloque esté activo**, y los empleados
  existentes en blanco sin marcar error al activarlo después.
- **El asistente está escrito para eso desde F1-04**: `pasos` es un `computed`, no una lista fija, y
  el perfil se intercala **antes** del resumen. La tabla `perfil_sociodemografico` y su modelo ya
  existen desde F1-01; lo que falta es el parámetro de empresa, el paso y la escritura.
- **Los parámetros de empresa viven en `tenants`** y su pantalla es F1-12, que llega después. F1-06
  solo necesita leer el parámetro, no editarlo.
- **Si F1-06 agrega campos al asistente, hay que agregarlos a la lista de campos de su paso** en
  `pasos`: de ahí sale tanto el «no avanzar con un obligatorio vacío» como el «volver al paso del
  error» cuando el servidor rechaza algo. Con todo opcional, el paso nuevo llevará `campos: []`.
- **`consultar()` del asistente ya sabe repreguntar.** Si el perfil mueve de sitio la fecha de
  ingreso, el `@salir` que dispara la advertencia de reingreso tiene que ir con ella.
- **La columna de saldo de vacaciones entra en el listado cuando exista F2-02**, no antes.
- **No queda ninguna tarea bloqueada en el backlog.**
