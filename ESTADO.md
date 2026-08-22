# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F3-04 (tercera y última pasada) · terminada
**Verificación:** verde
**Siguiente tarea:** F3-05 · Resúmenes mensuales

## Qué se hizo

- **El cupo de prórrogas cortas dejó de estar partido en dos.** `config/reglas_laborales.php` tiene
  ahora una sola constante, `prorrogas_cortas` en 3, y las dos viejas —`prorrogas_cortas_pactadas` en
  4 y `prorrogas_cortas_automaticas` en 3— ya no existen. Con ellas se fue
  `EventoContractual::limiteDeProrrogasCortas()`: el cupo es de la cadena entera, así que vive donde
  se cuenta la cadena y no en el enumerado que distingue los tipos.
- **`HistorialDeContrato::prorrogasCortas()` y `prorrogasCortasRestantes()` ya no reciben el evento**
  y filtran por `esProrroga()`. Las tres llamadas de `RenovacionDeContrato` —`hallazgos()`,
  `lineaDeLaProrroga()` y `gastaElUltimoCupoCorto()`— dejaron de pasarlo, y `hallazgos()` perdió el
  parámetro entero: ninguna de las dos validaciones de 5.5 depende ya del tipo de prórroga.
- **`UltimaRenovacionCorta`, la cuarta alerta del término fijo.** Cuelga de `ReglaDePreavisoDelFijo`
  y dispara el mismo día que `PlazoDeRenovacion`. Ya está en el catálogo, así que el proceso diario
  la manda: en un contrato que agotó el cupo salen los dos correos ese día, y hay prueba.
- **La clase base ganó dos ganchos** —`aplicaA()` y `fechaDelHecho()`— y el «margen de dos días» se
  subió a ella. Eran las tres únicas diferencias que la alerta nueva tenía con las dos del preaviso;
  copiar la consulta de candidatas y la guardia del ingreso habría sido escribirlas por segunda vez.
- **`HistorialDeContrato::elTramoVigenteAgotoElCupoCorto()`** es la condición de disparo y el método
  nuevo del historial.
- **La ayuda del selector del modal de renovación cambió de texto**: decía «El límite de prórrogas
  cortas es distinto en cada caso», que era exactamente la lectura que Angela corrigió. Ahora explica
  lo que sí depende del tipo, que es cuánto dura el tramo nuevo.
- `composer verificar` en verde: Pint, PHPStan sin errores, **821** pruebas de Pest, build y 275 de
  Vitest.

## Decisiones tomadas

- **El aviso le toca al contrato cuyo tramo vigente es la prórroga que agotó el cupo**, y no a todo
  contrato con el cupo agotado. Es la lectura literal de 9.4 —«el vencimiento del tramo que agota el
  cupo»— y tiene el efecto correcto: si la empresa renueva después por un año, ya hizo lo que el
  aviso le pedía y el correo no se repite. Un contrato que siga renovándose largo nunca lo vuelve a
  recibir.
- **La plantilla de 5.4 dice «acumula {n} prórrogas» y el sistema escribe «acumula 3 prórrogas
  inferiores a un año».** Son dos palabras de más, y la razón es que el número que importa es el de
  las cortas: en un contrato que además tuvo una prórroga larga en medio, decir el total dejaría al
  lector sin entender por qué la siguiente ya no puede ser corta. La segunda frase va literal.
- **La fecha del hecho de esta alerta es el vencimiento del tramo, no la fecha límite del preaviso.**
  Lo que el mensaje explica es qué puede pactarse a partir de ahí; los treinta días del preaviso son
  otra cosa y de ellos habla el correo hermano, que sale el mismo día.
- **El mensaje no lleva ninguna fecha y es la única de las tres del preaviso que no usa
  `ReferenciaTemporal`.** El texto de 5.4 no nombra ningún día, y no hace falta: lo que anuncia no
  vence, vale de la próxima renovación en adelante.
- **El «margen de dos días» pasó a la clase base.** El documento dice que esta alerta sale en «el
  mismo momento» que la del plazo, y el mismo momento tiene que ser el mismo número, no dos
  constantes iguales en dos archivos que un día alguien cambie por separado.
- **La prueba nueva de `ReglasLaboralesTest` comprueba que las dos claves viejas devuelven nulo.** Es
  lo que impide que alguien vuelva a leer `prorrogas_cortas_pactadas` sin darse cuenta de que ya no
  existe: un `config()` de una clave inexistente no falla solo.

## Preguntas para Angela

