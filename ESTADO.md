# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F3-04 (segunda pasada, cerrada como `[!]`)
**Verificación:** verde
**Siguiente tarea:** F3-05 · Resúmenes mensuales — los cuatro correos agrupados por empresa

## Qué se hizo

- **`CercaniaAlTope`, la alerta nueva de la cuarta fila de 9.4.** Un solo aviso, **tres meses antes**
  del día en que el vínculo cumple los cuatro años a término fijo. Ya está registrada en el catálogo,
  así que el proceso diario la manda. Es la primera alerta que **no cuelga del vencimiento vigente
  sino del vínculo entero**: las prórrogas que se registren en medio no la mueven.
- **`config/alertas.php`, archivo nuevo.** La anticipación de tres meses vive ahí y no dentro de la
  regla. Es lo que manda el documento 2, 8.1: los umbrales que decide Angela se ajustan sin desplegar
  código. `reglas_laborales.php` no se tocó, porque ahí solo van números con norma citada.
- **`EfectoDelSilencio` ahora escoge entre tres consecuencias**, que son las tres variantes de D.3.
  La primera frase del correo no cambia nunca; la segunda sí, según lo que quepa antes del tope.
- **Dos métodos nuevos de dominio**: `HistorialDeContrato::finDeLaProrrogaAutomatica()` —hasta dónde
  llegaría el contrato si nadie avisa, sin truncar— y `MotorDeCalculo::mesesCumplidos()`, que cuenta
  con la misma regla de la víspera que `cumpleMesesEl()` en lugar de inventar una segunda.
  `Formato::tiempoTranscurrido()` los escribe: «3 años y 9 meses», «un año», «un mes».
- **La pestaña «Umbrales» del panel del proveedor dejó de decir «sin decidir»** en el renglón de la
  anticipación del tope: enseña los tres meses y dónde se cambian. Era el primero de los cuatro que
  se cierra.
- **«Última renovación corta» sigue bloqueada** y por eso la tarea quedó `[!]`. La pregunta, abajo,
  es la misma de la sesión anterior y no ha cambiado.
- `composer verificar` en verde: Pint, PHPStan sin errores, **815** pruebas de Pest (9 nuevas), build
  y 275 de Vitest.

## Decisiones tomadas

- **Las tres variantes de D.3 se deciden comparando dos fechas, sin ningún margen.** El tope frente a
  hasta dónde llegaría la prórroga automática. Si el vencimiento ya está en el tope, no cabe ninguna
  prórroga y el mensaje lo dice; si la prórroga completa se pasaría, queda truncada hasta el tope; si
  cabe, sale el texto literal de la sección 5.4.
- **El «(o prácticamente)» de D.3 no se implementó, a propósito.** Una prórroga que cabe entera y
  termina dos días antes del tope recibe el texto normal, y ese texto es cierto: se prorroga por el
  término completo. Poner un margen de «casi llega» habría sido escribir un umbral legal que no está
  en ninguna norma ni en ninguna decisión.
- **Apareció una cuarta situación que D.3 no nombra y hubo que redactarla: el contrato que vence
  exactamente el día del tope.** No es rara —es justo lo que deja una prórroga apurada hasta el
  límite—, y ahí la prórroga automática no existe: el contrato se vuelve indefinido. El texto se
  compuso con las piezas de las otras dos variantes y con la ley que ya cita D.3; la regla es de
  Angela, la redacción es del sistema. Vale la pena que ella la lea.
- **El «hoy (fecha)» que D.3 escribe al principio de la variante truncada no se repite.** Ya lo dice
  la primera frase del mismo correo. Lo que se copió literal es la consecuencia, que es la parte con
  efecto jurídico.
- **La fecha del tope se escribe entera y con año, no en cuenta atrás.** El texto de 5.4 no trae
  ninguna referencia relativa que `ReferenciaTemporal` pueda ajustar, y el tope cae a meses de
  distancia, casi siempre en el año siguiente. Es el mismo criterio de la alerta del mes cumplido.
- **Al texto de 5.4 se le agregó la fecha del tope** —«que se cumple el 1 de enero de 2030»—. Sin
  ella el correo dice «se acerca al límite» y no dice cuándo, que es exactamente lo que prohíbe la
  regla de redacción de 9.1: quien lo recibe no debe tener que calcular nada.
