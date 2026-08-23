# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea REV-05 (cerrada: alertas de periodo de prueba de obra o labor)
**Verificación:** verde — Pint, PHPStan sin errores, **943** pruebas de Pest, build y 286 de Vitest.
**Siguiente tarea:** F4-04 · Panel de inicio, indicadores.

## Qué se hizo

- **REV-05, adelantada delante de F4-04 el 23 de agosto**: Angela cerró la pregunta que F3-03 había
  dejado abierta. A obra o labor no le corresponde ninguna de las tres alertas de periodo de prueba,
  al revés de lo que decía el documento 1, 9.6, antes de la revisión de agosto.
- `ReglaDePeriodoDePrueba::PRUEBA_GENERAL` dejó de incluir `TipoContrato::ObraLabor`. Un solo punto de
  cambio porque las tres reglas del indefinido —`PruebaIndefinidoUnMes`, `PruebaIndefinidoQuinceDias`,
  `PruebaIndefinidoVispera`— ya delegaban ahí sus modalidades sin repetir la lista.
- La prueba que antes comprobaba que obra o labor **sí** recibía las tres pasó a comprobar que **no**
  recibe ninguna, en `tests/Feature/AlertasDePeriodoDePruebaTest.php`.
- El contrato a término indefinido no se tocó: sigue generando las tres exactamente igual, con la
  misma prueba de siempre.

## Decisiones tomadas

- **No se renombró la constante `PRUEBA_GENERAL`** aunque ahora solo contenga `Indefinido`. Cambiarle
  el nombre era tocar cinco archivos por una tarea que pedía una línea, y el nombre sigue siendo
  correcto: describe la alerta que usa el periodo de prueba general de dos meses, por oposición a la
  quinta parte del término fijo. Se actualizó su comentario para explicar por qué obra o labor salió.
- **El panel de inicio no se tocó.** F4-03 ya dejaba fuera a la obra o labor de la tarjeta de periodo
  de prueba a propósito, anticipando esta corrección. Con el cambio de hoy, panel y correo dejan de
  discrepar, que era la segunda razón por la que esta tarea se adelantó al final del backlog.
- **Ningún dato del cliente cambia por este arreglo.** Ninguna alerta de las tres se había disparado
  todavía para obra o labor en producción real porque no hay producción real: el `.env` sigue en
  `MAIL_MAILER=log` (F3-06b sigue bloqueada). El arreglo es de código, no de limpieza de correos ya
  enviados.

## Preguntas para Angela

- **Heredadas, las cinco de sesiones anteriores**: los tres nombres del filtro «Estado del contrato»,
  el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».

## Decisiones para David

- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Hasta que se haga, ninguna alerta sale de la máquina.
- **D.4 sigue abierta y bloquea dos de los seis bloques de F4-04**: faltan los cortes de los rangos de
  edad y de antigüedad del dashboard. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
  Los otros cuatro bloques de F4-04 —vacaciones, planta, rotación, ausentismo, movimientos— no
  dependen de ella.
- **Todas las demás heredadas siguen abiertas.** Y detrás, la lista larga de REV-02 (ya cerrada como
  tarea, pero con deuda menor pendiente en otros frentes): la regla de la fecha entre paréntesis fuera
  de los correos, meses comerciales de treinta días, prórroga mínima, plantilla de carga, fondo de
  cesantías, la prueba intermitente de `CatalogosTest`, el texto del empleado inactivo, PHP 8.2 contra
  el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F4-04**, ahora que REV-05 quedó cerrada. Llega con el patrón de rendimiento
  ya resuelto de F4-03: todo indicador que recorra la planta va en `Inertia::defer`, salvo el total del
  saldo, que tiene `CacheDeSaldos::total()` respondiendo en milésimas.
- **F4-04 está bloqueada por D.4 en dos de sus seis bloques** —rangos de edad y de antigüedad—. Los
  otros cuatro se pueden hacer enteros y dejar esos dos con su motivo escrito, como F1B-03 hizo con las
  pestañas del panel del proveedor.
- **La prueba de los dos segundos de F4-03 vigila la primera vuelta y nada más.** Si F4-04 mete un
  indicador caro en la carga inicial, se pone en rojo sola.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
- **`REV-03` y `REV-04` siguen al final del backlog**, sin cambios.
