# Encargo al abogado

> Paso 11 del plan de salida al mercado (`docs/09-plan-de-salida-al-mercado.md`).
> Preparado el 2026-11-03 a partir del código del sistema, para entregárselo al abogado tal cual.
> **No es un texto legal**: es lo que el abogado necesita saber para redactar sin adivinar, y lo que
> encarece su factura si lo descubre a mitad del trabajo.

## Qué le pedimos

Cuatro documentos, que el sistema ya sabe versionar, publicar y hacer aceptar (cada uno vive en un
archivo de `resources/legal/` y se reemplaza sin tocar código):

| # | Documento | Quién lo acepta | Qué regula |
|---|---|---|---|
| 1 | Contrato de prestación del servicio (términos) | La empresa cliente, por medio de su superadministrador, al primer ingreso | La relación comercial |
| 2 | Acuerdo de tratamiento de datos | La empresa cliente, igual que el anterior | La empresa cliente como **responsable** y nosotros como **encargado** (Ley 1581 de 2012; contrato de transmisión del artículo 25 del Decreto 1377 de 2013) |
| 3 | Política de tratamiento de datos del proveedor | Nadie la acepta: es pública | Los datos de los que nosotros somos **responsables**: los usuarios administradores de cada cliente, sus contactos y los prospectos |
| 4 | Plantilla de autorización para los empleados del cliente | La firma cada empleado con su empleador, fuera del sistema | La autorización que el cliente necesita para entregarnos los datos de su gente, **con autorización expresa para datos de salud** |

Además, aparte de estos cuatro: **el acuerdo entre socios**.

## Quiénes somos y qué vendemos

- Un software como servicio para la gestión humana de PYMES colombianas. Se paga una suscripción
  mensual; no se vende ni se licencia el código. El código y la propiedad intelectual son del proveedor.
- Lo que el cliente compra es que **el sistema le avisa por correo antes de que se venza un plazo
  legal**: fin del periodo de prueba, preaviso de un término fijo, tope de prórrogas, vacaciones
  acumuladas. El sistema calcula y recuerda; **no da asesoría jurídica** ni le quita al empleador la
  responsabilidad de cumplir la ley laboral. Esa cláusula ya está aprobada por Angela (documento 6, A.2).
- Hoy el proveedor son dos personas. La sociedad se está constituyendo (paso 8 del plan).

## Qué datos trata el sistema

Por cada empleado del cliente:

| Grupo | Datos |
|---|---|
| Identificación | Tipo y número de documento, fecha y lugar de expedición, nombres y apellidos, fecha y lugar de nacimiento, género |
| Contacto | Dirección y ciudad de residencia, celular, correo |
| Contrato | Cargo, tipo de contrato, fechas de ingreso y de finalización, prórrogas, salario, bonificación, aumentos y cambios de cargo, terminación y su motivo |
| Seguridad social | EPS, fondo de pensión, ARL y nivel de riesgo, caja de compensación, banco (solo la entidad, nunca el número de cuenta) |
| Novedades | Vacaciones, licencias, ausencias, suspensiones e incapacidades, con fechas |
| **Salud (dato sensible)** | **El código de diagnóstico de cada incapacidad** |
| Perfil sociodemográfico, **opcional por empresa** | Estado civil, nivel educativo, número de hijos, personas a cargo, tipo de vivienda, estrato, medio de transporte y tiempo de desplazamiento |

Por cada usuario administrador del cliente: nombre, correo, rol, módulos a los que accede, registro de
accesos con IP y navegador, y una auditoría de todo lo que crea, edita o elimina.

**No hay datos de menores de edad como titulares en el diseño del sistema**, pero el número de hijos sí
se registra como cifra.

## Cómo se protegen

Esto es lo que el acuerdo de tratamiento puede afirmar sin exagerar, porque el código lo hace hoy:

- **Separación entre empresas en dos capas**: un filtro automático en cada consulta y, por debajo, la
  seguridad por filas de la base de datos (PostgreSQL), que impide que una empresa lea datos de otra
  aunque el código falle. Hay pruebas automáticas que intentan saltarse esa separación y fallan si lo
  logran.
- Conexión cifrada (HTTPS) en todo el sistema. Contraseñas guardadas con hashing irreversible; no
  existe forma de ver una contraseña, ni siquiera el proveedor.
- Límite de intentos de acceso, y segundo factor obligatorio para las cuentas del proveedor.
- **El diagnóstico solo lo ve quien tiene permiso sobre novedades**, y la auditoría lo guarda oculto.
- Permisos por módulo, verificados en el servidor en cada petición.
- Copias de seguridad de la base de datos del proveedor de infraestructura. **La restauración todavía
  no se ha probado** (paso 22 del plan): no conviene prometer un tiempo de recuperación hasta hacerlo.

## Lo que el abogado tiene que resolver

### 1. El acceso del proveedor al entorno del cliente

El proveedor puede entrar al entorno de un cliente para resolver un problema que el cliente no sabe
describir. El sistema lo hace así: **solo lectura por defecto**; escribir exige activarlo con un motivo
escrito; y cada visita queda en la auditoría **de ese cliente**, visible para él. El documento 4, 4.4,
exige que el contrato y el acuerdo de tratamiento lo mencionen expresamente. Pregunta: cómo debe quedar
redactado y si hace falta una autorización previa por cada acceso o basta la general del contrato.

### 2. Los terceros que tocan los datos (subencargados) y la transmisión internacional

Tres servicios tratan datos por nuestra cuenta, y los tres estarían fuera de Colombia:

