# Plan de la simulación, jornada por jornada

**Jornada 1** es el día en que se crea la empresa y se carga la información. **Jornada 2** es el primer día hábil
siguiente, la primera mañana con alertas. Cada jornada es un día hábil (lunes a viernes, no festivo). Las
fechas concretas salen de `calendario_esperado.md`; los empleados se nombran por su clave (E1 a E16) y su número
de documento está en la tabla del calendario.

Cada tarea dice **qué hacer** y **qué se espera**. Si lo que se ve no coincide con lo esperado, va al reporte.

---

## Jornada 1 · Crear la empresa y cargarla

### 1.1 Entrar como proveedor
- Entra a la dirección del sistema con la cuenta del proveedor que te dio David.
- **Se espera:** la primera vez pide activar el segundo factor con un código QR. Escanéalo con la app del celular
  y escribe el código de 6 dígitos. Luego llegas al panel del proveedor.

### 1.2 Crear la empresa (Panel → Empresas → Crear empresa)
| Campo | Valor |
|---|---|
| Razón social | Comercializadora Andina de Prueba S.A.S. |
| NIT | 901555123-4 |
| Teléfono | 6044480000 |
| Ciudad | Medellín |
| Certificado de Cámara de Comercio | Déjalo vacío |
| ¿El sábado es día laboral? | No |
| ¿Las licencias, ausencias y suspensiones afectan la acumulación de vacaciones? | Sí |
| ¿Se habilita el perfil sociodemográfico? | No |
| Plan | Pequeña — hasta 50 empleados |
| Empleados incluidos · Usuarios administradores | 50 · 5 |
| Fecha de inicio | Hoy |
| Nombre y correo del superadministrador | Tu nombre y **tu correo** |

- **Se espera:** un resumen de la configuración que dice que falta el certificado. **No llega ningún correo**:
  crear la empresa no envía la invitación.

### 1.3 Probar el archivo con errores (Panel → Puesta en marcha)
- Empresa: la recién creada. Plantilla: Empleados actuales. Archivo: `1 - Empleados actuales (CON ERRORES).xlsx`.
  Pulsa **Simular**.
- **Se espera:** un informe de errores fila por fila con exactamente las filas de la tabla «Errores que debe
  reportar el archivo CON ERRORES» del calendario (la fila 14 trae dos mensajes). No permite cargar y **no se
  escribe nada**: la empresa sigue con cero empleados.
- Angela revisa si cada mensaje lo entendería un cliente sin ayuda. Lo que no se entienda va como mejora sugerida.

### 1.4 Cargar el archivo correcto
- Mismo lugar, archivo `1 - Empleados actuales (CORRECTA).xlsx`. **Simular** → **Se espera:** sin errores, 16
  filas correctas. **Cargar** → se cargaron 16 filas.
- Después, en este orden, simula y carga: `3 - Historico de incapacidades.xlsx` (2 filas),
  `4 - Historico de vacaciones.xlsx` (2 filas), `6 - Historico de aumentos y promociones.xlsx` (2 filas).
- **Se espera:** las cuatro cargas aparecen en el historial de cargas con fecha, filas y quién.

### 1.5 Revisar la ficha de la empresa (Panel → Empresas → la empresa)
- **Se espera:** 16 empleados activos.

### 1.6 Enviar la invitación (ficha de la empresa → Usuarios → Enviar invitación)
- **Se espera:** en pocos minutos llega a tu correo la invitación. Ábrela, define tu contraseña.
- Al entrar: pide **aceptar los documentos legales**. Acéptalos.
- **Se espera:** llegas al Inicio de la empresa con el dashboard lleno.

### 1.7 Revisar lo cargado, como cliente
| Revisión | Se espera |
|---|---|
| Empleados: 16 activos | Filtro por tipo de contrato: 8 indefinido, 6 término fijo, 2 obra o labor |
| Ficha de E9 (obra o labor con fecha estimada) | Muestra el periodo de prueba sugerido y la advertencia de que la fecha es una estimación de la empresa |
| Ficha de E10 (obra o labor sin fecha estimada) | Sin periodo de prueba |
| Contratos → E8 → historial de renovaciones | El contrato inicial y tres prórrogas, todas menores de un año; la fecha límite de preaviso visible en la ficha |
| Vacaciones → saldo de E14 | 14,95 días (más lo que haya acumulado desde hoy, casi nada) |
| Vacaciones → saldo de E16 | 22,50 días. Sus vacaciones del histórico (diciembre de 2025) no restan: son anteriores a la carga |
| Incapacidades | 2 registros del histórico: E16 (5 días, común) y E14 (15 días, laboral) |
| Movimientos | 2 registros: aumento de E16 y promoción de E14 |
| Dashboard → contratos por vencer | E6, E7 y E8 vencen en unas cinco semanas: tienen que contar en la cifra de 60 días (y en la de 90). Claude calcula en cuál de las tres cifras cae cada uno con las fechas del calendario |
| Auditoría | Registros de lo que se creó en la carga |

