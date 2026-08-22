# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F3-04 (bloqueada parcialmente)
**Verificación:** verde
**Siguiente tarea:** F3-05 · Resúmenes mensuales

## Qué se hizo

- **Las dos alertas del preaviso del término fijo, con sus textos exactos.** `PlazoDeRenovacion`
  —32 días antes del vencimiento— y `EfectoDelSilencio` —30 días antes—, en
  `app/Dominio/Alertas/Reglas/`, ya registradas en el catálogo, así que el proceso diario las manda.
  Son las dos primeras filas de la sección 9.4.
- **`ReglaDePreavisoDelFijo`, la base que comparten.** Las dos cuelgan de la misma fecha —la fecha
  límite del preaviso— y solo se diferencian en cuántos días antes de ella avisan y en qué dicen.
  Candidatas, contrato activo, vencimiento vigente y armado de la alerta se escriben una vez.
- **El «32» no existe en el código.** Es `HistorialDeContrato::fechaLimiteDePreaviso()` —que lee
  `preaviso_dias` de la configuración— menos los dos días que el propio mensaje anuncia. Escribir 32
  sería guardar dos veces el mismo número legal: el día en que el preaviso deje de ser de treinta
  días, esa alerta quedaría mintiendo sola.
- **Las otras dos de la sección 9.4 quedaron bloqueadas**, con la tarea marcada `[!]` y el motivo en
  `BACKLOG.md`. Las preguntas están abajo.
- `composer verificar` en verde: Pint, PHPStan sin errores, **806** pruebas de Pest (13 nuevas), build
  y 275 de Vitest.

## Decisiones tomadas

- **«Si no se ha registrado prórroga ni terminación» no se pregunta aparte: se cumple por
  construcción.** La terminación deja la vinculación inactiva y `activas()` la saca de las
  candidatas; la prórroga mueve el vencimiento vigente y con él la fecha límite del preaviso, así
  que el disparo del plazo viejo desaparece y aparece el del tramo nuevo, que es justo lo que
  corresponde. Escribir la condición otra vez sería tener la misma regla en dos sitios, y la segunda
  copia se separa sola. Las dos mitades tienen prueba.
- **La `fechaDelHecho` de las dos es la fecha límite del preaviso, no el vencimiento.** Es de ella de
  la que hablan los mensajes en relativo —«en dos días», «hoy»—, y es la que tiene que ir entre
  paréntesis por 9.1.
- **La de los 32 días escribe dos fechas y cada una a su manera.** El vencimiento del contrato va
  entero y con año con `Formato::fechaLarga()`, porque puede caer en el año siguiente y el mensaje no
  habla de él en relativo; la fecha límite del preaviso la escribe `ReferenciaTemporal`, que es la
  que se ajusta al corrimiento.
- **Los dos asuntos no llevan referencia temporal y por eso no se tocan.** Es la diferencia con
  F3-03, donde tres de los cinco asuntos traían la cuenta escrita a mano y hubo que reescribirlos:
  los de la sección 5.4 para estas dos —«Definir renovación del contrato de {Nombre}» y «Último día
  para avisar la no renovación de {Nombre}»— no afirman ningún plazo, así que se copian literales.
- **Un contrato tan corto que el plazo del preaviso caería antes del ingreso no manda nada.** Con
  veinte días pactados, los treinta días de preaviso quedan en diciembre del año anterior. Tiene
  prueba, igual que su gemela de F3-03.
- **El indefinido y la obra o labor no reciben ninguna de las dos.** No tienen fecha de finalización
  previsible, que es lo que dice expresamente la sección 9.6.

## Preguntas para Angela

- **¿Con cuánta anticipación avisa la alerta de cercanía al tope de cuatro años?** Es la decisión
  **D.3** del documento 6, marcada BLOQUEANTE. La tabla 9.4 solo dice «cuando se aproxima a los
  cuatro años» y no hay número en `config/reglas_laborales.php`. El propio documento 6 sugiere seis
  meses antes y de nuevo a los tres meses; **falta confirmarlo**, y hay que decir si son uno o dos
  avisos, porque son una o dos filas del catálogo.