- **«Lleva {tiempo}» se cuenta desde el inicio del conteo del tope, no desde la firma.** Es la misma
  convención que ya usaba `HistorialDeContrato::duracionTotalEnDias()`. En un contrato posterior a la
  reforma las dos fechas coinciden; en uno anterior, los años previos a la ley no cuentan para el
  tope, y son los años del tope los que el mensaje explica.
- **La consulta de candidatas no filtra por fecha.** El día del disparo sale del inicio del vínculo,
  que puede ser el de la firma o el de la reforma según C.2; escribir esa condición en SQL sería la
  misma regla por segunda vez, en otro idioma y sin prueba.

## Preguntas para Angela

- **¿Cuál es «el límite de prórrogas cortas» de la alerta de última renovación corta, y qué día
  dispara?** La tabla lo dice en singular, pero el sistema tiene dos límites distintos —cuatro
  prórrogas pactadas y tres automáticas, del Concepto MinTrabajo 6157—. Disparar en cuanto se agote
  el primero afirmaría en un correo que «la próxima renovación deberá ser de mínimo un año» cuando
  todavía podría ser corta del otro tipo, y eso es afirmar algo falso sobre una regla legal. Falta
  además el día: «cuando llega al límite» puede ser el día en que se registra la prórroga que lo
  agota, o el mismo día del aviso de renovación, 32 días antes del vencimiento siguiente. Es lo único
  que le falta a F3-04 para cerrarse.
- **Para confirmar, no para desbloquear: el texto del contrato que vence el día mismo del tope.** Hoy
  dice «Si no se avisa, el contrato ya no puede prorrogarse: el vínculo cumple el tope legal de
  cuatro años el (fecha) y a partir de esa fecha se convierte en indefinido por ley». Es la
  consecuencia que D.3 pide para ese caso; la frase la escribió el sistema.
- **D.2 sigue abierta y bloquea uno de los cuatro resúmenes de F3-05**: no hay umbral de meses para
  considerar prolongado un contrato de obra o labor.

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
- **Uno de los cuatro se va a bloquear**: el de obra o labor prolongada depende de D.2, que no tiene
  umbral. Los otros tres —contratos vencidos sin gestionar, acumulación de 15 días y año cumplido sin
  disfrute mínimo— están completos en 9.4, 9.5 y 5.4. Si se bloquea uno, es `[!]` y se termina la
  sesión: no se pasa a F3-06.
- **Cuidado con `ReferenciaTemporal` en el resumen de contratos vencidos**: habla de fechas pasadas y
  esa clase lanza excepción a propósito con un hecho anterior al envío. Ese resumen escribe la fecha
  de vencimiento entera y los días transcurridos, no una referencia relativa.
- **El umbral de D.2, cuando llegue, va en `config/alertas.php`**, que se estrenó en esta sesión con
  la anticipación del tope, y suma un renglón a la pestaña «Umbrales» de `CatalogosDelSistema`. El
  camino ya está hecho y probado: son dos renglones, no una tarea.
- **La prueba «el catálogo trae las reglas construidas y solo esas»** —en
  `AlertasDePeriodoDePruebaTest.php`— ahora enumera **ocho** tipos. Es a propósito la que se pone
  roja cuando alguien registra una regla sin querer; al sumar las de F3-05 hay que actualizarla.
- **`CercaniaAlTope` es la primera regla que recorre todas las vinculaciones a término fijo activas
  de la empresa**, sin acotar por fecha. Es correcto y está razonado en su docblock, pero es el sitio
  que hay que mirar si algún día el proceso diario se vuelve lento con una empresa grande.
- **El punto que hay que mirar en el próximo control** sigue siendo el contexto de empresa en segundo
  plano: `EnviarAlerta` serializa dos identificadores y no un modelo. Está probado, pero es el sitio
  donde una fuga sería más fácil.
- **Aviso para F5-03:** el ambiente de demostración nace `en_prueba` y hoy no recibe alertas. Si tiene
  que enseñar correos, esa tarea cambia una línea en
  `ProcesoDiarioDeAlertas::empresasQueRecibenAlertas()`.
- `REV-03`, `REV-04` y `REV-05` siguen al final del backlog. **`REV-05` no es opcional**: hoy el
  sistema le manda a un empleado de obra o labor las alertas de periodo de prueba, que no le
  corresponden por ley.
