# Contexto completo de Nexo

Para responder las preguntas de Angela sobre el sistema: en qué estado está, qué cumple, qué regla sigue y por
qué. Actualizado el 11 de noviembre de 2026. Si una pregunta no se responde con este archivo ni con los demás del
paquete, **dilo así** y anótala como pregunta para David; no la contestes de memoria ni por analogía con otros
sistemas.

## 1. Qué es

Nexo es un SaaS multiempresa de gestión humana para pymes colombianas (microempresas, pequeñas y medianas, hasta
unos 200 empleados). Centraliza la información del personal, calcula vacaciones y periodos de prueba, y **avisa
por correo antes de que se venza un plazo legal**. Esa última es la promesa que se vende: si las alertas fallan,
el producto no sirve.

Lo construyeron David (desarrollo, infraestructura) y Angela (experta en gestión humana; ella definió y validó
las reglas laborales). Se vende como servicio con acompañamiento: el proveedor hace la puesta en marcha y la carga
inicial; el cliente usa el sistema día a día.

Técnicamente: Laravel 12 (PHP) con Vue 3 por Inertia, base de datos PostgreSQL, desplegado en Railway.

## 2. En qué estado está hoy

| Frente | Estado |
|---|---|
| Código | **Terminado** para la primera versión. Todos los módulos de la especificación están construidos y probados: 1487 pruebas automáticas del servidor y 450 de la interfaz, todas en verde. |
| Despliegue | **En línea desde el 10 de noviembre de 2026** en Railway, con una dirección provisional de Railway. Falta el dominio propio. |
| Correo | Funciona con el remitente de prueba de Resend. **Solo entrega al correo dueño de la cuenta de Resend** y solo a la lista de correos permitidos. Falta verificar un dominio propio para enviarle a cualquiera. |
| Cuentas | Solo existe la cuenta del proveedor. La empresa la crea Angela en esta simulación. |
| Documentos legales | **En borrador.** El contrato de servicio, el acuerdo de tratamiento de datos, la política de tratamiento y la autorización para empleados los tiene que redactar el abogado. El sistema ya sabe pedir la aceptación, guardarla y versionarla. |
| Empresa (la sociedad) | Sin constituir todavía: SAS, RUT, facturación electrónica y contador están pendientes. |
| Ventas | Ningún cliente aún. El primero se plantea como piloto. |

### Lo que falta para vender, en orden

1. Contratar al abogado (cuatro textos y el acuerdo entre socios).
2. Resolver con el asesor laboral o con Angela las preguntas abiertas (sección 6).
3. Constituir la SAS.
4. RUT, IVA, régimen tributario y cuenta bancaria.
5. Contador y facturación electrónica.
6. Comprar el dominio.
7. ~~Desplegar en Railway~~ — hecho; falta conectar el dominio.
8. El correo: verificar el dominio en Resend (SPF, DKIM, DMARC), cambiar el remitente, definir el correo de
   soporte real (hoy hay uno de relleno, `soporte@nexo.invalid`) y, al vender, vaciar la lista de correos
   permitidos.
9. Sentry (errores) y un monitor de disponibilidad externo.
10. Las cuentas del equipo, con segundo factor, y una revisión de seguridad final del despliegue.
11. Un simulacro de restauración de la base de datos.
12. Pegar los textos del abogado y desplegar.
13. Revisar la demostración con ojos de cliente.
14. El primer cliente como piloto, midiendo las horas reales de implementación y soporte.
15. Registrar la marca Nexo ante la SIC.

Cada año: cargar los festivos del año siguiente (hoy están 2025 a 2028).

## 3. Las cinco reglas que el sistema nunca rompe

1. **Aislamiento entre empresas.** Ningún usuario alcanza datos de otra empresa por ningún camino (pantalla,
   exportación, búsqueda, auditoría o petición directa). Hay dos capas: el filtro automático en cada consulta
   y, debajo, las políticas de seguridad por fila de PostgreSQL (RLS), que protegen aunque alguien se salte la
   primera. Hay 49 casos de prueba que intentan leer datos ajenos por todos los caminos.
