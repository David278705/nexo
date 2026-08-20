# Estado del proyecto

**Última sesión:** 2026-08-19 · Tarea F1-07
**Verificación:** verde
**Siguiente tarea:** F1-08 · Listado y detalle de contratos

## Qué se hizo

- **`App\Contratacion\FichaDeEmpleado`**, que arma la pantalla entera: el encabezado, el resumen de
  seis datos y el contenido de las cuatro pestañas. El controlador quedó en tres líneas y no formatea
  ni calcula nada, igual que con `ListadoDeEmpleados`.
- **La pantalla `Empleados/Ficha`**, con las pestañas de datos personales, contrato, novedades y
  movimientos. Cambiar de pestaña no pide nada al servidor: el contenido de las cuatro ya viene en
  las props.
- **Tres componentes compartidos nuevos**: `Pestanas` (recorrible con flechas y con `role="tab"`),
  `ListaDeDatos` (la tarjeta de pares etiqueta-valor, que se repetía cuatro veces en la sola pestaña
  de datos) y `ResumenDeEmpleado`, la franja azul del prototipo.
- **`TablaDatos` aprendió a no ser clicable.** El historial de periodos informa, no lleva a ninguna
  parte, y una fila con `cursor-pointer` que no hace nada al pulsarla es peor que ninguna.
- **Dos relaciones que faltaban en `Persona`**: `paisNacimiento()` y `paisExpedicion()`. La columna
  guarda el código ISO y la ficha necesita el nombre.
- **`FichaDeEmpleadoTest` con 19 casos** —el resumen completo, la antigüedad en cuatro escenarios, el
  perfil sociodemográfico encendido, apagado y vacío, el historial con y sin prórroga, el tope legal
  solo donde hay plazo— y 11 casos nuevos de Vitest.
- `composer verificar` en verde a la primera: 305 pruebas de Pest, PHPStan sin errores, build y 126
  de Vitest.

## Decisiones tomadas

- **El saldo de vacaciones se enseña como un hueco con nombre, no como un cero.** Dice «Aún no
  disponible» con una línea que explica de qué depende. Pintar «0,00 días» sería el único número
  falso posible, y guardar una columna lo prohíbe la regla 2. Cuando exista F2-02 se cambia
  `FichaDeEmpleado::saldoDeVacaciones()` y no cambia nada más: la pantalla ya lo trata como un dato
  cualquiera del resumen.
- **Las pestañas de novedades y movimientos cargan vacías y explican qué se verá ahí.** No dicen «no
  hay datos» ni anuncian que falta un módulo: describen el contenido futuro, que además es cierto hoy
  —esa persona no tiene ninguna registrada—. El día que exista el módulo solo se llena el arreglo.
- **La antigüedad deja de correr cuando la persona sale.** Se mide hasta `fechaDeSalida()` si la hay
  y hasta hoy si no. No es una cuenta laboral —no descuenta suspensiones— y por eso no vive en el
  motor de cálculo: es la distancia entre dos fechas.
- **El historial de periodos se enseña ya en la ficha, no solo en F1-08.** La tarjeta de contrato sin
  él sería tres renglones. `FichaDeEmpleado::filasDelHistorial()` es pública y estática justamente
  para que el detalle del contrato de F1-08 use la misma tabla y no una copia.
- **El tope de cuatro años y la duración total solo aparecen donde hay plazo.** En un indefinido no
  existe el tope, y enseñarlo vacío invita a preguntarse por qué está.
- **Las fechas van en `d/m/Y`,** como el listado y la exportación, aunque el prototipo escriba «3 de
  febrero de 2022» en este encabezado. Una sola forma de leer una fecha en todo el sistema pesa más
  que el detalle de una pantalla.

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
- **D.1 sigue abierta: ¿se registra el tipo de prórroga?** La respuesta cambia el formulario de
  «Renovar» de F1-09: o pregunta cuál de las dos es, o no pregunta.
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años.

## Decisiones para David

- **El texto del hueco del saldo se lee en pantalla y es tuyo.** Hoy dice «Aún no disponible» y, en
  letra pequeña, «El saldo se calcula con las vacaciones disfrutadas y las suspensiones registradas.
  Se verá aquí cuando el módulo de vacaciones esté disponible». Está en un solo sitio
  (`FichaDeEmpleado::saldoDeVacaciones()`). Si prefieres otra frase para las demostraciones, se
  cambia ahí.
- **La ficha no tiene botón de «Editar».** El prototipo lo enseña y el documento 3 describe la
  pantalla de edición, pero **no hay tarea de editar empleado en el backlog de la fase 1**. No se
  puso un botón que lleve a una pantalla que no existe. Si la edición va en la fase 1, es una tarea
  nueva; si va después, la ficha ya tiene su sitio libre.
- **La fecha de salida no existe todavía.** `Vinculacion::fechaDeSalida()` devuelve `fecha_fin` de
  una vinculación inactiva. Ahora de ella dependen dos cosas: el énfasis de los 30 días de la
  advertencia de reingreso y **la antigüedad de quien ya salió**. Está anotado en F2-09.
- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Lo advierte la propia hoja «Notas y fuentes» del archivo: hay nueve EPS intervenidas por la
  Supersalud y el sector está en movimiento. **Es una lista global: si está mal, está mal para todos
  los clientes a la vez.**
- **Verificar el calendario de festivos antes de vender.** Cincuenta y tres fechas, quince minutos,
  mismo riesgo global que las EPS.
- **Contrastar los 1.101 municipios contra el archivo del DANE.** Ese mismo cotejo trae los códigos
  DANE que hoy están nulos.
- **B.3, ¿bloquear o advertir?**, deja de ser teórica en F1-09. La sugerencia del documento 6 es
  bloquear el tope de cuatro años y advertir con fuerza en la prórroga corta.
- **Vigencia del enlace de invitación: siete días.** Abierta desde F0-07, en
  `Invitaciones::DIAS_DE_VIGENCIA`. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`.
- Abierta de dieciocho sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-08 · Listado y detalle de contratos**: vista por defecto de los que vencen en
  90 días, detalle con el historial desde el periodo inicial y la fecha límite de preaviso visible.
- **La tabla del historial ya está resuelta.** `FichaDeEmpleado::filasDelHistorial()` la arma y la
  pestaña de contrato la enseña; el detalle de F1-08 la reutiliza tal cual y le agrega el preaviso y
  los dos botones de acción. **No escribas una segunda.**
- **`HistorialDeContrato` ya calcula todo lo que F1-08 necesita** menos el preaviso: prórrogas
  acumuladas, vencimiento vigente, duración total y fecha del tope legal.
- **El listado de contratos se parece mucho al de empleados.** `ListadoDeEmpleados` es el patrón a
  seguir —filtros normalizados en el servidor, una sola consulta compartida con la exportación— y
  `Paginacion`, `BarraFiltros` y `TablaDatos` ya existen.
- **`ResumenDeEmpleado` está pensado para reaparecer** en el modal grande de novedades (documento 3,
  5.5), que es F2-03. No lo dupliques allá.
- **`OpcionesDeLista` sigue solo en los cuatro enumerados de F1-06.** `Genero`, `TipoDocumento`,
  `TipoContrato`, `TipoRiesgo`, `TipoVivienda` y `EstadoVinculacion` mantienen sus métodos escritos a
  mano. Unificarlos es un rato y no rompe nada.
- **No queda ninguna tarea bloqueada en el backlog.**
