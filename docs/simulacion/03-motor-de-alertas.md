# El motor de alertas

Es la parte más importante de la simulación. Aquí está todo lo que hace, tal como está programado.

## Cuándo corre y a quién le escribe

- Un proceso corre **todos los días a las 6:00 a. m., hora de Bogotá**, en el servicio `scheduler` de Railway.
  Evalúa cada empresa activa, una por una, y deja cada correo en una cola. El servicio `worker` los envía
  enseguida (con hasta 3 intentos, separados 60 segundos). En condiciones normales el correo está en la bandeja
  antes de las 6:10.
- **Solo envía en días hábiles** (lunes a viernes que no son festivos en Colombia; el sábado nunca es hábil para
  las alertas, aunque la empresa trabaje sábados). Si la fecha de una alerta cae en sábado, domingo o festivo,
  **sale el día hábil inmediatamente anterior**, y el texto lo dice: «en dos días (22 de noviembre)» en lugar de
  «mañana». Un viernes antes de un lunes festivo evalúa viernes, sábado, domingo y lunes.
- Los domingos y festivos el proceso corre pero no envía nada. Es correcto.
- Destinatarios: los usuarios de la empresa con acceso al módulo de la alerta. El superadministrador los tiene
  todos. Las de vacaciones son del módulo Vacaciones; todas las demás, del módulo Contratos.
- Solo para empleados **activos**. Al registrar la terminación, no sale ninguna alerta más de esa persona.
- **Idempotencia**: una misma alerta no se envía dos veces, aunque el proceso corra dos veces.
- Toda referencia de tiempo lleva la fecha exacta entre paréntesis: «Mañana (14 de junio) es el último día…».
- Un mes se cumple el día anterior al mismo número del mes siguiente: quien entra el 15 de abril cumple un
  mes el 14 de mayo y dos meses el 14 de junio.
- Mientras el sistema está en pruebas, los correos solo salen a la lista de correos permitidos. Si una alerta
  va a alguien fuera de la lista, se descarta y queda una línea en el registro: no es falla.

## Las alertas individuales (un correo por empleado)

### Periodo de prueba — término indefinido (dura dos meses)

| Alerta | Sale el | Asunto | Cuerpo |
|---|---|---|---|
| Completó un mes | Día en que cumple un mes | «Periodo de prueba: [nombre] completó un mes» | «[nombre], [cargo], ha completado un mes de periodo de prueba. Su periodo de prueba termina el [fecha].» |
| Faltan 15 días | 15 días antes del último día del periodo | «Periodo de prueba de [nombre]: termina en 15 días ([fecha])» | «[nombre], [cargo], completará en 15 días ([fecha]) su periodo de prueba.» |
| Víspera | El día anterior al último | «Mañana ([fecha]) vence el periodo de prueba de [nombre]» | «Mañana ([fecha]) es el último día del periodo de prueba de [nombre], [cargo].» |

Ejemplo: ingresa el 15 de abril → último día del periodo, 14 de junio → alertas el 14 de mayo, el 30 de mayo y el
13 de junio. Si alguna cae en día no hábil, se adelanta y el número cambia («en 16 días», «en dos días»).

### Periodo de prueba — término fijo, y obra o labor con fecha estimada

El periodo es la quinta parte de la duración pactada (días corridos de ingreso a fin, truncando), sin pasar de
dos meses. En obra o labor se calcula sobre la fecha de finalización **estimada**; sin esa fecha no hay periodo de
prueba ni alertas de prueba.

| Alerta | Sale el | Asunto | Cuerpo |
|---|---|---|---|
| Faltan dos días | 2 días antes del último día | «Periodo de prueba de [nombre]: termina en dos días ([fecha])» | «El periodo de prueba de [nombre], [cargo], termina en dos días ([fecha]).» |
| Víspera | El día anterior al último | «Mañana ([fecha]) vence el periodo de prueba de [nombre]» | «Mañana ([fecha]) es el último día del periodo de prueba de [nombre], [cargo].» |

