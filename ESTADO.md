# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F2-07 · Licencias, ausencias y suspensiones
**Verificación:** verde
**Siguiente tarea:** F2-08 · Aumentos y promociones. Módulo con salario y cargo actuales de solo
lectura, y el histórico de la persona.

## Qué se hizo

- **Una sola tabla `licencias` con un campo de clasificación**, no tres tablas: es literal del
  documento 1, 6.5 —«un solo tipo de novedad con un campo de clasificación»—. `TipoDeLicencia` es el
  enumerado de las tres opciones (licencia no remunerada · ausencia · suspensión) y se guarda como
  texto legible, nunca como número. Sin columna de días, igual que incapacidades y vacaciones.
- **`Licencia::dias()` llama a `MotorDeCalculo::diasCorridos()`**, la misma regla de conteo que
  incapacidades y distinta de vacaciones. El caso del documento —jueves a martes, con el domingo
  dentro— da seis días y está probado por la ruta de verdad.
- **El hueco que F2-05 dejó anotado quedó tapado.** `MotorDeCalculo::diasDeSuspensionDelMes()`
  devolvía cero con un comentario que decía por qué: no existía tabla de dónde leer un solo día.
  Ahora recorre las licencias de la vinculación y suma, con `diasSuspendidosDelMes()`, los días de
  cada una que caen en el mes que se está acumulando. **El parámetro
  `suspensiones_afectan_vacaciones` se lee dentro del motor**, no en quien llama, para que ninguna
  pantalla pueda olvidarse de mirarlo.
- **La aritmética de la proporción no se escribió: ya estaba.** `acumulacionProporcional()` existe y
  está probada desde F2-02, así que esta tarea no tocó `SaldoDeVacaciones` más allá de actualizar el
  comentario que anunciaba el sumando pendiente. El caso obligatorio pasa: la licencia del 16 de
  marzo al 15 de abril deja marzo en 0,625 y abril en 0,625 —la mitad de 1,25 en cada uno—.
- **Eliminar una licencia no hace ninguna operación inversa.** `Licencia` usa borrado lógico, así que
  la fila sale sola de la suma la próxima vez que se pida la acumulación; hay prueba que la borra y
  comprueba que marzo vuelve a 1,25.
- **El módulo completo con el patrón compartido**: `ListadoDeLicencias` sobre `Listado` (REV-01) con
  filtro de rango de fechas y de tipo, `LicenciaController`, `RegistrarLicencia`, `LicenciasExport`
  con las tres columnas obligatorias de toda exportación de novedades, y la pantalla
  `Licencias/Index` sobre `useListado` (REV-02) enchufada a `ModalDeNovedad` (F2-03). Los días de la
  novedad que se digita se cuentan en el servidor y llegan por la recarga parcial `diasDeLicencia`:
  en el navegador no se resta ninguna fecha.
- **Las tres pruebas compartidas de siempre se actualizaron**: el caso de aislamiento por las cuatro
  vías en `AislamientoTest.php`, la pantalla real en vez de `SeccionPendiente` en `EstructuraTest.php`
  y `componenteDe()` en `FlujoDeNovedadTest.php`.
- `composer verificar` en verde: Pint, PHPStan sin errores, **648** pruebas de Pest (17 nuevas),
  build y **247** de Vitest (cinco nuevas, las de `Licencias/Index.test.js`).

## Decisiones tomadas

- **El botón de guardar exige el tipo, no solo las fechas.** El tipo es obligatorio en el servidor
  (`Rule::in` sobre los tres valores) y el modal lo refleja: sin tipo elegido no se puede enviar.
  Es la única pieza de lógica propia de esta pantalla que no cubría ya `ModalDeNovedad.test.js`, y
  por eso `Licencias/Index.test.js` es nuevo.
- **El listado ordena por fecha de inicio descendente por defecto**, igual que incapacidades y
  vacaciones: la novedad más reciente arriba.
- **No se filtró por empleado ni por cargo** aunque el documento 1, 11.3 los liste: es F4-01 para los
  tres módulos de novedades a la vez, y adelantarlo aquí habría dejado dos módulos con un filtro que
  el tercero no tiene.

## Preguntas para Angela

- (vacío, ninguna nueva.)

## Decisiones para David

- **Todas las heredadas siguen abiertas** —meses comerciales de treinta días, prórroga mínima,
  plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, el diagnóstico en la pestaña de
  novedades de la ficha, la frase del prototipo sobre el saldo negativo, y el resto de la lista larga
  de REV-02—. Ninguna la tocó esta tarea.

## Notas para la próxima sesión

- **La siguiente es F2-08, Aumentos y promociones.** No es una novedad del capítulo 6 sino un
  movimiento del capítulo 8, 8.1: salario y cargo actuales de solo lectura, el nuevo valor, y el
  histórico de la persona. **Guardar un movimiento actualiza el registro principal de la
  vinculación**, que es lo que lo diferencia de las tres novedades ya construidas: aquí sí se escribe
  en dos sitios y hay que hacerlo en una sola transacción, como ya hace
  `Vinculacion::registrarPeriodo()` con el historial de contrato.
- **El módulo `Modulo::Movimientos` sigue siendo `SeccionPendiente`.** Cuando F2-08 le ponga pantalla
  real hay que tocar las mismas tres pruebas compartidas de siempre: la lista de exclusión de
  `routes/web.php`, la de `EstructuraTest.php` y `componenteDe()` en `FlujoDeNovedadTest.php`.
- **Con F2-07 el motor de cálculo ya no tiene ningún sumando en cero.** `SaldoDeVacaciones` está
  completo: saldo inicial, acumulación con reducción por suspensiones, y disfrute. Si un cálculo sale
  raro de aquí en adelante, es un error de verdad y no una pieza que falte.
- Las notas de arquitectura de REV-01/REV-02 sobre el armazón de listado y las de F2-03/F2-04/F2-05
  sobre el flujo compartido de novedades siguen vigentes y no se repiten aquí.
