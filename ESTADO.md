# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F3-05 · **bloqueada parcialmente** (tres de los cuatro resúmenes hechos)
**Verificación:** verde
**Siguiente tarea:** F3-06 · Entregabilidad y registro

## Qué se hizo

- **`ResumenMensual`, la base de los resúmenes.** Recoge las tres cosas que separan un resumen de las
  nueve alertas individuales: sale el último día hábil del mes, va agrupado en un solo correo por
  empresa con `AlertaPorEnviar::paraLaEmpresa()` —que existía desde F3-01 y hasta hoy no usaba
  nadie—, y no se manda si no hay a quién listar.
- **`ContratosVencidosSinGestionar`** (9.4, última fila), **`AcumulacionDeQuinceDias`** y
  **`AnioSinDisfruteMinimo`** (9.5), las tres registradas en el catálogo, así que el proceso diario
  ya las manda.
- **Dos métodos nuevos del motor.** `ultimoDiaHabilDelMes()`, que se apoya en
  `diaHabilAnteriorParaAlertas()` para que «último día hábil» y «corrimiento» sean la misma noción de
  día de oficina; y `diasTranscurridos()`, que es el número que escribe el resumen de contratos
  vencidos y no es ni días corridos ni una anticipación.
- **`tests/Feature/ResumenesMensualesTest.php`**, con veinte casos: el «hecho cuando» de la tarea —un
  solo correo, sin vinculación, con los tres empleados dentro—, la idempotencia, el día de salida en
  un mes que termina en lunes y en otro que termina en domingo, los textos literales de los tres, y
  las salidas del listado por prórroga y por terminación.
- **La prueba «el catálogo trae las reglas construidas y solo esas» ahora enumera doce tipos.**
- `composer verificar` en verde: Pint, PHPStan sin errores, **841** pruebas de Pest, build y 275 de
  Vitest.

## Decisiones tomadas

- **`AnioSinDisfruteMinimo` avisa en cada aniversario, no solo en el primero.** La plantilla dice
  «cumplen el mes entrante un año en la empresa», y la frase siguiente de la misma plantilla dice por
  qué: «que es el mínimo anual que exige la ley». Un aviso que solo mirara el primer aniversario
  dejaría fuera para siempre a quien lleva cuatro años sin tomar seis días seguidos, que es
  exactamente la persona de la que habla el artículo 190. El texto dice «un año **de servicio**»,
  dos palabras más que la plantilla, porque a los tres años «cumple un año en la empresa» sería
  falso.
- **«Seis días hábiles continuos» se lee como un solo periodo registrado**, no como la suma de
  varios cortos. Dos periodos de tres días no son un descanso de seis, y hay prueba del caso.
- **El número va en cifra —«al menos 6 días hábiles continuos»— y no en letras como la plantilla.**
  Sale de `disfrute_minimo_dias_habiles`, y escribir «seis» sería guardar un número legal en el texto
  del correo: el día en que la norma cambie, el correo mentiría sin que nadie lo notara.
- **Los quince días de la acumulación tampoco están escritos**: son doce meses a
  `dias_vacaciones_por_mes`. Es el mismo número legal visto de otra forma.
- **La acumulación mira el fin del mes calendario y los contratos vencidos miran el corte.** No es
  una inconsistencia: son dos textos distintos. «Completaron **este mes** 15 días» exige el mes
  contado entero, y el saldo causa el mes completo al cerrarse, así que preguntarlo el viernes 29
  daría el saldo de julio. «Superaron su fecha de finalización» habla en pasado y tiene que ser
  cierto el día en que el correo sale.
- **Un resumen sin nadie no se manda.** Un correo mensual que dice «0 contratos vencidos» enseña a
  ignorar el remitente, y con él se ignoraría el mes en que sí traía algo.
- **La fecha del hecho de los tres es el corte del resumen.** Ninguna de las cuatro plantillas trae
  una referencia relativa que `ReferenciaTemporal` pueda ajustar —un resumen no dice «mañana»—, así
  que las fechas que importan van escritas enteras dentro de cada línea del listado.

## Preguntas para Angela

- **D.2 sigue abierta y es lo que dejó la tarea a medias.** ¿A partir de cuántos meses sin registrar
  terminación debe aparecer un contrato de obra o labor en el resumen mensual? No hay criterio legal
  del que deducirlo: es una señal de gestión y depende de qué tan largas suelen ser las obras en las
  empresas del segmento. Con el número, la alerta que falta es media sesión: el tipo ya está en el
  catálogo, la base `ResumenMensual` está escrita y probada, y el renglón le espera en
  `config/alertas.php`.