En obra o labor el cuerpo termina con: «Este periodo de prueba es una sugerencia calculada sobre la fecha
estimada de finalización que registró la empresa. Verifique que esa fecha corresponda a la duración real de la
obra.»

### Vencimiento y prórrogas del término fijo

El «último día para avisar» es **30 días antes de la fecha de fin** del contrato vigente (la de la última
prórroga, si la hay).

| Alerta | Sale el | Asunto | Cuerpo |
|---|---|---|---|
| Plazo para definir la renovación | 2 días antes del último día para avisar (32 días antes del fin) | «Definir renovación del contrato de [nombre]» | «El contrato de [nombre], [cargo], termina el [fecha de fin]. En dos días ([fecha]) vence el plazo legal de 30 días para avisarle si el contrato no se va a renovar. Si van a pactar la prórroga, el documento debe firmarse antes del [fecha de fin].» |
| Efecto del silencio | El último día para avisar (30 días antes del fin) | «Último día para avisar la no renovación de [nombre]» | «Hoy ([fecha]) es el último día para notificar a [nombre] que su contrato no se renovará.» y después **la duración real de la prórroga automática**: «…queda prorrogado automáticamente por un término igual al último pactado, hasta el [fecha].», o «…por un año, hasta el [fecha].» cuando la ley la pone en un año (contrato inicial menor de un año que ya lleva 3 prórrogas o más), o la variante del tope (abajo). |
| Última renovación corta | El mismo día que el plazo de renovación, si el contrato inicial es menor de un año, la prórroga vigente es menor de un año y **la siguiente ya no puede ser corta**: después de la 3.ª prórroga si alguna fue automática o si el contrato vino de la carga inicial; después de la 4.ª si todas fueron pactadas y el contrato se creó en Nexo | «La próxima renovación de [nombre] debe ser de un año» | «El contrato de [nombre] acumula N prórrogas inferiores a un año. La próxima renovación deberá ser de mínimo un año.» |
| Cercanía al tope legal | 3 meses antes de cumplir 4 años a término fijo | «El contrato de [nombre] se acerca al tope de cuatro años» | Explica cuándo se cumple el tope. **No se puede probar en esta simulación**: los cuatro años se cuentan desde el 25 de junio de 2025 como mínimo, así que la primera saldría en 2029. |

**Si se registra una renovación antes del último día para avisar, el efecto del silencio de ese contrato NO
sale**, porque la fecha de fin cambia y el plazo se corre. Lo mismo si se registra la terminación.

### Las reglas al renovar (desde el 13 de noviembre de 2026, `PROD-33`)

- **Tipo de prórroga obligatorio.** **Pactada**: se digita la fecha y hay que marcar la declaración «Declaro que
  esta prórroga consta en un documento firmado por la empresa y el trabajador antes del vencimiento del
  contrato.»; sin marcarla no deja registrar. **Automática**: no se digita la fecha, **la pone el sistema**:
  por el mismo término del tramo vigente (si el tramo es de meses exactos, los mismos meses: 1 ene–31 mar se
  renueva al 30 jun), de un año cuando la ley lo exige, y nunca más allá del tope de 4 años.
- **Prórrogas cortas** (solo si el contrato inicial es menor de un año; corta = menor de un año). Se cuentan
  todas las prórrogas, incluidas las de la carga:

| Al registrar una prórroga corta, el contrato… | Resultado |
|---|---|
| lleva 0, 1 o 2 prórrogas | Se registra |
| lleva 3 y al menos una fue automática | **Bloquea** (mensaje A: «…al menos una de ellas fue automática… debe ser de mínimo un año, con fecha de fin igual o posterior al [fecha]») |
| lleva 3 y vino de la carga inicial | **Bloquea** (mensaje E: «Este contrato se registró en la carga inicial…») |
| lleva 3, todas pactadas, creado en Nexo, y la nueva es pactada | Se registra (es la 4.ª corta), con aviso de que la siguiente debe ser de un año |
| lleva 3, todas pactadas, y la nueva es automática | No se digita: el sistema la pone a un año (mensaje B) |
| lleva 4 o más | **Bloquea** (mensaje C: «Este contrato ya tiene 4 prórrogas. Desde la 5.ª…») |

