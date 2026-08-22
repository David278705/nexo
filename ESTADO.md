# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F3-03
**Verificación:** verde
**Siguiente tarea:** F3-04 · Alertas de contrato a término fijo

## Qué se hizo

- **Las cinco alertas de periodo de prueba, con sus textos exactos.** Viven en
  `app/Dominio/Alertas/Reglas/` y ya están registradas en el catálogo, así que el proceso diario las
  manda de verdad. Son las tres de la sección 9.2 —mes cumplido, quince días antes y víspera— y las
  dos de la 9.3 —dos días antes y víspera—.
- **`ReglaDePeriodoDePrueba`, la base que comparten.** Las cinco solo se diferencian en tres cosas:
  sobre qué modalidades miran, qué día disparan y qué dicen. Todo lo demás —candidatas, empleado
  activo, de dónde sale la fecha del periodo de prueba, cómo se arma la alerta— se escribe una vez.
  Escrito cinco veces, la que se olvidara de `activas()` mandaría correos sobre gente que ya se fue.
- **`ReglaDeVisperaDePrueba`, el texto que el documento repite.** El asunto y el cuerpo de la víspera
  del indefinido y de la del fijo son idénticos palabra por palabra en la sección 5.4. Se escriben una
  vez; siguen siendo dos tipos distintos porque son dos filas distintas del catálogo y el registro de
  envíos tiene que poder decir cuál salió.
- **Ninguna regla cuenta días.** El final del periodo de prueba lo da `PeriodoDePrueba::terminaEl()`,
  el mes cumplido `MotorDeCalculo::cumpleMesesEl()` y la frase temporal `ReferenciaTemporal`. La
  única aritmética que hay en las reglas es restarle días a la fecha de terminación.
- **`AlertasTest` ahora vacía el catálogo en su `beforeEach`.** Ese archivo mide el armazón con una
  regla de mentira y sus conteos se romperían con las cinco de verdad dentro. Las cinco se prueban en
  `AlertasDePeriodoDePruebaTest`, que sí usa el catálogo real y comprueba que trae las cinco.
- `composer verificar` en verde: Pint, PHPStan sin errores, **793** pruebas de Pest (21 nuevas), build
  y 275 de Vitest.

## Decisiones tomadas

- **La de los 15 días se dispara quince días antes del final, no cuarenta y cinco días después del
  ingreso.** La tabla titula la fila «al cumplir 45 días» y el mensaje dice «completará en 15 días».
  En el ejemplo del propio documento coinciden: ingreso el 15 de abril, fin el 14 de junio, alerta el
  30 de mayo, que es a la vez el día 45 y quince días antes. Dejan de coincidir cuando el periodo cae
  sobre febrero —quien entra el 31 de enero termina el 30 de marzo: día 45 es el 17 de marzo, quince
  días antes es el 15—. **Manda el mensaje**, que es lo que el cliente lee: una alerta que dice «en 15
  días» y sale trece días antes miente sobre un plazo legal.
- **Los asuntos también se ajustan al corrimiento.** Tres de los cinco asuntos de la sección 5.4
  traen la referencia temporal escrita a mano —«Mañana vence…», «quedan 15 días», «quedan 2 días»— y
  eso deja de ser cierto en cuanto el envío se adelanta. Los escribe `ReferenciaTemporal`, con la
  fecha entre paréntesis: la regla de redacción de 9.1 dice «toda referencia temporal» y no distingue
  asunto de cuerpo, y el asunto es lo primero que se lee. Los dos de cuenta atrás quedaron con la
  misma forma —«Periodo de prueba de {Nombre}: termina en 15 días (14 de junio)»— porque «quedan 2
  días» no admite meter la frase dentro sin quedar en mal español.
- **`{Nombre}` y `{Nombre completo}` se resolvieron los dos con `Persona::nombreCompleto()`.** El
  sistema tiene una sola forma canónica de escribir un nombre; inventar una corta para los asuntos
  sería crear una segunda, y ninguna parte del documento dice cómo se abrevia.
- **La del mes cumplido escribe la fecha con año.** Es la única de las cinco sin referencia relativa
  —«Su periodo de prueba termina el {fecha}»— y el final queda a un mes largo, que puede caer en el
  año siguiente. Usa `Formato::fechaLarga()`; las otras cuatro usan `ReferenciaTemporal`, cuya fecha
  entre paréntesis va sin año porque nunca mira más de 15 días.