2. **El saldo de vacaciones se calcula, no se guarda.** Saldo = saldo inicial + acumulación − disfrute −
   suspensiones. No hay un número que se sume y se reste a mano, así que no se puede descuadrar.
3. **Tres reglas de conteo distintas**: vacaciones en días hábiles; incapacidades en días corridos; licencias,
   ausencias y suspensiones en días corridos. Cada una tiene su propia función, para que no se confundan.
4. **Nunca se borra nada físicamente.** Eliminar es un borrado lógico y toda acción queda en la auditoría, escrita
   automáticamente. Una sola excepción, con candados: eliminar para siempre los datos de un cliente que ya se fue
   (sección 8).
5. **Persona y vinculación son cosas distintas.** Una persona puede tener varias vinculaciones con la misma
   empresa a lo largo del tiempo. El documento es único por empresa en las personas, no en los contratos. Por
   eso un reingreso es una vinculación nueva y la anterior queda intacta.

## 4. Las reglas laborales y su fundamento

| Regla del sistema | Valor | Fundamento |
|---|---|---|
| Acumulación de vacaciones | 1,25 días por mes (15 días hábiles al año) | CST art. 186 |
| Vacaciones en días hábiles | No cuentan domingos ni festivos; el sábado según la empresa | CST art. 186 |
| Disfrute mínimo anual | 6 días hábiles continuos | CST art. 190 |
| Licencias, ausencias y suspensiones | Días corridos; reducen la acumulación de vacaciones si la empresa lo parametriza | CST arts. 51 y 53 |
| Incapacidad sobre vacaciones | Los días de incapacidad que caen dentro de unas vacaciones no se descuentan como vacaciones | Decidido (D.7); **la norma que lo sustenta está por confirmar** (sección 6) |
| Periodo de prueba, indefinido | 2 meses | CST art. 78 |
| Periodo de prueba, término fijo | Quinta parte de la duración inicial, máximo 2 meses | CST art. 78 |
| Periodo de prueba, obra o labor | Igual que el fijo, sobre la fecha de finalización **estimada**; sin ella, no hay | Decidido por David el 2026-11-02; es una sugerencia y el sistema lo advierte |
| Reingreso | Advierte que puede no ser válido pactar prueba; no decide; genera las alertas igual | CST art. 78 (no hay plazo legal entre contratos) |
| Preaviso de no renovación | 30 días antes del fin | CST art. 46, mod. Ley 2466 de 2025, art. 6 |
| Prórroga automática por silencio | Por un término igual al último (si son meses exactos, los mismos meses); por un año cuando el contrato inicial fue menor de un año y ya lleva 3 prórrogas o más; nunca más allá del tope. La fecha la calcula el sistema | CST art. 46 reformado |
| Tipo de prórroga | Obligatorio al renovar. **Pactada** = hubo documento firmado; quien la registra marca una declaración, que queda en la auditoría. **Automática** = operó el silencio. Las prórrogas de la carga inicial se tratan como automáticas | Decidido por Angela el 2026-11-11 (`PROD-33`) |
| Prórrogas cortas | En contratos con inicial menor de un año: con 3 prórrogas, si alguna fue automática o el contrato vino de la carga inicial, la 4.ª debe ser de mínimo un año; si las 3 fueron pactadas en un contrato creado en Nexo, cabe una 4.ª pactada corta; desde la 5.ª, siempre mínimo un año. El sistema **bloquea** | CST art. 46 (Ley 2466 de 2025) y Conceptos MinTrabajo 6157 y 26880 de 2026. El caso mixto no lo resuelve la ley: se aplica la lectura más favorable al trabajador (Const. art. 53, CST art. 21). Decidido por Angela el 2026-11-11 |
| Tope del término fijo | 4 años, contados desde el inicio del contrato o desde el 25 de junio de 2025 si es anterior. Al superarlo es indefinido por ley, sin liquidar. El sistema **bloquea** la renovación que lo supere, dice la fecha máxima y ofrece renovar hasta ahí o cambiar a indefinido. Si una prórroga que debería ser de un año no cabe, gana el tope | CST art. 46 reformado |
| Obra o labor prolongada | Si terminó la obra y la persona siguió sin acuerdo escrito, es indefinido desde el inicio. Por eso hay un listado bimestral de todas las obras activas, sin umbral | Decidido por Angela (D.2) |
| Código de diagnóstico | Se guarda; es dato sensible: solo lo ven quienes tienen el módulo de novedades y el dashboard lo muestra agregado | Decreto 019 de 2012; Decreto 1072 de 2015 y Res. 0312 de 2019 |
| Perfil sociodemográfico | Opcional por empresa; todos sus campos son opcionales | Decreto 1072 de 2015 (SG-SST) |
| Festivos | Tabla cargada año por año, verificada a mano, incluidos los que caen en sábado y el de la Virgen de Chiquinquirá (Ley 2578 de 2026) | Ley 51 de 1983 (traslado al lunes) |

