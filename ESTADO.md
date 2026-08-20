# Estado del proyecto

**Última sesión:** 2026-08-19 · Tarea F1-08
**Verificación:** verde
**Siguiente tarea:** F1-09 · Renovar contrato

## Qué se hizo

- **`App\Contratacion\VigenciaDelContrato`**, el objeto nuevo de la sesión: responde en qué día está
  un contrato —cuántos días faltan para vencer, si el plazo del preaviso todavía alcanza, qué estado
  se lee y de qué color—. Recibe el día como parámetro, así que se puede situar en cualquier fecha
  sin tocar el historial. Lo usarán tal cual las alertas de vencimiento del capítulo 9.
- **`HistorialDeContrato::fechaLimiteDePreaviso()`**: treinta días antes del vencimiento vigente,
  leídos de `reglas_laborales.preaviso_dias`. Hay una prueba que cambia la constante a 45 y exige que
  la fecha se mueva; un 30 escrito a mano la pondría en rojo.
- **`ListadoDeContratos`** con los tres filtros del documento 3, 5.4 —rango de vencimiento, tipo de
  contrato y número de prórrogas—, el orden y la paginación, todo en el servidor y con una sola
  consulta compartida con la exportación, igual que `ListadoDeEmpleados`.
- **`DetalleDelContrato`**, `ContratoController`, `ContratosExport` y las pantallas
  `Contratos/Index` y `Contratos/Detalle`. El módulo salió del bucle de secciones pendientes.
- **Dos componentes compartidos aprendieron algo, ninguno nuevo**: `TarjetaIndicador` acepta una
  nota bajo la cifra —«Quedan 12 días» bajo el límite de preaviso— y `Selector` puede ocultar su
  línea de marcador, que en un filtro de lista cerrada es una opción que no significa nada.
- **`ContratosTest` con 33 casos** —la vista por defecto, los cinco filtros, las siete columnas, los
  cuatro estados con su color, el aviso de vencidos, la exportación, el detalle y los permisos—,
  cinco casos nuevos de aislamiento por las rutas reales y 16 de Vitest.
- `composer verificar` en verde: 343 pruebas de Pest, PHPStan sin errores, build y 142 de Vitest.

## Decisiones tomadas

- **La vista por defecto incluye los contratos ya vencidos.** «Vencen en los próximos 90 días» filtra
  por arriba y no por abajo. Un contrato que venció sin que nadie registrara la prórroga es lo más
  urgente de esta pantalla, y esconderlo del rango por defecto sería esconder el problema. Quien los
  quiera solos tiene la opción «Ya vencidos».
- **El listado solo trae vinculaciones activas.** El contrato de quien ya salió no hay que
  gestionarlo, así que no hay filtro de estado del empleado. Es la diferencia con el listado de
  empleados, que sí lo tiene.
- **La columna «Inicio» es la del periodo vigente, no la fecha de ingreso.** Es lo que hace el
  prototipo y es lo correcto: en un contrato con tres prórrogas, el tramo que corre empezó el día de
  la última, y es esa la fecha que se contrasta con el vencimiento de la columna siguiente. La
  antigüedad se sigue preguntando en la ficha del empleado.
- **El ámbar del estado arranca el día en que se cumple la fecha límite del preaviso**, que sale de
  la constante legal y no de un umbral inventado. Antes de esa fecha, verde; después, ámbar; vencido,
  rojo. Es la única regla de color de la pantalla y coincide con los tonos del prototipo.
- **La duración total del vínculo se enseña en días, no en «1a 2m».** El prototipo escribe años y
  meses; se siguió lo que ya hace la pestaña de contrato de la ficha, que es el mismo dato en la
  misma sesión de trabajo del usuario. Una sola forma de leer una duración pesa más que el detalle de
  una pantalla.
- **En un contrato sin plazo, las tarjetas que no existen no se pintan vacías.** Un indefinido no
  tiene vencimiento, ni preaviso, ni tope de cuatro años: en vez de cuatro tarjetas con rayas, salen
  dos y una frase que dice por qué.