- **Tope de 4 años**: bloquea con el mensaje que dice la fecha máxima y ofrece renovar hasta esa fecha o
  cambiar a indefinido. Si una prórroga que debería ser de un año no cabe antes del tope, **gana el tope**: se
  puede renovar justo hasta la fecha máxima.
- **Contratos de la carga inicial**: todas sus prórrogas cargadas se tratan como **automáticas**. Al Simular,
  la carga rechaza una prórroga menor de un año desde la 4.ª (si el contrato inicial fue menor de un año) y una
  fecha de finalización posterior al tope de 4 años.

Cuando el efecto del silencio coincide con el tope de cuatro años, el texto cambia («el contrato ya no puede
prorrogarse…» o «queda prorrogado solo hasta el [fecha]…»). Tampoco ocurre en esta simulación.

## Los resúmenes (un correo por empresa)

Salen el **último día hábil del mes**. Si un resumen no tiene a nadie, no se envía.

| Resumen | Asunto | Quién aparece |
|---|---|---|
| Contratos vencidos sin registrar | «Contratos vencidos sin registrar: N» | Contratos a término fijo cuya fecha de fin ya pasó sin prórroga ni terminación. Cada línea: «[nombre], [cargo] — venció el [fecha], hace N días.» |
| Vacaciones acumuladas | «Vacaciones acumuladas: 1 persona completó 15 días» (o «N personas completaron») | Quienes cruzan los 15 días de saldo **en ese mes** (tenían menos de 15 al cierre del mes anterior y 15 o más al cierre de este). Cada línea: «[nombre], [cargo] — X,XX días acumulados.» |
| Vacaciones pendientes | «Vacaciones pendientes: N personas cumplen un año de servicio» | Quienes cumplen un año (o 2, 3…) de servicio **el mes siguiente** sin haber tomado en ese año un periodo de vacaciones de al menos 6 días hábiles seguidos. Cada línea: «[nombre], [cargo] — cumple un año el [fecha].» |
| Contratos de obra o labor activos | «Contratos de obra o labor activos: N» | **Solo en meses impares** (enero, marzo, mayo, julio, septiembre, noviembre). Todos los de obra o labor activos, con su ingreso y cuánto llevan. |

## Lo que se ve en pantalla, no por correo

- **Saldo de vacaciones insuficiente**, al registrar vacaciones (no bloquea).
- **Empleado no encontrado**, al registrar una novedad con un documento que no es de un empleado activo.
- **Advertencia de reingreso**, al crear un empleado que ya tuvo una vinculación.
- **Validaciones de la renovación**: tope de cuatro años (bloquea) y prórrogas cortas (advierte).

## Cómo ajustar el calendario cuando Angela registra algo

| Lo que hace Angela | Efecto en las alertas |
|---|---|
| Registra la terminación de alguien | Desde ese momento no sale nada más de esa persona. Si era término fijo vencido, sale del resumen de vencidos. |
| Renueva un término fijo | Cambia la fecha de fin: el efecto del silencio del tramo anterior ya no sale, y las alertas de plazo y silencio se recalculan sobre el nuevo fin. En una automática, la nueva fecha es la que mostró el sistema. |
| Cambia una obra o labor a indefinido | Deja de aparecer en el resumen de obra o labor. Sus alertas de periodo de prueba no cambian: dependen del contrato con que ingresó, no del actual. |
| Cambia un término fijo a indefinido | Dejan de salir las alertas de vencimiento y prórroga de ese contrato. |
| Registra vacaciones de 6 días hábiles o más dentro del año de alguien | Sale del resumen de vacaciones pendientes. |
| Registra vacaciones o una licencia que lo bajan de 15 días | Puede dejar de aparecer en el resumen de acumulación. |
| Crea un empleado nuevo | Le aplican las alertas de su tipo de contrato desde su fecha de ingreso. |
| Elimina una novedad | El saldo se recalcula; queda en Auditoría. |
