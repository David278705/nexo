# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F3-02
**Verificación:** verde
**Siguiente tarea:** F3-03 · Alertas de periodo de prueba

## Qué se hizo

- **El corrimiento al día hábil anterior quedó completo.** La mitad del calendario ya venía de F3-01
  —el proceso adelanta la evaluación y guarda `fecha_programada` corrida—; lo que faltaba es lo que
  pide la sección 9.1: que el texto se ajuste. Un correo que se adelanta sin ajustar la frase es peor
  que no mandarlo, porque quien lo lee actúa sobre una fecha equivocada.
- **`App\Dominio\Alertas\ReferenciaTemporal`**, la frase que van a compartir las once alertas. Recibe
  las dos fechas que la regla de disparo ya tenía resueltas y escribe `hoy (21 de agosto)`,
  `mañana (…)`, `en dos días (…)` o `en 15 días (…)`. Dos formas: `frase()` para mitad de oración y
  `fraseCapitalizada()` para cuando abre la oración, que son los dos usos que aparecen en las
  plantillas del documento 2, 5.4.
- **La fecha exacta entre paréntesis va siempre**, sin excepción y sin parámetro para omitirla. Es la
  regla de redacción de 9.1 y la única manera de que no se pueda olvidar es que no se pueda apagar.
- **`ReglaDePrueba` ahora redacta con esa clase**, así que el corrimiento se comprueba de punta a
  punta y no solo en el unitario: sábado → «Mañana (22 de agosto)», domingo → «En dos días
  (23 de agosto)», lunes festivo → «En tres días (24 de agosto)». Y el texto ajustado se comprueba
  además dentro del correo renderizado, no solo en la fila de `alertas_enviadas`.
- **El caso obligatorio del motor quedó entero.** «Alerta programada para un domingo → se envía el
  viernes anterior y el texto dice "en dos días" con la fecha» estaba a medias desde F2-01: probaba
  las fechas y no el texto. Ahora prueba las dos mitades.
- `composer verificar` en verde: Pint, PHPStan sin errores, **772** pruebas de Pest (12 nuevas), build
  y 275 de Vitest.

## Decisiones tomadas

- **Las cantidades de una cifra se escriben con letras y las de dos con número.** No es una invención:
  es lo que hacen literalmente las plantillas de la sección 5.4 del documento 2, que dicen «termina en
  dos días ({fecha})» y «completará en 15 días ({fecha})». Con el corrimiento máximo posible —Semana
  Santa: jueves y viernes festivos más el fin de semana— aparecen adelantos de hasta cuatro días, así
  que hacían falta «tres» y «cuatro»; la convención del español los cubre hasta el nueve y de ahí en
  adelante van en cifra.
- **La redacción vive en `app/Dominio/Alertas/` y no en el motor de cálculo ni en `Formato`.** No
  cuenta ni un día: el número lo da `MotorDeCalculo::diasDeAnticipacion()`, que ya existía desde
  F2-02, y la fecha la escribe `Formato::diaYMes()`. Aquí solo se compone la frase, y es una frase
  que solo tienen las alertas.
- **La fecha entre paréntesis va sin año**, como en los dos ejemplos del documento —«(14 de junio)»—.
  La alerta más adelantada del catálogo mira 32 días, así que el año nunca es ambiguo; es el mismo
  criterio con el que `Formato::diaYMes()` se escribió para la advertencia de saldo.
- **Un hecho anterior al envío lanza excepción en lugar de redactar «hace dos días».** Ninguna de las
  once plantillas habla del pasado en relativo: el resumen de contratos vencidos escribe la fecha de
  vencimiento y los días transcurridos, no una referencia relativa. Inventar ese texto sería inventar
  una plantilla. La excepción la contiene el proceso, que ya aísla el fallo de una empresa.
- **Se borraron dos pruebas en vez de sumarlas.** «Adelanta al viernes la alerta que tocaba el
  domingo» quedó contenida en el «hecho cuando» nuevo, que comprueba lo mismo y además el texto, y
  dos casos de `diaHabilAnteriorParaAlertas()` que iba a escribir en `AlertasTest` ya estaban en
  `MotorDeCalculoTest`. Una prueba duplicada no protege el doble: da el doble de sitios donde
  actualizar la misma regla.

## Preguntas para Angela

- (vacío, ninguna nueva.)

## Decisiones para David

- **La regla de «la fecha entre paréntesis» ya está aplicada, y de forma obligatoria, en las alertas.**
  Sigue sin acotar para el resto de la interfaz: `.claude/rules/30-interfaz.md` la enuncia para toda
  la interfaz y su fuente —documento 1, 9.1— solo para los mensajes de correo. Con F3-02 la decisión
  quedó tomada de hecho en el lado de las alertas; lo que falta es saber si aplica también en pantalla.
- **Todas las heredadas siguen abiertas**: meses comerciales de treinta días, prórroga mínima,
  plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, la frase del prototipo sobre el saldo
  negativo, y el resto de la lista larga de REV-02.

## Notas para la próxima sesión

- **La siguiente es F3-03 y ya tiene todo lo que necesita.** Los textos literales están en el
  documento 2, sección 5.4, con asunto y cuerpo para las cinco —tres de indefinido y dos de fijo—;
  las fechas de disparo salen de `MotorDeCalculo::cumpleMesesEl()` y `PeriodoDePrueba::terminaEl()`,
  ya probados; y la frase temporal la escribe `ReferenciaTemporal`. **Ninguna regla debe volver a
  escribir «mañana» a mano**: si aparece un literal así en una regla, el corrimiento no la va a
  ajustar y el correo mentirá el fin de semana.
- **Cuidado con la primera alerta de indefinido**, la del mes cumplido: su plantilla dice «Su periodo
  de prueba termina el {fecha}», que es una fecha absoluta y no una referencia relativa. Esa no usa
  `ReferenciaTemporal`, usa `Formato` directo. Las otras cuatro sí.
- **`DestinatariosDeAlertas` decide por el módulo del tipo**, así que F3-03 no toca destinatarios: las
  cinco son de Contratos y `TipoDeAlerta` ya lo dice.
- **El punto que hay que mirar en el próximo control** sigue siendo el contexto de empresa en segundo
  plano: `EnviarAlerta` serializa dos identificadores y no un modelo. Está probado, pero es el sitio
  donde una fuga sería más fácil.
- **Aviso para F5-03:** el ambiente de demostración nace `en_prueba` y hoy no recibe alertas. Si tiene
  que enseñar correos, esa tarea cambia una línea en
  `ProcesoDiarioDeAlertas::empresasQueRecibenAlertas()`.
- `REV-03` ya no está bloqueada y `REV-04` —la pantalla de auditoría— sigue al final del backlog, con
  F5-07 dándola por hecha. Las notas de arquitectura de REV-01/REV-02 siguen vigentes.