| Servicio | Qué ve | Dónde |
|---|---|---|
| Railway (infraestructura y base de datos) | Toda la información | Por definir la región; probablemente Estados Unidos |
| Proveedor de correo transaccional (Resend, Postmark o Amazon SES) | El contenido de las alertas: nombre del empleado, cargo y fechas | Estados Unidos |
| Sentry (registro de errores) | La traza de un error. Está configurado para **no** enviar datos personales por defecto | Estados Unidos o Unión Europea |

Lo que encontramos: la Circular Externa 005 de 2017 de la SIC incluye a Estados Unidos entre los países
con nivel adecuado de protección (Ley 1581, artículo 26). Preguntas: si esto es transmisión a
subencargados y qué debe decir el acuerdo para cubrirla; si el cliente tiene que autorizarla
expresamente; y si conviene elegir una región concreta de Railway por razones legales.

### 3. Qué conserva el proveedor cuando un cliente se va

**Ya está decidido por David y construido** (`PROD-15`): se suprime toda la información de los empleados
y se conservan las suscripciones y los pagos sin plazo, la aceptación de los documentos legales con el
nombre y el correo de quien aceptó, el registro de alertas sin nombres ni destinatarios, y las constancias
de entrega —con la huella SHA-256 del archivo entregado— y de eliminación. El documento 6, A.4, prometía
«no conservar nada», que era demasiado absoluto. Pregunta: si está de acuerdo, y cómo debe quedar escrito
en el contrato y en el acuerdo de tratamiento.

Datos del procedimiento de salida que ya existe: exportación completa en un ZIP con un archivo por
tabla; **30 días de gracia** antes de eliminar; eliminación definitiva con constancia por tabla; las
copias de seguridad la eliminan solas al cumplirse su periodo de retención.

### 4. El derecho de supresión frente a la conservación laboral

El sistema **nunca borra físicamente** mientras el cliente está activo: todo se marca como eliminado y
queda en la auditoría. Si un empleado pide la supresión de sus datos, el empleador suele tener el deber
de conservarlos (prescripción laboral de tres años, artículo 488 del CST; SG-SST veinte años, Decreto
1072 de 2015). Pregunta: cómo debe quedar en la política y en la plantilla de autorización, y si el
borrado lógico basta mientras dure ese deber.

### 5. El plazo para notificar un incidente de seguridad

El acuerdo de tratamiento tiene que fijarlo (documento 4, 14.2). Propuesta nuestra: **notificar al
cliente dentro de las 48 horas siguientes a detectarlo**, con lo que se sepa en ese momento, y apoyarlo
en sus deberes ante la SIC sin asumirlos. Pregunta: si ese plazo es razonable y qué debe contener la
notificación.

### 6. La responsabilidad por los cálculos

Los avisos y los saldos salen de la información que el cliente digita. Tres casos que el contrato debe
cubrir de forma explícita:

- **Información digitada a mano** (documento 6, A.2): es responsabilidad del cliente verificarla.
- **El periodo de prueba de la obra o labor** se calcula sobre una **fecha de finalización estimada** que
  pone el cliente. El sistema lo advierte en pantalla, en la ficha y en cada correo: es una sugerencia, y
  si la obra termina antes, ese periodo puede no ser válido.
- **El calendario de festivos** lo mantiene el proveedor año por año.

Pregunta: el límite de responsabilidad propuesto es un múltiplo de lo pagado en los últimos doce meses,
con excepciones para dolo, culpa grave y violación de confidencialidad. ¿Qué múltiplo recomienda?

## Datos que el contrato tiene que copiar del sistema

El sistema ya aplica estos números. El contrato tiene que decir **exactamente lo mismo**, o el sistema le
avisará al cliente una cosa y el contrato dirá otra:

| Concepto | Valor en el sistema | Dónde vive |
|---|---|---|
| Aviso de cobro próximo | 5 días antes de la fecha de cobro | `config/panel.php`, `cobro.dias_por_vencer` |
| Primer aviso de mora | 5 días de atraso | `cobro.escalera_de_mora.primer_aviso` |
| Segundo aviso de mora | 10 días de atraso | `cobro.escalera_de_mora.segundo_aviso` |
| Suspensión del servicio | 15 días de atraso | `cobro.escalera_de_mora.suspension` |
| Periodo de gracia tras la salida | 30 días desde la entrega de la información | `config/panel.php`, `salida.dias_de_gracia` |
| Vigencia de la invitación de un usuario | 7 días | `App\Autenticacion\Invitaciones` |
| Acceso de prueba sugerido | 14 días | `config/panel.php`, `demostracion` |

Dos reglas que también tienen que quedar escritas:

- **La suspensión no es automática**: la decide el proveedor, con un motivo escrito.
- Ante el impago se suspende el servicio; **nunca se retienen los datos** del cliente (documento 6, A.4).

## El SLA

Lo eligen David y Angela antes de que el abogado redacte. Las opciones están en
`docs/salida/opciones-de-sla.md`, con la recomendación.

## Cómo se entregan los textos

En Markdown o en Word, uno por documento. Se pegan en `resources/legal/`, se marcan como vigentes y se
sube la versión. Desde ese momento el sistema bloquea a cada empresa hasta que su superadministrador los
acepte, y guarda quién, qué versión, cuándo y desde qué IP. **Cada cambio posterior del texto obliga a
subir la versión**, y entonces todos los clientes tienen que volver a aceptar: conviene que el abogado lo
sepa para agrupar los cambios.
