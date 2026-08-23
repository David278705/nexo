# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F4-03 (cerrada: panel de inicio, bloque de acción)
**Verificación:** verde — Pint, PHPStan sin errores, **943** pruebas de Pest, build y 286 de Vitest.
**Siguiente tarea:** F4-04 · Panel de inicio, indicadores.

## Qué se hizo

- **Las cuatro tarjetas del prototipo**, cada una enlazando a su listado ya filtrado: contratos
  vencidos sin gestionar, contratos por vencer con sus tres cifras acumuladas, periodos de prueba que
  terminan esta semana y personas con un periodo completo de vacaciones acumulado.
- **`App\Inicio\BloqueDeAccion`**, con las dos vueltas —`paraElUsuario()` y
  `diferidasParaElUsuario()`—; **`TarjetaDeAccion`**, el valor que viaja a la pantalla; y
  **`AtencionDelInicio`**, el enumerado de los dos motivos que el listado de empleados sabe filtrar.
- **`InicioController`**: la ruta `/` dejó de ser una función suelta en `routes/web.php`.
- **`resources/js/Components/TarjetaDeAccion.vue`** y la pantalla `Inicio.vue` con el bloque.
- **El filtro `atencion` de `ListadoDeEmpleados`**, que no se ofrece en la barra pero sí sale como
  etiqueta removible.
- **`SaldoDeVacaciones::diasDeUnPeriodoCompleto()`**, que salió de dentro de `AcumulacionDeQuinceDias`;
  y **`VigenciaDelContrato::VENCIDO`**, que era un literal escrito en tres sitios.
- **`tests/Feature/PanelDeInicioTest.php`, 25 casos**, más seis de Vitest entre `TarjetaDeAccion` e
  `Inicio`.

## Decisiones tomadas

- **La tarjeta que tarda no hace esperar al panel, y la medición es el motivo.** Con doscientos
  empleados, resolver el bloque entero de una vez tardaba **4,8 segundos**; separando la de vacaciones
  a `Inertia::defer`, la primera vuelta baja a **0,007**. Contar a quién le sobran quince días obliga
  a pedirle el saldo al motor uno por uno porque **el saldo se calcula, no se guarda**, y las otras
  tres salidas eran peores: leerlo de `CacheDeSaldos` la volvería la fuente de verdad **sobre una
  persona** —que es justo lo que F4-02 impidió quitándole el método—, tocar el motor por velocidad es
  donde se rompen los números, y quitar la tarjeta habría sido recortar el alcance por conveniencia.
- **Una tarjeta en cero no se pinta.** Es la misma decisión que `ResumenMensual` tomó con los correos
  mensuales: un bloque llamado «Requiere tu atención» que enseña cuatro ceros enseña a no mirarlo. La
  de contratos por vencer se pinta si hay algo en **noventa** días aunque la cifra grande de treinta
  sea cero, porque esconderla por eso sería esconder lo que existe para enseñar.
- **Ninguna tarjeta lleva a un 403.** Cada una declara su módulo y lo decide `PoliticaDeModulos`, el
  mismo que responde en la ruta de destino, así que el panel y esa ruta no pueden discrepar. Es el
  criterio de `AlertasDelUsuario` con el panel de alertas.
- **El filtro `atencion` no se ofrece en la barra y no es una omisión.** La tabla 11.3 fija los
  filtros que la barra **ofrece**, y empleados los tiene los cinco desde F4-01; nadie escoge «periodos
  de prueba que terminan esta semana» de un desplegable. Es el destino de las tarjetas, que la sección
  5.2 exige que sean «un enlace directo al listado filtrado». Sí sale como etiqueta removible: sin
  ella, quien pulsa la tarjeta ve un listado corto sin saber por qué le faltan empleados.
- **Los dos motivos se resuelven contando y devuelven identificadores**, porque ninguno es una
  columna. El de periodo de prueba lleva la **ventana de tres meses de ingresos** de
  `ReglaDePeriodoDePrueba`, con su mismo motivo escrito: la ventana es una optimización y quien decide
  es la comparación.