- **`VigenciaDelContrato` se separó de `HistorialDeContrato` a propósito.** El historial no depende
  del día en que se mire; la vigencia sí. Juntarlos habría obligado a pasar la fecha de hoy a un
  objeto que calcula prórrogas y topes, que no la necesitan.

## Preguntas para Angela

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
  distinguir especialización, maestría y doctorado**, tal como los pediste. Separarlos después es
  agregar casos al enumerado y nada más.
- **D.1 se vuelve bloqueante en la próxima tarea: ¿se registra el tipo de prórroga?** El enumerado ya
  distingue pactada de automática y el conteo de prórrogas cortas depende de ello —cuatro pactadas,
  tres automáticas—, pero **el formulario de «Renovar» de F1-09 tiene que preguntarlo o no
  preguntarlo, y eso es una decisión tuya**.
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años.

## Decisiones para David

- **El detalle del contrato no tiene los botones de «Renovar» ni «Cambiar a término indefinido».** El
  documento 3 los describe y el prototipo los enseña, pero son las tareas F1-09 y F1-11 y todavía no
  existen las pantallas. Se hizo como con el botón de «Editar» de la ficha: no se pone un botón que
  lleve a ninguna parte. El sitio está libre y el dato que decide si aparecen —`admiteRenovacion`—
  ya viaja en las props, así que agregarlos es una línea de cada lado.
- **El texto del hueco del saldo de vacaciones se lee en pantalla y sigue siendo tuyo.** Está en
  `FichaDeEmpleado::saldoDeVacaciones()`, en un solo sitio.
- **La fecha de salida no existe todavía.** `Vinculacion::fechaDeSalida()` devuelve `fecha_fin` de una
  vinculación inactiva. De ella dependen el énfasis de los 30 días de la advertencia de reingreso y
  la antigüedad de quien ya salió. Está anotado en F2-09.
- **B.3, ¿bloquear o advertir?**, deja de ser teórica en F1-09. La sugerencia del documento 6 es
  bloquear el tope de cuatro años y advertir con fuerza en la prórroga corta.
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
- Abierta de diecinueve sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-09 · Renovar contrato**: acción separada de editar la fecha, que calcula y
  muestra duración de la prórroga, total acumulado y prórrogas restantes, y registra el tipo.
  **Depende de D.1**, que sigue abierta y está arriba.
- **La escritura ya existe: `Vinculacion::registrarPeriodo()`.** Lo único que F1-09 agrega es que,
  además del tramo, hay que mover `tipo_contrato` y `fecha_fin` de la vinculación. **El historial y
  lo vigente se mueven a la vez o quedan desalineados**; hoy eso solo lo hace el observador al crear,
  y las pruebas de F1-08 lo hacen a mano justo por eso. Conviene que `registrarPeriodo()` lo asuma.
- **Los cálculos del modal de renovación ya están hechos.** `HistorialDeContrato` da prórrogas
  pactadas y automáticas por separado, duración total, tope legal y `superaElTopeLegal($fechaFin)`,
  que es exactamente la validación bloqueante de F1-10.
- **El detalle del contrato es donde va el botón.** `Contratos/Detalle.vue` ya recibe
  `contrato.admiteRenovacion`, y el modal es el componente `Modal` que ya existe.
- **La tabla del historial es una sola en todo el sistema.** La arma
  `FichaDeEmpleado::filasDelHistorial()` y la enseñan la pestaña de contrato de la ficha y el detalle
  del contrato. Si F1-09 le agrega la columna «Registrado», se agrega ahí y aparece en las dos.
- **`VigenciaDelContrato` es el sitio de cualquier cuenta de días sobre un contrato.** No escribas
  otra: recibe el día por parámetro justamente para el proceso diario de alertas.
- **`OpcionesDeLista` sigue solo en los cuatro enumerados de F1-06.** `Genero`, `TipoDocumento`,
  `TipoContrato`, `TipoRiesgo`, `TipoVivienda` y `EstadoVinculacion` mantienen sus métodos escritos a
  mano. Unificarlos es un rato y no rompe nada.
- **No queda ninguna tarea bloqueada en el backlog.**