Las alertas, con sus fechas y textos exactos, están en `03-motor-de-alertas.md`.

## 5. Decisiones ya tomadas (y quién las tomó)

| Tema | Decisión |
|---|---|
| Prórrogas del término fijo | Reemplaza la decisión de agosto («tres en total, sin separar tipos» y «advierte»): el tipo de prórroga sí cuenta, la regla es la de la sección 4 y **bloquea** (Angela, 2026-11-11, `PROD-33`) |
| Tope de 4 años | Bloquea, con la fecha máxima en el mensaje; si choca con la prórroga mínima de un año, gana el tope (Angela, 2026-11-11) |
| Obra o labor prolongada | Sin umbral; listado cada dos meses (Angela) |
| Cercanía al tope | Alerta 3 meses antes (Angela) |
| Obra o labor con periodo de prueba | Con fecha estimada opcional (David, 2026-11-02) |
| Obra o labor a indefinido | Se puede hacer en el sistema, como otrosí; la fecha de ingreso no cambia (David, 2026-11-03) |
| Mora del cliente | Primer aviso a los 5 días, segundo a los 10, suspensión a los 15. La suspensión **no es automática**: la ejecuta el proveedor con un motivo escrito. El panel muestra el peldaño (David) |
| Qué se conserva cuando un cliente se va | Sección 8 (David, 2026-11-03) |
| Demostración | Una cuenta de prueba propia por prospecto, con datos ficticios, por 14 días; aparte, una demostración compartida que se restaura cada noche a las 3:00 |
| Planes y precios | Sección 8 (David, 2026-11-08) |
| Infraestructura | Railway, plataforma administrada |
| Nombre | Nexo (el registro de la marca está pendiente) |
| Segundo factor | Obligatorio para la cuenta del proveedor |

## 6. Preguntas que siguen abiertas

- **Prórrogas del término fijo** (para el asesor laboral): confirmar la regla del caso mixto (pactadas y
  automáticas), que el tope de 4 años prevalece sobre la prórroga mínima de un año, y que las prórrogas
  anteriores al 25 de junio de 2025 cuentan para el límite. El sistema ya aplica lo que decidió Angela.
- **Para el abogado**: que el contrato de servicio diga que la marca «Pactada» es declaración y responsabilidad
  del cliente.
- **Para David**: si se debe aceptar una renovación que termina justo el día en que se cumplen los 4 años (hoy
  sí se acepta), y qué hacer el día que un contrato cumple los 4 años sin que nadie registre nada (hoy aparece en
  «vencidos sin registrar», aunque por ley ya es indefinido).

- **Periodo de prueba al pasar de obra o labor a indefinido con una labor completamente distinta.** La lectura
  conservadora, que es la que sigue el sistema, es que no procede. Falta la confirmación del asesor laboral.
- **La norma que sustenta que la incapacidad interrumpe las vacaciones.** El sistema ya lo aplica; falta citar la
  norma.
- **El fondo de cesantías** en la ficha del empleado: pendiente de definir con el asesor laboral o con Angela.
- **Los textos legales** (sección 2).

Si Angela responde alguna durante la simulación, anótalo en el reporte para David: una decisión nueva entra a la
lista única del proyecto.

## 7. Seguridad y datos personales

- HTTPS obligatorio; contraseñas con hash moderno; segundo factor obligatorio para el proveedor.
- La autorización se verifica en el servidor en cada petición: ocultar un menú no es lo que protege.
- Los usuarios administradores solo entran a los módulos asignados; si intentan otro, el servidor responde con
  prohibido.