**Hoy no llega ninguna alerta**: el proceso corre mañana a las 6:00.

---

## Jornada 2 · Las primeras alertas

### Al empezar
- Revisa el correo. **Se espera:** exactamente las alertas de la Jornada 2 del calendario (con el escenario de
  ejemplo: «completó un mes» de E1, y «Definir renovación» de E6 y de E7).

### Tareas
| # | Qué hacer | Valores | Se espera |
|---|---|---|---|
| 2.1 | **Renovar el contrato de E7** (Contratos → E7 → Renovar contrato). Es la respuesta a su alerta de plazo. | Tipo: **Pactada** · Nueva fecha de fin: un año después de la fecha de fin actual · Marcar la declaración | Sin marcar la declaración no deja registrar. Al registrarla queda en el historial como prórroga pactada, y en Auditoría con tu usuario. **El efecto del silencio de E7 ya no debe llegar**. El de E6 sí. |
| 2.2 | **Terminar a E12** (Terminaciones → Nuevo) | Fecha de salida: hoy · Tipo: Terminación en periodo de prueba · Motivo: Desempeño insuficiente durante la inducción · Observaciones: «Prueba de la simulación» | E12 pasa a inactivo. **Ninguna alerta de E12 debe llegar desde hoy** (tenía «completó un mes» más adelante). |
| 2.3 | Probar un documento que no existe (Incapacidades → Nuevo) | Documento: 1234567890 | «Empleado no encontrado» y no deja continuar |
| 2.4 | Probar novedad sobre un inactivo (Incapacidades → Nuevo) | Documento de E12 | No lo deja registrar |
| 2.5 | **Incapacidad de E3** (Incapacidades → Nuevo) | Inicio: ayer · Terminación: mañana · Código: J11.1 · Prórroga: No · EPS: Sura EPS · Origen: Común | Días: **3** (corridos) |
| 2.6 | **Licencia no remunerada de E16** (Licencias → Nuevo) | Tipo: Licencia no remunerada · Del próximo viernes al lunes siguiente | Días: **4** (corridos: el sábado y el domingo cuentan; si el lunes es festivo, también) |

---

## Jornada 3

- Revisa las alertas del día contra el calendario. **Ajuste:** el efecto del silencio de E7 **no** debe llegar
  (se renovó en la Jornada 2). El de E6 sí.

| # | Qué hacer | Valores | Se espera |
|---|---|---|---|
| 3.1 | **Vacaciones de E14** | Del primer lunes de diciembre que tenga un festivo en la semana, o cualquier semana completa de lunes a viernes futura | Días = días hábiles sin domingos ni festivos (ej.: 7 al 11 de diciembre de 2026 = 4 días). Sin advertencia de saldo |
| 3.2 | **Vacaciones de E1 con saldo insuficiente** | 10 días hábiles empezando el próximo lunes | Advertencia con números exactos: «Al [fecha] esta persona tendrá X días disponibles. Está registrando 10 días: faltan Y días.» **Deja guardar** |
| 3.3 | **Eliminar** esas vacaciones de E1 | — | Se elimina, el saldo vuelve al anterior, y en Auditoría aparece la eliminación con el detalle |
| 3.4 | **Aumento salarial de E16** (Movimientos) | Nuevo salario: 10.300.000 · Cargo: el mismo · Efectividad: hoy | El salario de E16 cambia; en su histórico se ve anterior y nuevo, junto al aumento cargado del histórico |
| 3.5 | **Promoción de E2** | Nuevo cargo: Coordinador de compras · Nuevo salario: 3.900.000 · Efectividad: hoy | Cambian cargo y salario de E2. Las alertas siguientes de E2 dicen el **cargo nuevo** |

---

## Jornada 4