- **Para confirmar, no para desbloquear: «cumple un año de servicio» en vez de «cumple un año en la
  empresa».** El motivo está arriba. Si la intención era avisar solo del primer aniversario, es
  cambiar una comparación y la frase vuelve a ser la literal.
- **Para confirmar, tampoco bloquea: «al menos 6 días hábiles continuos»**, con cifra y no con
  letras.
- **Heredadas, las dos de la sesión anterior**: el texto del contrato que vence el día mismo del tope
  y «acumula 3 prórrogas inferiores a un año».

## Decisiones para David

- **`.claude/rules/20-calculo-y-reglas-legales.md` sigue desactualizado y sigue sin poderse tocar.**
  Su lista de valores vigentes todavía dice «4 prórrogas pactadas cortas y 3 automáticas antes del
  mínimo de un año». Hay que cambiarlo por «3 prórrogas inferiores a un año, en una sola cuenta para
  toda la cadena, sean pactadas o automáticas (Concepto MinTrabajo 6157 de 2026 y sección C.1 del
  documento 6)». La edición de los archivos de `.claude/rules/` pide permiso y estas sesiones corren
  sin interacción. **Es la última copia del número viejo que queda en el repositorio.**
- **Todas las heredadas siguen abiertas**: la regla de la fecha entre paréntesis fuera de los
  correos, meses comerciales de treinta días, prórroga mínima, plantilla de carga, fondo de
  cesantías, la prueba intermitente de `CatalogosTest`, el texto del empleado inactivo, PHP 8.2
  contra el 8.3 que pide `CLAUDE.md`, la frase del prototipo sobre el saldo negativo, y el resto de
  la lista larga de REV-02.

## Notas para la próxima sesión

- **F3-05 queda `[!]` y hay que volver a ella cuando llegue D.2**, no rehacerla: lo construido está
  completo y probado. Lo único que falta es `ObraOLaborProlongada`, que hereda de `ResumenMensual`,
  lista las vinculaciones de obra o labor activas cuyo ingreso lleve más del umbral, y suma su
  renglón a `config/alertas.php` y a la pestaña «Umbrales» de `CatalogosDelSistema`.
- **La siguiente es F3-06**, entregabilidad y registro: proveedor transaccional, registro de envíos
  consultable y el panel de alertas dentro del sistema como canal alterno. `AlertaEnviada` ya guarda
  destinatarios, estado, intentos y motivo del fallo desde F3-01, así que el registro es de leer, no
  de inventar columnas.
- **La prueba del catálogo enumera doce tipos** y es a propósito la que se pone roja cuando alguien
  registra una regla sin querer. El trece llega con `ObraOLaborProlongada`.
- **Los dos resúmenes de vacaciones recorren a todos los activos de la empresa, sin acotar en SQL**,
  y no es un descuido: el saldo es una función y no una columna, así que no hay condición que
  ponerle a la consulta. Es, junto con `CercaniaAlTope`, el sitio que hay que mirar si el proceso
  diario se vuelve lento con una empresa grande. `Vacacion::dias()` dentro de
  `AnioSinDisfruteMinimo` es un `N+1` conocido y contenido: solo lo pagan los que cumplen año ese
  mes.
- **`AlertaPorEnviar::clave()` deja el hueco de la vinculación vacío en los resúmenes**, así que por
  empresa y por día solo puede haber uno de cada tipo. Es lo que hace que correr el proceso dos veces
  el último día del mes no duplique nada, y tiene prueba.
- **El punto que hay que mirar en el próximo control** sigue siendo el contexto de empresa en segundo
  plano: `EnviarAlerta` serializa dos identificadores y no un modelo. Está probado, pero es el sitio
  donde una fuga sería más fácil.
- **Aviso para F5-03:** el ambiente de demostración nace `en_prueba` y hoy no recibe alertas. Si tiene
  que enseñar correos, esa tarea cambia una línea en
  `ProcesoDiarioDeAlertas::empresasQueRecibenAlertas()`.
- `REV-03`, `REV-04` y `REV-05` siguen al final del backlog. **`REV-05` no es opcional**: hoy el
  sistema le manda a un empleado de obra o labor las alertas de periodo de prueba, que no le
  corresponden por ley.
