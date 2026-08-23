# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F4-02 (cerrada: caché de saldos)
**Verificación:** verde — Pint, PHPStan sin errores, **915** pruebas de Pest, build y 277 de Vitest.
**Siguiente tarea:** F4-03 · Panel de inicio, bloque de acción.

## Qué se hizo

- **La tabla `saldos_en_cache`**: `tenant_id`, el trait, su política de RLS, índice único
  `(tenant_id, vinculacion_id)`, `dias` en decimal de precisión fija, `calculado_al` y `calculado_en`.
- **`App\Dominio\Calculo\CacheDeSaldos`**, con `refrescar()` y `total()` y **ningún método que
  devuelva el saldo de una persona**, que es lo que hace que la caché no pueda volverse la fuente de
  verdad de nada.
- **`App\Dominio\Calculo\TotalDeSaldos`**, lo que devuelve `total()`: la cifra, la fecha a la que se
  refiere, cuántos empleados la componen y cuántos de ellos se calcularon al vuelo.
- **`App\Models\SaldoEnCache`**, y su entrada en la lista `SIN_AUDITAR` del observador.
- **El proceso diario la refresca**, empresa por empresa, al final de cada vuelta y en su propio
  bloque. `ResumenDelProceso` cuenta las filas refrescadas y las dice en su línea de consola.
- **`tests/Feature/CacheDeSaldosTest.php`, 14 casos**, más tres de aislamiento en `AislamientoTest`.

## Decisiones tomadas

- **El «hecho cuando» está medido y sin la caché no se cumplía.** Con doscientos empleados, sumar el
  saldo de la planta tarda **3,7 segundos calculando y 0,006 leyendo la caché**, y las dos vías dan
  exactamente la misma cifra. Los dos segundos que pide la tarea no se alcanzaban por el camino largo.
- **«Nunca fuente de verdad» se resolvió quitando el método, no escribiendo una advertencia.**
  `CacheDeSaldos` no ofrece el saldo de un empleado por ninguna parte, así que la ficha, la
  advertencia de saldo insuficiente, la confirmación de borrado y los dos resúmenes mensuales de
  vacaciones **no tenían de dónde leerlo aunque quisieran**: siguen llamando a `SaldoDeVacaciones`.
  La tabla la lee un solo consumidor y para una sola cosa, sumar.
- **Los resúmenes mensuales de vacaciones no usan la caché, y no es un olvido.**
  `AcumulacionDeQuinceDias` necesita el saldo al cierre de **dos** meses distintos y
  `AnioSinDisfruteMinimo` mira periodos de disfrute, no el saldo de hoy. La caché guarda una cifra a
  una fecha; servirles a ellos la obligaría a ser la fuente de verdad de fechas que no guarda. La nota
  de la sesión anterior daba a entender que esta tarea los aliviaría: **no los alivia y no debía**.
  A quien alivia es al panel de inicio, que es lo que dice la sección 3.4.
- **`total()` calcula al vuelo a quien no tiene fila todavía.** Un total que se dejara fuera a los
  registrados esta mañana sería un número **equivocado**, y una caché puede dar uno viejo pero no uno
  falso. No los escribe: escribir en medio de una petición de lectura convierte un panel en un proceso
  de mantenimiento. En régimen, después de cada noche, son cero.
- **La fecha del total es la de la caché y viaja dentro del resultado**, para que la pantalla escriba
  «actualizado al 22 de agosto» en vez de dar a entender que la cifra es de este instante. Los que se
  calculan al vuelo se calculan **a esa misma fecha**: un total mezclado de dos fechas no sería el
  saldo de ningún día.
- **La tabla se sale de dos convenciones a propósito**, con el motivo escrito en la migración. **Sin
  borrado lógico**: la regla 4 protege información, y aquí no hay ninguna que proteger —cada fila se
  vuelve a producir entera—, mientras que una fila muerta que hubiera que excluir en cada lectura sí
  añadiría una forma de sumar de más. **Sin auditar**: si no, el refresco nocturno dejaría una línea
  sin autor por empleado y por noche contando que un número derivado se volvió a derivar.
- **El refresco va después de las alertas y en su propio bloque.** La caché es una optimización de
  lectura y las alertas son la promesa que se vende: un fallo recalculando saldos anota la empresa
  como fallida —el operador tiene que enterarse— pero los correos de esa empresa ya salieron.
- **El aislamiento no pasó por `casoDeAislamiento()`.** La tabla no tiene texto por el que buscar ni
  ruta propia, y su único camino de salida es una suma: una fuga no se vería como el dato ajeno en
  pantalla, sino como un total más alto de lo debido. Los tres casos prueban los tres caminos que sí
  existen —el modelo, el total y el refresco—, y el tercero es el que importa: sin filtro de empresa,
  la primera que corriera el proceso dejaría a las demás sin caché.

## Preguntas para Angela

- **Heredadas, las cinco de sesiones anteriores**: los tres nombres del filtro «Estado del contrato»,
  el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».

## Decisiones para David

- **Todas las heredadas siguen abiertas**, empezando por la que no depende de código: **escoger el
  proveedor transaccional, apuntar el dominio y crear SPF, DKIM y DMARC**, que es la única parte del
  «hecho cuando» de F3-06 que ninguna prueba puede cubrir. Y detrás, la lista larga de REV-02: la
  regla de la fecha entre paréntesis fuera de los correos, meses comerciales de treinta días, prórroga
  mínima, plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto
  del empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **F4-03 es la siguiente y ya tiene las dos piezas que necesitaba.** `CacheDeSaldos::total()` le
  resuelve la tarjeta del saldo acumulado de la planta, y F4-01 le dejó el enlace de contratos:
  `estado=vencido` con `vence=todos` es exactamente «los que vencieron sin gestionar», el mismo número
  que la cabecera de contratos enseña en su aviso.
- **Si el panel enseña el total, que enseñe también la fecha.** `TotalDeSaldos::$al` está justamente
  para eso, y `vieneToda()` dice si la cifra salió entera de la caché. Poner el número sin la fecha
  sería reintroducir por la pantalla el problema que la regla 2 evita en la base de datos.
- **La caché queda vacía hasta la primera corrida del proceso diario**, y eso no rompe nada: `total()`
  responde igual, solo que despacio, y hay prueba de ello. En un ambiente nuevo conviene correr
  `php artisan alertas:diarias` una vez para dejarla poblada.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
- **`REV-03`, `REV-04` y `REV-05` siguen al final del backlog.** `REV-05` no es opcional: hoy el
  sistema le manda a un empleado de obra o labor las alertas de periodo de prueba, que no le
  corresponden por ley.
- **El sitio lento que quedaba ya no es el saldo de la planta, sino el refresco mismo**: cuatro
  segundos por empresa de doscientos empleados, de noche y sin nadie esperando. Si algún día molesta,
  el punto es `SaldoDeVacaciones::acumulacionHasta()`, que consulta mes a mes; esta tarea no lo tocó
  a propósito, porque tocar el motor para ganar velocidad es exactamente donde se rompen los números.