- **¿Cuál es «el límite de prórrogas cortas» de la alerta de última renovación corta, y qué día
  dispara?** La tabla lo dice en singular, pero el sistema tiene dos límites distintos —cuatro
  prórrogas pactadas y tres automáticas, del Concepto MinTrabajo 6157—. Disparar en cuanto se agote
  el primero afirmaría en un correo que «la próxima renovación deberá ser de mínimo un año» cuando
  todavía podría ser corta del otro tipo, y eso es afirmar algo falso sobre una regla legal. Falta
  además el día: «cuando llega al límite» puede ser el día en que se registra la prórroga que lo
  agota, o el mismo día del aviso de renovación, 32 días antes del vencimiento siguiente.
- **¿La obra o labor debe recibir las tres alertas del indefinido, como quedó, o solo alguna?**
  (heredada de F3-03). La sección 9.6 dice que le aplican «cuando corresponda» sin decir cuáles.

## Decisiones para David

- **Todas las heredadas siguen abiertas**: la regla de la fecha entre paréntesis fuera de los
  correos, meses comerciales de treinta días, prórroga mínima, plantilla de carga, fondo de
  cesantías, la prueba intermitente de `CatalogosTest`, el texto del empleado inactivo, PHP 8.2
  contra el 8.3 que pide `CLAUDE.md`, la frase del prototipo sobre el saldo negativo, y el resto de
  la lista larga de REV-02.

## Notas para la próxima sesión

- **La siguiente tarea es F3-05, los cuatro resúmenes mensuales**, y son de otra forma: van agrupados
  en un solo correo por empresa, sin vinculación, con `AlertaPorEnviar::paraLaEmpresa()`, que existe
  desde F3-01 y todavía no la usa nadie. Su día es «el último día hábil del mes».
- **Ojo con dos de los cuatro resúmenes**: el de obra o labor prolongada depende de la decisión
  **D.2**, que sigue abierta —no hay umbral de meses—, así que probablemente se bloquee igual que las
  dos de esta sesión. Los otros tres —contratos vencidos sin gestionar, acumulación de 15 días y año
  cumplido sin disfrute mínimo— están completos en 9.4, 9.5 y 5.4.
- **Cuidado con `ReferenciaTemporal` en el resumen de contratos vencidos**: habla de fechas pasadas y
  esa clase lanza excepción a propósito con un hecho anterior al envío. Ese resumen escribe la fecha
  de vencimiento entera y los días transcurridos, no una referencia relativa.
- **Al retomar F3-04 no hay que construir nada de infraestructura**: `ReglaDePreavisoDelFijo` no
  sirve para las dos que faltan —ninguna cuelga del preaviso—, pero `HistorialDeContrato` ya responde
  todo lo que necesitan: `prorrogasAcumuladas()`, `prorrogasCortasRestantes()`,
  `contratoInicialEsInferiorAUnAnio()`, `fechaDelTopeLegal()` e `inicioDelConteoDelTope()`, este
  último con la regla C.2 de la reforma ya aplicada.
- **La prueba «el catálogo trae las reglas construidas y solo esas»** —en
  `AlertasDePeriodoDePruebaTest.php`— ahora enumera siete tipos. Es a propósito la que se pone roja
  cuando alguien registra una regla sin querer; al sumar las de F3-05 hay que actualizarla.
- **El punto que hay que mirar en el próximo control** sigue siendo el contexto de empresa en segundo
  plano: `EnviarAlerta` serializa dos identificadores y no un modelo. Está probado, pero es el sitio
  donde una fuga sería más fácil.
- **Aviso para F5-03:** el ambiente de demostración nace `en_prueba` y hoy no recibe alertas. Si tiene
  que enseñar correos, esa tarea cambia una línea en
  `ProcesoDiarioDeAlertas::empresasQueRecibenAlertas()`.
- `REV-03` y `REV-04` siguen al final del backlog, con F5-07 dando por hecha la pantalla de auditoría.