| # | Qué hacer | Valores | Se espera |
|---|---|---|---|
| 4.1 | **Cambiar E10 a término indefinido** (Contratos → E10) | Fecha del cambio: hoy | Queda registrado el cambio con su fecha; la fecha de ingreso de E10 **no cambia** (4 de marzo de 2024). E10 sale del resumen de obra o labor del cierre de mes |
| 4.2 | **Reingreso de E12** (Empleados → Nuevo) | Mismo documento de E12, mismos datos personales · Cargo: Auxiliar de despachos · Término indefinido · Ingreso: hoy · Salario 1.750.905 | Aparece la **advertencia de reingreso con énfasis** (salió hace menos de 30 días). Al guardar, es una vinculación nueva, y la anterior sigue inactiva. Le aplican las tres alertas del indefinido desde hoy: calcula sus fechas y súmalas al calendario |
| 4.3 | **Probar el tope de 4 años con E6** (Contratos → E6 → Renovar) | Tipo: Pactada · Nueva fecha de fin: 31 de diciembre de 2030 | **Bloquea** con el mensaje del tope, que dice la **fecha máxima** (unos 4 años después del ingreso de E6) y ofrece renovar hasta ahí o «Cambiar a término indefinido». No guardes |
| 4.4 | **Probar la regla de prórrogas cortas con E8** (Renovar). E8 vino de la carga con 3 prórrogas | Tipo: Pactada · Nueva fecha de fin: 6 meses después de la actual · Marcar la declaración | **Bloquea** con el mensaje E: «Este contrato se registró en la carga inicial y no es posible verificar cómo se hicieron sus prórrogas anteriores a Nexo…», con la fecha mínima (un año después del inicio de la nueva prórroga). No guardes |
| 4.5 | **La automática de E8** (mismo modal) | Tipo: Automática | No pide fecha: muestra la que pone el sistema, **un año** después del vencimiento actual, con el aviso de que ya lleva 3 prórrogas. **Cancela sin guardar**: si se guarda, E8 ya no recibe su efecto del silencio |
| 4.6 | **Historial de E8** (Contratos → E8) | — | Sus 3 prórrogas cargadas aparecen como **automáticas** |
| 4.7 | **Exportar** Empleados y Vacaciones a Excel | — | Cada archivo trae documento, nombre completo y cargo |

---

## Jornadas 5 en adelante

- Cada mañana: revisar las alertas contra el calendario ajustado. Fijarse especialmente en:
  - La alerta que el calendario marca «adelantada desde el sábado» (E11): debe llegar el viernes y decir
    «en dos días ([fecha])», no «mañana».
  - E8: el día de su plazo de renovación llegan **dos** correos (plazo y «última renovación corta»), y dos días
    después el efecto del silencio, que debe decir que se prorroga **por un año, hasta el [fecha]** (ya lleva 3
    prórrogas y vino de la carga).
  - E9: los dos avisos de periodo de prueba de obra o labor, con la nota de la fecha estimada.
  - E12: nada, aunque el calendario original tenía alertas suyas.
- Un día sin alertas en el calendario es un día sin correo. Que llegue algo ese día es una falla.
- Una vez por semana: Panel → **Alertas enviadas** y **Salud del sistema** (captura de pantalla para Claude).
- Libre: registra lo que harías en una empresa de verdad (una incapacidad laboral, una ausencia, una renuncia con
  su motivo) y pídele a Claude que diga antes qué debe pasar.

## El cierre de mes (último día hábil del mes)

Llegan los resúmenes del calendario. Con el escenario de ejemplo, y con los ajustes de las jornadas 2 a 4:

| Resumen | Se espera |
|---|---|
| Contratos vencidos sin registrar | E13 (venció hace unos 20 días más los que hayan pasado) |
| Vacaciones acumuladas | E14, con un poco más de 15 días. Si en la Jornada 3 le registraste vacaciones que **empiezan** en este mes, puede no aparecer: Claude lo calcula |
| Vacaciones pendientes | Las personas del calendario que cumplen un año el mes siguiente (E15, y según la fecha de inicio también E6 y E7) |
| Contratos de obra o labor activos | Solo en meses impares. **Solo E9**, porque E10 se cambió a indefinido en la Jornada 4 |

Después del cierre, si se quiere: registrar la terminación de E13 con tipo «Expiración del plazo fijo pactado» y
fecha de salida igual a su fecha de fin. **Se espera** que el motivo se autocomplete y que E13 no vuelva a salir en
el resumen de vencidos.

## El final

Al terminar, Claude prepara el reporte final para David con todo lo probado, lo que funcionó, lo que falló y las
mejoras sugeridas.