- **La tarjeta de periodo de prueba deja fuera al contrato de obra o labor.** La sección 9.6 dice que
  no le corresponde ninguna de esas alertas. Las tres alertas todavía se las mandan —es `REV-05`, que
  sigue pendiente—, y la decisión fue **no repetir el error en una pantalla nueva** mientras tanto.
  Hasta que REV-05 se haga, el panel y el correo discrepan en ese punto, y el que está bien es el panel.
- **La cuarta tarjeta se llama por lo que cuenta y no «vacaciones en riesgo».** En 11.4 ese reporte
  son dos poblaciones: la de los quince días acumulados y la de quien está por cumplir un año sin
  haber disfrutado seis días continuos. La segunda ya tiene aviso propio, el correo mensual
  `AnioSinDisfruteMinimo`, y no se suma aquí; usar el nombre del reporte para una sola de las dos
  diría un número que no es el del reporte.
- **La tarjeta de vacaciones va al listado de empleados y no al de vacaciones, contra el prototipo.**
  La cifra habla de **personas** y el listado de vacaciones enseña periodos registrados, que es
  justamente lo que esta gente no tiene: la tarjeta diría cuatro y la pantalla enseñaría cero filas.
- **Los quince días salieron de la alerta al motor.** `SaldoDeVacaciones::diasDeUnPeriodoCompleto()`
  los calcula desde la constante del artículo 186, y ahora lo preguntan dos: `AcumulacionDeQuinceDias`
  y la tarjeta. Dos declaraciones del mismo quince se separan solas.
- **El azul del prototipo en la tarjeta de periodo de prueba no se copió.** El azul de acción es solo
  para lo clicable (regla de interfaz), y aquí la tarjeta entera ya lo es. El único color es el rojo
  de los contratos vencidos, que es lo único que está mal de verdad.

## Preguntas para Angela

- **Heredadas, las cinco de sesiones anteriores**: los tres nombres del filtro «Estado del contrato»,
  el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».
- **D.4 sigue abierta y bloquea la siguiente tarea**: faltan los cortes de los rangos de edad y de
  antigüedad del dashboard. Es el único pendiente marcado **BLOQUEANTE** en el documento 6, y F4-04 lo
  necesita para el bloque de composición.

## Decisiones para David

- **Todas las heredadas siguen abiertas**, empezando por la que no depende de código: **escoger el
  proveedor transaccional, apuntar el dominio y crear SPF, DKIM y DMARC**, que es la única parte del
  «hecho cuando» de F3-06 que ninguna prueba puede cubrir. Y detrás, la lista larga de REV-02: la
  regla de la fecha entre paréntesis fuera de los correos, meses comerciales de treinta días, prórroga
  mínima, plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto
  del empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **F4-04 llega con el patrón de rendimiento ya resuelto y va a necesitarlo.** Todo indicador que
  recorra la planta —el saldo acumulado total, la antigüedad promedio, la distribución por edad— va en
  `Inertia::defer`, como la tarjeta de vacaciones. El total del saldo es la excepción: para ese está
  `CacheDeSaldos::total()`, que responde en milésimas y trae la fecha en `TotalDeSaldos::$al` para que
  la pantalla escriba «actualizado al 22 de agosto».
- **F4-04 está bloqueada por D.4 en dos de sus seis bloques.** Los rangos de edad y de antigüedad no
  se pueden inventar. Los otros cuatro bloques —vacaciones, planta, rotación, ausentismo, movimientos—
  no dependen de ella, así que la tarea se puede hacer casi entera y dejar esos dos con su motivo
  escrito, como se hizo con las pestañas del panel del proveedor en F1B-03.
- **La prueba de los dos segundos vigila la primera vuelta y nada más.** Si F4-04 mete un indicador
  caro en la carga inicial, se pone en rojo sola. Es a propósito.
- **`REV-05` subió de prioridad.** Hoy el panel y el correo dicen cosas distintas sobre el periodo de
  prueba de obra o labor: el panel no lo cuenta y las tres alertas se lo siguen mandando. La
  discrepancia es información, pero conviene cerrarla antes de que alguien la note desde fuera.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
- **`REV-03` y `REV-04` siguen al final del backlog**, sin cambios.
