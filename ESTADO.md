# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F4-01 (cerrada: exportación y filtros por módulo)
**Verificación:** verde — Pint, PHPStan sin errores, **898** pruebas de Pest, build y 277 de Vitest.
**Siguiente tarea:** F4-02 · Caché de saldos.

## Qué se hizo

- **Los cinco filtros que faltaban de la tabla 11.3 del documento 1**: «Empleado» en incapacidades,
  vacaciones y licencias; «Cargo» en vacaciones y movimientos; «Estado del contrato» en contratos. Con
  eso, **los siete módulos con pantalla tienen exactamente los filtros que el documento 1 les da**.
- **`App\Contratacion\FiltraPorEmpleado`**, el trait nuevo: la búsqueda por nombre o documento y el
  filtro de cargo, cada uno en sus dos versiones —el listado que parte de `vinculaciones` y el que
  parte de la tabla de la novedad—. Es el gemelo de `OrdenaPorEmpleado`, que ya hacía lo mismo para el
  orden.
- **`VigenciaDelContrato` ganó `ESTADOS`, `estadoDeGestion()` y `acotarAlEstado()`.** Los tres estados
  son los que la columna «Estado» ya pintaba; lo nuevo es que tienen nombre y se pueden pedir por la
  dirección.
- **`HistorialDeContrato::diasDePreaviso()`**, que reúne las tres lecturas sueltas de
  `reglas_laborales.preaviso_dias`.
- **La exportación de incapacidades lleva ahora la EPS**, que el reporte del documento 1, 11.4 pide
  junto al origen, el diagnóstico y los días.
- **`tests/Feature/ExportacionesYFiltrosTest.php`, 22 casos**: el «hecho cuando» con tres filtros a la
  vez, las tres columnas obligatorias en las siete exportaciones, cada filtro nuevo con su etiqueta
  removible, los tres estados del contrato con su prueba de coherencia con el color, y el aislamiento
  de los caminos nuevos.
- Las cinco pantallas afectadas y las tres fábricas de props de Vitest que se quedaban cortas
  (`Contratos`, `Vacaciones`, `Movimientos`, `Licencias`), más un caso nuevo de Vitest para el selector
  de estado del contrato.

## Decisiones tomadas

- **«Empleado» es una caja de búsqueda y no un desplegable, y usa la clave `buscar`.** Una empresa de
  doscientas personas produce un selector que no se recorre, y quien registra una novedad conoce el
  nombre o la cédula. Reutilizar la clave de empleados tiene una consecuencia concreta: `useListado`
  ya trae `busqueda` y `buscar()` con su espera de 400 ms, así que **el composable no se tocó** y las
  cuatro cajas de búsqueda del sistema se comportan igual.
- **«Cargo» filtra por el cargo vigente de la vinculación**, no por el que la persona tenía el día de
  la novedad: ninguna de las cinco tablas lo guarda, y el vigente es el que la columna enseña y el que
  va a la exportación. En movimientos eso significa que filtrar por «Coordinadora de Operaciones» trae
  también la promoción que se lo dio, que es lo que se busca al preguntar por un cargo.
- **«Estado del contrato» no inventó ningún estado.** El documento 1 pide el filtro y el documento 3,
  5.4 no lo lista entre los tres de esa pantalla; la salida sin inventar nada fue **filtrar por la
  columna que la tabla ya muestra**. `tono()` pasó a derivarse de `estadoDeGestion()`, así que hay una
  sola definición del estado en PHP, y `acotarAlEstado()` es su traducción a SQL —hecha falta porque
  filtrar en PHP obligaría a traer la empresa entera—. **La prueba que las mantiene juntas** recorre
  las filas de cada estado comprobando su tono, y una tercera comprueba que los tres reparten entre
  ellos todos los contratos sin dejar ninguno fuera.
- **Un contrato sin plazo está «al día».** Indefinido y obra o labor no tienen fecha que se les pueda
  pasar, y ya recibían el verde de `tono()`.
- **Las siete exportaciones ya cumplían 11.2 y no se tocaron.** Lo que faltaba no era código sino la
  prueba que lo comprueba en las siete a la vez: hoy la escribe un solo caso que recorre la lista, y
  la octava exportación no puede olvidarlo sin ponerlo en rojo.
- **Auditoría queda fuera de esta tarea a propósito.** Es el octavo módulo de la tabla 11.3 y no tiene
  pantalla; sus cuatro filtros llegan con `REV-04`.
- **El panel de alertas sigue sin exportación**, como decidió F3-06: el documento 2, 5.5 pide un
  registro «consultable» y el capítulo 11 no lo lista entre los módulos que exportan.

## Preguntas para Angela

- **Del filtro «Estado del contrato», para confirmar y sin que frene nada: los tres nombres.** «Al
  día», «Plazo de preaviso pasado» y «Vencido sin gestionar» los redactó el sistema a partir de lo que
  la columna ya decía. Los estados en sí no son una decisión suya —son los que el propio documento y
  el artículo 46 del CST producen—, solo cómo se llaman en el desplegable.
- **Heredadas, las cuatro de sesiones anteriores**: el texto del correo de prueba de entregabilidad,
  los textos del correo de obra o labor, el texto del contrato que vence el día mismo del tope y
  «acumula 3 prórrogas inferiores a un año».

## Decisiones para David

- **Todas las heredadas siguen abiertas**, empezando por la que no depende de código: **escoger el
  proveedor transaccional, apuntar el dominio y crear SPF, DKIM y DMARC**, que es la única parte del
  «hecho cuando» de F3-06 que ninguna prueba puede cubrir. Y detrás, la lista larga de REV-02: la
  regla de la fecha entre paréntesis fuera de los correos, meses comerciales de treinta días, prórroga
  mínima, plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto
  del empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **F4-02 es la siguiente y es justo el alivio que llevan tres sesiones anotándose.** Los dos
  resúmenes mensuales de vacaciones y `CercaniaAlTope` recorren a todos los activos porque el saldo es
  una función y no una columna; la caché de saldos es lo que lo arregla. **Nunca fuente de verdad**: si
  hay diferencia, manda el cálculo (documento 2, 3.4).
- **F4-01 no tocó ninguna consulta de saldo**, así que el sitio que hay que mirar cuando llegue una
  empresa grande sigue siendo el mismo.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
- **La deuda de filtros del backlog quedó pagada entera.** Los avisos que F2-04, F2-05, F2-07 y F2-08
  dejaron escritos en sus propias clases —«esto es F4-01»— se borraron al hacerlo, así que no queda
  ningún comentario prometiendo un filtro que ya existe.
- **`REV-03`, `REV-04` y `REV-05` siguen al final del backlog.** `REV-05` no es opcional: hoy el
  sistema le manda a un empleado de obra o labor las alertas de periodo de prueba, que no le
  corresponden por ley. **`REV-04` ganó un motivo más**: la pantalla de auditoría es la única de la
  tabla 11.3 del documento 1 que sigue sin sus filtros, y esta tarea no pudo dárselos porque no hay
  pantalla donde ponerlos.
- **Si F4-03 filtra el listado de contratos desde una tarjeta del panel de inicio**, ya tiene por
  dónde: `estado=vencido` con `vence=todos` es exactamente «los que vencieron sin gestionar», el mismo
  número que la cabecera de contratos enseña en su aviso.