- **Para confirmar, no para desbloquear: el texto del contrato que vence el día mismo del tope.**
  Viene de la sesión anterior y sigue igual. Hoy dice «Si no se avisa, el contrato ya no puede
  prorrogarse: el vínculo cumple el tope legal de cuatro años el (fecha) y a partir de esa fecha se
  convierte en indefinido por ley». La consecuencia es la que pide D.3; la frase la escribió el
  sistema.
- **Para confirmar, tampoco bloquea: «acumula 3 prórrogas inferiores a un año».** La plantilla del
  documento 2, 5.4, dice «acumula {n} prórrogas» a secas. El motivo del cambio está arriba.
- **D.2 sigue abierta y bloquea uno de los cuatro resúmenes de F3-05**: no hay umbral de meses para
  considerar prolongado un contrato de obra o labor.

## Decisiones para David

- **`.claude/rules/20-calculo-y-reglas-legales.md` quedó desactualizado y no se pudo tocar.** Su
  lista de valores vigentes todavía dice «4 prórrogas pactadas cortas y 3 automáticas antes del
  mínimo de un año». Hay que cambiarlo por «3 prórrogas inferiores a un año, en una sola cuenta para
  toda la cadena, sean pactadas o automáticas (Concepto MinTrabajo 6157 de 2026 y sección C.1 del
  documento 6)». La edición de los archivos de `.claude/rules/` pide permiso y esta sesión corre sin
  interacción. **Es la última copia del número viejo que queda en el repositorio.**
- **Todas las heredadas siguen abiertas**: la regla de la fecha entre paréntesis fuera de los
  correos, meses comerciales de treinta días, prórroga mínima, plantilla de carga, fondo de
  cesantías, la prueba intermitente de `CatalogosTest`, el texto del empleado inactivo, PHP 8.2
  contra el 8.3 que pide `CLAUDE.md`, la frase del prototipo sobre el saldo negativo, y el resto de
  la lista larga de REV-02.

## Notas para la próxima sesión

- **La siguiente es F3-05, los cuatro resúmenes mensuales**, y son de otra forma: van agrupados en un
  solo correo por empresa, sin vinculación, con `AlertaPorEnviar::paraLaEmpresa()`, que existe desde
  F3-01 y todavía no la usa nadie. Su día es «el último día hábil del mes».
- **Uno de los cuatro se va a bloquear**: el de obra o labor prolongada depende de D.2, que no tiene
  umbral. Los otros tres —contratos vencidos sin gestionar, acumulación de 15 días y año cumplido sin
  disfrute mínimo— están completos en 9.4, 9.5 y 9.6. Si se bloquea uno, es `[!]` y se termina la
  sesión: no se pasa a F3-06.
- **El umbral de D.2, cuando llegue, va en `config/alertas.php`** y suma un renglón a la pestaña
  «Umbrales» de `CatalogosDelSistema`. El camino ya está hecho y probado desde la sesión anterior.
- **Cuidado con `ReferenciaTemporal` en el resumen de contratos vencidos**: habla de fechas pasadas y
  esa clase lanza excepción a propósito con un hecho anterior al envío. Ese resumen escribe la fecha
  de vencimiento entera y los días transcurridos, no una referencia relativa.
- **La prueba «el catálogo trae las reglas construidas y solo esas»** —en
  `AlertasDePeriodoDePruebaTest.php`— ahora enumera **nueve** tipos, que son todas las individuales
  de 9.7. Al sumar las de F3-05 hay que actualizarla; es a propósito la que se pone roja cuando
  alguien registra una regla sin querer.
- **`ReglaDePreavisoDelFijo` tiene tres hijas y dos ganchos.** Si aparece una cuarta alerta colgada
  del preaviso, el sitio es ese; si aparece una que no cuelga del vencimiento vigente, el modelo es
  `CercaniaAlTope`, que es independiente.
- **`CercaniaAlTope` recorre todas las vinculaciones a término fijo activas de la empresa**, sin
  acotar por fecha. Es correcto y está razonado en su docblock, pero es el sitio que hay que mirar si
  el proceso diario se vuelve lento con una empresa grande.
- **El punto que hay que mirar en el próximo control** sigue siendo el contexto de empresa en segundo
  plano: `EnviarAlerta` serializa dos identificadores y no un modelo. Está probado, pero es el sitio
  donde una fuga sería más fácil.
- **Aviso para F5-03:** el ambiente de demostración nace `en_prueba` y hoy no recibe alertas. Si tiene
  que enseñar correos, esa tarea cambia una línea en
  `ProcesoDiarioDeAlertas::empresasQueRecibenAlertas()`.
- `REV-03`, `REV-04` y `REV-05` siguen al final del backlog. **`REV-05` no es opcional**: hoy el
  sistema le manda a un empleado de obra o labor las alertas de periodo de prueba, que no le
  corresponden por ley.
