# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F2-08 · Aumentos y promociones
**Verificación:** verde
**Siguiente tarea:** F2-09 · Terminaciones. Los siete tipos de salida, los motivos dependientes, el
autocompletado en expiración de plazo y obra terminada, y la categoría de rotación deducida.

## Qué se hizo

- **La tabla `movimientos` guarda el cargo y el salario anteriores, y eso es deliberado.** Es la
  primera tabla de este grupo que guarda un valor «que ya estaba en otro sitio», y no contradice la
  regla 2: lo que la regla prohíbe es guardar un saldo que se puede calcular, y aquí no hay nada que
  calcular. Al guardar el movimiento, el registro principal del empleado cambia, así que la
  vinculación deja de saber qué había antes; sin esta foto el histórico no podría enseñar «el salario
  y el cargo anterior frente al nuevo», que es literal de la sección 8.1.
- **`Vinculacion::registrarMovimiento()` es el único sitio donde se escribe en dos tablas**, y por
  eso vive en el modelo y no en el controlador, igual que `registrarPeriodo()` con el historial de
  contrato. Las dos escrituras van en una transacción: un fallo en medio dejaría un histórico que no
  coincide con lo vigente. Hay prueba de que una petición que no pasa la validación no le toca el
  salario al empleado.
- **El cargo del aumento salarial no lo digita nadie.** «Si el movimiento es solo aumento salarial,
  se repite el mismo cargo» es del documento 1, 8.1, así que es una derivación y no un campo:
  `TipoDeMovimiento::cargoResultante()` la resuelve y el servidor ignora cualquier cargo que llegue
  en un aumento. Hay prueba con un cargo distinto enviado a propósito.
- **El cargo y el salario vigentes se enseñan de solo lectura y llegan del servidor** por la recarga
  parcial `situacionActual`, el mismo patrón de `diasDeLicencia` y `saldoDeVacaciones`. En el
  navegador no se formatea dinero ni se adivina nada, y la prop no se resuelve en la carga de la
  pantalla.
- **La pestaña «Movimientos» de la ficha dejó de estar vacía.** `FichaDeEmpleado::movimientos()`
  devuelve el histórico del más reciente al más antiguo con el cambio escrito en una sola celda
  —«Analista Senior → Coordinadora de Operaciones»—, y cuando el cargo no cambió no dibuja la flecha:
  decir «Conductor → Conductor» sería anunciar un cambio que no hubo. `Ficha.vue` no se tocó: las
  cuatro claves de columna que ya declaraba eran las que hacían falta.
- **El módulo completo con el patrón compartido**: `ListadoDeMovimientos` sobre `Listado` (REV-01)
  con filtro de rango de fecha de efectividad y de tipo, `MovimientoController`,
  `RegistrarMovimiento`, `MovimientosExport` con las tres columnas obligatorias de toda exportación
  más los cuatro valores del movimiento, y la pantalla `Movimientos/Index` sobre `useListado`
  (REV-02) enchufada a `ModalDeNovedad` (F2-03).
- **Las cuatro pruebas compartidas de siempre se actualizaron**: el caso de aislamiento por sus rutas
  reales en `AislamientoTest.php` —con un caso propio: que la empresa B no pueda subirle el salario al
  empleado de la A ni consultar su situación actual—, la pantalla real en vez de `SeccionPendiente` en
  `EstructuraTest.php`, `componenteDe()` en `FlujoDeNovedadTest.php` y la lista `PANTALLAS` de
  `useListado.test.js`.
- `composer verificar` en verde: Pint, PHPStan sin errores, **673** pruebas de Pest (25 nuevas), build
  y **256** de Vitest (nueve nuevas).

## Decisiones tomadas

- **No se validó que el nuevo salario sea mayor que el actual, ni que la promoción estrene un cargo
  distinto.** Ningún documento lo pide y no es una regla legal: un movimiento puede corregir una
  digitación o reflejar un acuerdo que el sistema no tiene por qué juzgar. Queda escrito en
  `RegistrarMovimiento` para que no parezca un olvido.
- **El nuevo cargo de una promoción arranca vacío**, no relleno con el actual. El documento 3, 5.6
  solo pide el relleno automático «si el movimiento es un aumento», y en un aumento el campo es de
  solo lectura: rellenar también la promoción invitaba a guardar el cargo de antes como si fuera el
  nuevo.
- **El listado no filtra por cargo** aunque el documento 1, 11.3 lo liste para este módulo: es F4-01
  para todos los módulos a la vez, igual que el filtro de «Empleado» que les falta a los tres de
  novedades.
- **La tabla se llama `movimientos`, en singular `Movimiento`**, para que el nombre del módulo, la
  ruta, el permiso y el modelo sean el mismo texto y no puedan separarse. El reporte del catálogo
  (11.4) se llama «Movimientos salariales» y así se titula la exportación.

## Preguntas para Angela

- (vacío, ninguna nueva.)

## Decisiones para David

- **Todas las heredadas siguen abiertas** —meses comerciales de treinta días, prórroga mínima,
  plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, el diagnóstico en la pestaña de
  novedades de la ficha, la frase del prototipo sobre el saldo negativo, y el resto de la lista larga
  de REV-02—. Ninguna la tocó esta tarea.

## Notas para la próxima sesión

- **La siguiente es F2-09, Terminaciones**, y trae **una deuda apuntada desde F1-05**: al guardar la
  fecha de salida, `Vinculacion::fechaDeSalida()` tiene que devolverla a ella y no `fecha_fin`. Son
  tres líneas y es de donde sale el énfasis de los 30 días de la advertencia de reingreso.
- **Terminaciones es el último módulo que sigue siendo `SeccionPendiente`** junto con Auditoría. Al
  ponerle pantalla real hay que tocar las mismas cuatro pruebas compartidas: la lista de exclusión de
  `routes/web.php`, la de `EstructuraTest.php`, `componenteDe()` en `FlujoDeNovedadTest.php` y
  `PANTALLAS` en `useListado.test.js`.
- **F2-09 también escribe en dos sitios**: crea la terminación y desactiva la vinculación. El camino
  ya existe —`Vinculacion::desactivar()`— y el patrón de la transacción única es el de
  `registrarMovimiento()` que dejó esta tarea; conviene copiarlo y no inventar otro.
- **F2-10 (edición y borrado de novedades) tendrá que incluir los movimientos**, y ahí sí hay un
  problema propio: deshacer o corregir un movimiento no es solo borrar la fila, porque el registro
  principal del empleado quedó con el valor nuevo. La confirmación con la consecuencia calculada que
  pide la tarea tiene que decir a qué salario y a qué cargo vuelve el empleado.
- Las notas de arquitectura de REV-01/REV-02 sobre el armazón de listado y las de F2-03 a F2-07 sobre
  el flujo compartido de novedades siguen vigentes y no se repiten aquí.