- **La obra o labor recibe el calendario del indefinido.** No pacta plazo, así que no hay quinta parte
  que calcular y su periodo de prueba es el mismo de dos meses; la sección 9.6 dice expresamente que a
  esta modalidad «aplican también, cuando corresponda, las alertas de periodo de prueba», y las que le
  corresponden son las de su propio calendario.
- **Un contrato tan corto que el aviso caería antes del ingreso no manda nada.** Con siete días
  pactados el periodo de prueba dura un día y «dos días antes» sería la víspera de la contratación.
  Con cuatro días no hay periodo de prueba en absoluto. Las dos tienen prueba.
- **Las candidatas se buscan por ventana de ingreso y la fecha se confirma en PHP.** El día de disparo
  no se puede escribir en SQL: depende de la modalidad, del plazo pactado y de la aritmética de meses
  sin desbordamiento. La ventana son tres meses, holgada a propósito sobre los dos del periodo más
  largo; quien decide es la comparación, no la consulta.

## Preguntas para Angela

- **¿La obra o labor debe recibir las tres alertas del indefinido, como quedó, o solo alguna?** La
  sección 9.6 dice que le aplican «cuando corresponda» sin decir cuáles. Se implementó el calendario
  del indefinido porque su periodo de prueba es el mismo de dos meses, pero conviene que lo confirme.

## Decisiones para David

- **La regla de la fecha entre paréntesis está aplicada ahora también en los asuntos.** Sigue sin
  acotar para el resto de la interfaz: `.claude/rules/30-interfaz.md` la enuncia para toda la interfaz
  y su fuente —documento 1, 9.1— solo para los mensajes de correo. Falta saber si aplica en pantalla.
- **Todas las heredadas siguen abiertas**: meses comerciales de treinta días, prórroga mínima,
  plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, la frase del prototipo sobre el saldo
  negativo, y el resto de la lista larga de REV-02.

## Notas para la próxima sesión

- **F3-04 son las cuatro del término fijo y hereda muy poco de F3-03.** Ninguna cuelga del periodo de
  prueba: dos miran la fecha de finalización del contrato —32 y 30 días antes—, una el número de
  prórrogas acumuladas y otra la antigüedad del vínculo. Lo que sí se reutiliza tal cual es
  `ReferenciaTemporal` y la forma de `ReglaDePeriodoDePrueba`: filtrar por ventana en SQL y confirmar
  la fecha exacta en PHP.
- **Ojo con la del efecto del silencio**: su plantilla dice «si no se ha registrado prórroga ni
  terminación», o sea que la regla tiene una condición de estado además de la fecha. Es la primera de
  las once que la tiene.
- **La de la última renovación corta y la del tope de cuatro años no tienen fecha de disparo escrita**
  en el documento —«cuando llega al límite», «cuando se aproxima a los cuatro años»—. Si al llegar
  ahí no hay un número concreto en 9.4 ni en `config/reglas_laborales.php`, es motivo de bloqueo: no
  se inventa cuánto es «se aproxima».
- **Si F3-04 agrega reglas al catálogo, no hace falta tocar `AlertasTest`**: ya vacía el catálogo. Lo
  que sí hay que actualizar es la prueba «el catálogo trae las cinco del periodo de prueba», que
  enumera los tipos registrados y es a propósito la que se pone roja cuando alguien registra una regla
  sin querer.
- **El punto que hay que mirar en el próximo control** sigue siendo el contexto de empresa en segundo
  plano: `EnviarAlerta` serializa dos identificadores y no un modelo. Está probado, pero es el sitio
  donde una fuga sería más fácil.
- **Aviso para F5-03:** el ambiente de demostración nace `en_prueba` y hoy no recibe alertas. Si tiene
  que enseñar correos, esa tarea cambia una línea en
  `ProcesoDiarioDeAlertas::empresasQueRecibenAlertas()`.
- `REV-04` —la pantalla de auditoría— sigue al final del backlog, con F5-07 dándola por hecha. Las
  notas de arquitectura de REV-01/REV-02 siguen vigentes.