- La auditoría registra quién, cuándo, qué módulo, qué acción y el valor anterior y el nuevo. Es de solo lectura.
- Los accesos al sistema quedan registrados, y hay un límite de intentos de inicio de sesión. El mensaje de error
  no dice si el correo existe.
- Ley 1581 de 2012: la aceptación de los documentos legales guarda quién, cuándo, desde dónde y la huella del
  texto exacto que aceptó; hay un manual de consultas y reclamos y la leyenda «reclamo en trámite». El Registro
  Nacional de Bases de Datos de la SIC no aplica por tamaño (activos menores de 100.000 UVT).
- El sistema **no guarda archivos** de los empleados (ni hojas de vida ni historias clínicas): es una decisión
  de diseño que elimina ese riesgo. El único archivo que guarda es el certificado de Cámara de Comercio de la
  empresa cliente.

## 8. El negocio

### Planes (más IVA del 19 %)

| Plan | Empleados | Usuarios incluidos | Mensual | Implementación, una vez |
|---|---|---|---|---|
| Micro | Hasta 10 | 1 | $250.000 | $400.000 |
| Pequeña | Hasta 50 | 2 | $450.000 | $800.000 |
| Mediana | Hasta 200 | 3 | $750.000 | $1.500.000 |
| Usuario adicional | — | +1 | $40.000 | — |

Se cobra por transferencia o PSE con factura electrónica; no hay pasarela de cobro recurrente. Los planes se
cambian en el panel (Suscripciones) sin desplegar.

### Cuando un cliente se va

1. Se le entrega toda su información: un ZIP con un CSV por tabla, desde la ficha de la empresa en el panel.
2. Periodo de gracia de 30 días.
3. Se eliminan sus datos para siempre con un comando que solo procede si el servicio está suspendido, la
   información ya se entregó y pasó la gracia, y que pide escribir el NIT para confirmar. Es la única excepción a
   la regla de nunca borrar.
4. Se le envía la constancia de eliminación.

El proveedor conserva: la empresa, sus suscripciones y pagos (sin plazo), la aceptación de los documentos
legales con nombre y correo de quien aceptó, el registro de alertas **sin datos personales**, y las constancias de
entrega y eliminación. Las copias de seguridad se limpian solas al cumplir su retención. Nunca se retiene la
información como presión por impago: ante el impago se suspende el servicio.

## 9. Cómo funciona por dentro

| Pieza | Qué hace |
|---|---|
| Servicio `web` | La aplicación. Al arrancar revisa su configuración, aplica las migraciones y carga catálogos, festivos y planes |
| Servicio `worker` | Envía los correos de la cola, con 3 intentos |
| Servicio `scheduler` | Corre las tareas programadas |
| Postgres | La base de datos, con un usuario de aplicación sin privilegios para que RLS lo alcance |
| Tareas programadas (hora de Bogotá) | 6:00 el proceso de alertas · 3:00 la restauración de la demostración · el día 1 a las 00:10 la foto mensual del negocio |
| Correo | Resend. Hoy en modo de prueba (sección 2) |

## 10. Lo que el sistema NO hace (a propósito)

Contratos por prestación de servicios · guardar documentos o expediente digital · reportes centralizados (cada
módulo exporta lo suyo) · llamados de atención (fase dos) · preguntas frecuentes para empleados · asistente de IA
· sábado laboral por empleado (es por empresa) · listas de ciudades fuera de Colombia (se escriben a mano) ·
nómina, liquidaciones o seguridad social (no calcula pagos).

## 11. Lo que hoy es normal y no es una falla

- La dirección es provisional de Railway.
- El correo solo llega al correo dueño de la cuenta de Resend y a la lista de permitidos.
- Los documentos legales dicen que son borradores.
- El soporte muestra `soporte@nexo.invalid`.
- No hay Sentry: los errores solo quedan en los registros de Railway.
- La alerta de cercanía al tope de 4 años no puede salir antes de 2029.
- Crear la empresa no envía la invitación; se envía desde la ficha.
