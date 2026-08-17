# El Lado del Proveedor — Sistema de Gestión de Talento Humano

> Panel de administración y operación del negocio
> Documento de referencia del proyecto. Generado desde la fuente original; no editar a mano.

**DOCUMENTO INTERNO**
**El Lado del Proveedor**
**Panel de administración y operación del negocio**
*Lo que ustedes usan, no lo que usa el cliente*
*Acompaña al prototipo «Prototipo panel del proveedor.html»*
Documento 6 · Versión 1.0

## Por qué existe este documento

Los cinco documentos anteriores describen el producto que usa la empresa cliente. Ninguno describe lo que usan ustedes para operarlo, y ese vacío tiene una consecuencia concreta: hoy no existe forma de dar de alta al primer cliente sin meter los datos a mano en la base de datos.
Este documento cubre dos cosas distintas que se suelen confundir. La primera mitad es software: el panel del proveedor, con sus pantallas y su especificación, que hay que construir. La segunda mitad no es software: cómo se opera el negocio, quién hace qué y qué se responde cuando algo sale mal.

> **Los tres roles, para no confundirlos**
>
> · Owner: ustedes. Ven todas las empresas, crean cuentas, cargan datos y operan el negocio. Es el panel de este documento.
>
> · Superadministrador: la cuenta de la empresa cliente. Ve solo su empresa y tiene acceso a todo dentro de ella.
>
> · Administrador: usuario que crea el superadministrador, con acceso a los módulos que le asigne.
>
> El panel del proveedor es una aplicación aparte de la que ve el cliente. Vive en otra dirección web y ningún cliente puede llegar a ella.

## Contenido

<!-- tabla de contenido generada por el editor -->

## PARTE I — El panel del proveedor

### 1. Qué resuelve

El panel es la herramienta de trabajo diaria de ustedes. Sin él, cada operación de negocio —crear un cliente, cargar sus datos, revisar si lo está usando, cobrarle— se convierte en una intervención manual en la base de datos, que es lento, arriesgado y no deja rastro.

| Necesidad | Sin panel | Con panel |
|---|---|---|
| Dar de alta un cliente nuevo | Insertar filas a mano en la base de datos. | Un formulario de cinco minutos al final de la reunión de onboarding. |
| Saber si un cliente está usando el sistema | Consultas SQL cada vez que surge la duda. | Una columna de estado en el listado de empresas. |
| Responder "a mí nunca me llegó la alerta" | Revisar registros del servidor. | Buscar la alerta en la consola de envíos y ver a quién y cuándo se envió. |
| Cargar el calendario de festivos del año | Editar el código y desplegar. | Subir un archivo o digitar las fechas. |
| Agregar una EPS nueva al catálogo | Editar el código y desplegar. | Un formulario. |
| Suspender a un cliente que no paga | Cambiar un campo a mano. | Un botón, con constancia de quién y cuándo. |

**CRÍTICO.** El panel no está en las siete fases del Plan de Construcción. Hay que agregarlo, y no puede quedar para el final: sin la pantalla de crear empresa, no se puede probar el sistema con datos reales ni hacer la primera demostración a un cliente.

### 2. Estructura

| Sección | Para qué sirve |
|---|---|
| Inicio | El estado del negocio y lo que requiere atención hoy. |
| Empresas | Listado de clientes, creación, parametrización y ficha de cada uno. |
| Puesta en marcha | El asistente de carga inicial desde las plantillas de Excel. |
| Suscripciones | Planes, cobros, mora y suspensiones. |
| Alertas | Consola de todo lo que el sistema ha enviado. |
| Catálogos | EPS, fondos, ARL, cajas, bancos, ciudades y festivos. |
| Salud del sistema | Procesos programados, errores y copias de seguridad. |
| Demostración | Estado del ambiente de demo y su restauración. |
| Equipo | Los usuarios internos y qué puede hacer cada uno. |

### 3. Inicio — el estado del negocio

Responde dos preguntas en este orden: qué requiere atención hoy, y cómo va el negocio.

#### 3.1 Bloque de atención

| Tarjeta | Qué muestra | Por qué importa |
|---|---|---|
| Clientes en riesgo | Empresas cuyo uso cayó de forma marcada frente al mes anterior. | Una caída fuerte en la frecuencia de entrada es la señal más temprana de que un cliente se va a ir. Detectarla a tiempo permite llamar antes de la renovación, no después. |
| Clientes sin arrancar | Empresas creadas hace más de una semana con menos de cinco empleados cargados. | Un cliente que no completó la carga inicial no está usando el producto y va a cancelar. Es el momento de ofrecer ayuda. |
| Pagos vencidos | Suscripciones en mora, con días de atraso. | Antes de suspender conviene llamar. |
| Alertas fallidas | Correos que no se pudieron entregar en las últimas 24 horas. | Si las alertas no llegan, el producto no cumple su promesa y el cliente no se entera de que no se enteró. |
| Festivos sin cargar | Aviso si el año siguiente no tiene calendario cargado. | De esa tabla dependen todos los cálculos. Aparece desde octubre. |
| Procesos fallidos | Si el proceso diario de alertas no corrió o falló. | Es el trabajo más crítico del sistema y el más silencioso cuando falla. |

#### 3.2 Bloque de negocio

- Empresas activas, con la variación frente al mes anterior.
- Ingreso recurrente mensual y su variación.
- Total de empleados gestionados en todas las empresas, que es la métrica que mueve el costo de infraestructura.
- Total de usuarios administradores, que es la otra variable del precio.
- Altas y bajas de clientes del mes.
- Tasa de cancelación acumulada del año.
- Correos enviados en el mes, que es el costo operativo que más escala.

### 4. Empresas

#### 4.1 Listado

| Columna | Contenido |
|---|---|
| Empresa | Nombre y NIT. |
| Plan | Nivel contratado, empleados y usuarios incluidos. |
| Empleados | Cuántos tiene cargados frente a cuántos incluye el plan. Si los supera, se resalta: es una conversación de venta. |
| Usuarios | Cuántos administradores tiene activos. |
| Uso | Indicador de salud en tres estados: activa, con uso bajo, sin uso. |
| Último ingreso | Cuándo entró alguien de esa empresa por última vez. |
| Pago | Al día, por vencer o en mora con los días de atraso. |
| Estado | Activa, suspendida o en periodo de prueba. |

Filtros por estado, plan, salud de uso y estado de pago. Búsqueda por nombre o NIT.

#### 4.2 Crear una empresa

Es la pantalla que hoy no existe y sin la cual no hay cliente posible. Un formulario en tres pasos que se diligencia al final de la reunión de onboarding, delante del cliente.
1. Datos de la empresa: nombre, NIT, teléfono, ciudad, y adjuntar el certificado de Cámara de Comercio recibido.
2. Parámetros: sábado laboral, efecto de las suspensiones sobre vacaciones, perfil sociodemográfico habilitado o no.
3. Plan y cuenta: nivel contratado, número de empleados y usuarios incluidos, fecha de inicio, y los datos del superadministrador, a quien se le envía la invitación por correo.

> **Detalle que ahorra problemas**
>
> Al terminar, el panel debe mostrar un resumen de lo que quedó configurado y ofrecer copiarlo o imprimirlo. Es lo que se le lee al cliente en voz alta antes de cerrar la reunión, y evita la discusión posterior de "yo nunca dije que el sábado fuera laboral".

#### 4.3 Ficha de la empresa

| Pestaña | Contenido |
|---|---|
| Resumen | Datos, plan, indicadores de uso, fecha de alta y quién la creó. |
| Parámetros | Los cuatro parámetros, editables por el owner con constancia del cambio. |
| Usuarios | Los administradores de esa empresa, su último acceso y la posibilidad de reenviar una invitación o restablecer una contraseña. Nunca ver la contraseña: no existe forma de verla. |
| Uso | Empleados cargados, novedades registradas por mes, frecuencia de entrada por usuario y módulos que efectivamente usa. Es la conversación de renovación en una pantalla. |
| Suscripción | Plan, historial de pagos, facturas y estado. |
| Alertas | Las enviadas a esa empresa, con su resultado. |
| Bitácora | Notas internas del equipo sobre ese cliente: llamadas, quejas, promesas hechas. Nadie recuerda dentro de seis meses qué se le prometió a quién. |

#### 4.4 Acceso al entorno del cliente

**CRÍTICO.** Va a ser necesario entrar como el cliente para resolver un problema que él no sabe describir. Eso debe ser posible, pero con tres condiciones que no son negociables:
- Queda registrado en la auditoría de esa empresa, visible para el cliente, indicando que fue el proveedor.
- **Es de solo lectura por defecto.** Modificar datos del cliente exige activarlo de forma expresa y deja un registro aparte.
- El contrato y el acuerdo de tratamiento de datos deben mencionarlo. Un acceso silencioso a datos de empleados de un tercero es exactamente lo que el acuerdo prohíbe.

### 5. Puesta en marcha

Es el asistente de carga inicial, descrito en el Documento 3. Vive aquí, no en la aplicación del cliente. Además del asistente, esta sección guarda el historial de cargas: qué archivo, cuántas filas, quién y cuándo, con la posibilidad de volver a ver el informe de errores de una carga anterior.

### 6. Suscripciones

- Planes definidos con su precio, número de empleados y de usuarios incluidos.
- Estado de cada suscripción, próxima fecha de cobro y medio de pago.
- Historial de facturas y pagos.
- Registro de cambio de plan, con la fecha desde la que aplica.
- Suspensión y reactivación, con motivo y constancia.

> **Qué implica suspender**
>
> Suspender bloquea el acceso al sistema pero no elimina nada y no detiene los cálculos internos. Al reactivar, todo sigue donde estaba.
>
> Lo que nunca se hace es retener la información como forma de presión: eso está expresamente descartado en el acuerdo de tratamiento de datos. El cliente en mora puede pedir su información y hay que entregársela.
>
> Conviene definir la escalera: aviso a los 5 días de mora, segundo aviso a los 15, suspensión a los 30. Los números son una decisión de ustedes.

### 7. Alertas

La consola de todo lo que el sistema ha enviado. Existe por una razón muy concreta: tarde o temprano un cliente va a decir que nunca le llegó un aviso, y esa conversación se gana con evidencia o se pierde.

| Columna | Contenido |
|---|---|
| Fecha programada y fecha de envío | Permite ver si hubo corrimiento al día hábil anterior. |
| Empresa y empleado | Sobre quién era la alerta. |
| Tipo de alerta | Cuál de las once. |
| Destinatarios | A qué correos se envió. |
| Estado | Enviada, fallida, pendiente o rebotada. |
| Intentos | Cuántas veces se reintentó y el motivo del fallo. |

- Filtros por rango de fechas, empresa, tipo y estado.
- Acción de reenviar una alerta puntual.
- **Vista de entregabilidad:** porcentaje de correos entregados frente a rebotados en el mes. Una caída ahí significa que algo se rompió en la configuración del dominio, y es el tipo de fallo que nadie nota hasta que un cliente reclama.

### 8. Catálogos

**REGLA.** Estos datos cambian con el tiempo y no pueden vivir en el código. Toda la administración se hace desde el panel, sin desplegar nada.

| Catálogo | Notas |
|---|---|
| EPS, fondos de pensión, ARL, cajas de compensación, bancos | Alta, edición y desactivación. Una entidad que desaparece se desactiva, no se borra: hay empleados históricos apuntando a ella. |
| Departamentos y ciudades de Colombia | Carga inicial y actualización ocasional. |
| Festivos | Por año, con fecha y nombre. Debe incluir los que caen en sábado. El panel avisa desde octubre si el año siguiente no está cargado. |
| Constantes legales | Los números que vienen de la ley: 1,25 días por mes, dos meses de periodo de prueba, treinta días de preaviso, cuatro años de tope, seis días de disfrute mínimo. Cada uno con la norma que lo respalda y la fecha en que se verificó por última vez. |
| Umbrales configurables | Anticipación de la alerta de tope, tiempo para considerar prolongado un contrato de obra o labor, vigencia del usuario de demostración, días de mora antes de suspender. |

La pantalla de constantes legales es más importante de lo que parece: es donde se ajusta el sistema cuando cambia una ley, sin tocar código y sin esperar un despliegue.

### 9. Salud del sistema

- Estado del proceso diario de alertas: si corrió, cuándo, cuánto tardó y cuántas alertas produjo.
- Trabajos en cola pendientes y fallidos, con la posibilidad de reintentarlos.
- Errores de la aplicación de las últimas 24 horas.
- Última copia de seguridad y última restauración probada.
- Espacio ocupado por la base de datos y correos enviados en el mes, que son los dos costos que crecen.

### 10. Demostración y equipo

- Estado del ambiente de demostración, con un botón para restaurarlo de inmediato si un prospecto lo dejó desordenado.
- Generación de accesos de prueba con vigencia limitada, y listado de los que están activos.
- Usuarios internos del equipo, con dos niveles: acceso completo, y acceso de soporte sin ver datos de empleados ni facturación.
- Segundo factor de autenticación obligatorio para todo el equipo. Estas cuentas ven la información de todos los clientes a la vez.

## PARTE II — Operación del negocio

Esta parte no es software. Son los acuerdos de trabajo entre ustedes dos y lo que se le promete al cliente. Escribirlo ahora, con cero clientes, cuesta una hora; escribirlo con quince clientes ya no se hace.

### 11. Reparto de roles

| Área | Angela | David |
|---|---|---|
| Producto y reglas de negocio | Decide. Es la autoridad en todo lo laboral. | Consulta y traduce a comportamiento del sistema. |
| Desarrollo | No interviene. | Decide. |
| Venta y demostración | Lidera. Habla el idioma del cliente. | Apoya en lo técnico. |
| Onboarding y carga inicial | Lidera la reunión. | Ejecuta la carga. |
| Soporte de primer nivel | Atiende las dudas de uso. | Recibe lo que sea un fallo. |
| Legal y contable | Coordina con los asesores. | Aporta lo técnico del contrato. |
| Precio y facturación | Deciden juntos. | Deciden juntos. |

> **El acuerdo que falta**
>
> Participación en la sociedad, aportes de cada uno, propiedad del código y qué pasa si uno se retira. Es la conversación incómoda que todo el mundo aplaza y que se vuelve un problema serio justo cuando el negocio empieza a funcionar.
>
> No necesita un contrato elaborado al principio: un documento de una página firmado por ambos vale infinitamente más que un acuerdo verbal.

### 12. El proceso comercial

#### 12.1 Etapas

| Etapa | Qué pasa | Quién |
|---|---|---|
| Contacto | Un prospecto pide información. | Cualquiera |
| Demostración | Sesión guiada de 30 a 45 minutos con el ambiente de demo poblado. | Angela |
| Prueba | Se le entrega un acceso de prueba con vigencia limitada para que lo explore solo. | David genera el acceso |
| Propuesta | Plan, precio y qué incluye la puesta en marcha. | Ambos |
| Cierre | Firma del contrato y del acuerdo de tratamiento de datos. Se solicita el certificado de Cámara de Comercio. | Angela |
| Puesta en marcha | Reunión de onboarding, creación de la cuenta y carga inicial. | Ambos |
| Seguimiento | Revisión a los 15 y a los 45 días. | Angela |

#### 12.2 Guion de la reunión de onboarding

Una hora, en este orden. Conviene cronometrarla las primeras veces para saber cuánto cuesta realmente y en qué punto se estira.

| Minutos | Qué se hace |
|---|---|
| 0 a 5 | Presentación y confirmación de quién estará a cargo del sistema en la empresa. |
| 5 a 20 | Recorrido del sistema con el ambiente de demostración. Se muestran las alertas primero: es lo que compra el cliente. |
| 20 a 35 | Parámetros. Se pregunta y se decide en vivo: ¿trabajan sábados? ¿las licencias no remuneradas afectan las vacaciones? ¿quieren llevar perfil sociodemográfico? Aquí es donde la experiencia de Angela agrega el valor que un formulario de autoservicio nunca podría. |
| 35 a 45 | Se crea la cuenta en vivo, delante del cliente, y se lee el resumen de configuración en voz alta. |
| 45 a 55 | Entrega de las plantillas de Excel. Se explica qué es obligatorio y por qué el saldo de vacaciones es el dato crítico. Se acuerda fecha de devolución. |
| 55 a 60 | Cómo pedir soporte, en cuánto tiempo se responde, y agenda de la sesión de seguimiento a los 15 días. |

> **Lo que no se hace en esta reunión**
>
> No se entrega el acceso al cliente antes de la carga inicial. Un cliente que entra a un sistema vacío concluye que el producto no sirve.
>
> El acceso se entrega cuando sus datos ya están adentro, y esa primera impresión —ver a su gente en la pantalla— es la que hace que lo use.

### 13. Soporte

#### 13.1 Qué se promete

Antes de prometer nada, conviene poder cumplirlo. Con dos personas y otros trabajos, esto es realista:

| Gravedad | Qué es | Respuesta | Solución |
|---|---|---|---|
| Crítica | El sistema no está disponible o los datos se ven mal para todos. | Mismo día hábil | Lo antes posible, con avisos de avance |
| Alta | Una función central no funciona: no se pueden registrar novedades, no llegan las alertas. | Un día hábil | Tres días hábiles |
| Media | Algo funciona mal pero hay forma de seguir trabajando. | Dos días hábiles | Siguiente actualización |
| Baja | Duda de uso, sugerencia, detalle cosmético. | Tres días hábiles | Sin compromiso de fecha |

**Ser conservadores.** Es mucho mejor prometer un día hábil y responder en dos horas que prometer dos horas y no llegar. El cliente recuerda la promesa incumplida, no el promedio.

#### 13.2 Canal y registro

- Un solo canal declarado: un correo de soporte. Nada de WhatsApp personal, porque no deja registro y no se puede repartir entre dos.
- Toda solicitud queda anotada en la bitácora de la ficha del cliente en el panel.
- Las preguntas que se repiten se van juntando: son el guion de la futura sección de preguntas frecuentes y, mientras tanto, indican qué parte del producto no se entiende.

### 14. Incidentes

Un plan de una página, escrito antes de necesitarlo. Improvisar durante un incidente es lo que convierte un problema en una crisis.

#### 14.1 Si el sistema se cae

1. Confirmar el alcance: ¿son todos los clientes o uno solo?
2. Avisar por correo a los clientes afectados antes de que ellos escriban. Un aviso proactivo cambia por completo cómo se percibe una caída.
3. Resolver.
4. Avisar que ya está resuelto y explicar en una línea qué pasó.
5. Anotar la causa y qué se hará para que no se repita.

#### 14.2 Si hay un incidente de seguridad

**CRÍTICO.** Este es el escenario que puede terminar con el negocio. El acuerdo de tratamiento de datos fija un plazo para notificar al cliente; ese plazo se cumple aunque todavía no se sepa el alcance completo.
1. Contener: cortar el acceso, cambiar credenciales, aislar lo afectado.
2. Determinar qué datos y qué clientes se vieron comprometidos.
3. Notificar a los clientes afectados dentro del plazo pactado, con lo que se sepa hasta ese momento.
4. Apoyar al cliente en sus propios deberes de reporte ante la Superintendencia de Industria y Comercio, sin asumirlos: esos deberes son de él, como responsable del tratamiento.
5. Consultar al abogado antes de cualquier comunicación pública.
6. Documentar todo, en el momento. Un incidente bien documentado y bien manejado es defendible; uno mal documentado no.

#### 14.3 Si un cálculo estaba mal

Es el incidente más probable de todos y el que nadie prevé. Un error en el motor de cálculo no rompe nada visible: simplemente produce números equivocados en todos los clientes a la vez.
- Corregir el cálculo y volver a ejecutar sobre los datos existentes.
- Identificar qué clientes y qué registros quedaron afectados.
- Avisarles, con el dato viejo y el nuevo. Ocultarlo es peor: el cliente puede haber liquidado a alguien con ese número.
- Agregar el caso a la batería de pruebas para que no vuelva a pasar.

### 15. Ritmo de trabajo

| Cuándo | Qué |
|---|---|
| Cada semana | Media hora entre los dos: qué se construyó, qué está trabado, qué decisión hace falta. Revisar el bloque D del documento de Puntos Pendientes. |
| Cada mes | Revisar el panel de inicio del proveedor: clientes en riesgo, mora, entregabilidad del correo, costos del mes. |
| Cada trimestre | Revisar precios contra el costo real. Revisar qué se pide en soporte y qué dice del producto. |
| Cada año | Cargar el calendario de festivos del año siguiente. Contrastar las constantes legales del sistema con la normativa vigente. Probar una restauración de copia de seguridad de principio a fin. |

### 16. Qué medir desde el primer cliente

Con pocos clientes no hacen falta indicadores sofisticados. Estos cinco bastan y todos salen del panel:

| Indicador | Por qué |
|---|---|
| Días entre la firma y la primera novedad registrada por el cliente | Mide si la puesta en marcha funciona. Si pasan semanas, el problema está en el onboarding, no en el producto. |
| Frecuencia de entrada por cliente | Es la señal más temprana de cancelación. Una caída marcada frente al mes anterior anticipa la baja. |
| Novedades registradas por mes y por cliente | Distingue al cliente que usa el sistema del que solo lo tiene contratado. |
| Horas de soporte por cliente al mes | Es el costo que nadie calcula y el que decide si el precio alcanza. |
| Correos enviados por cliente | El costo de infraestructura que sí escala, y la justificación de cobrar por usuario. |

> **El indicador que no hay que perseguir todavía**
>
> Con menos de diez clientes, cualquier cálculo de tasa de cancelación es ruido: un cliente que se va son diez puntos porcentuales.
>
> En esta etapa lo útil es hablar con cada cliente que se va y entender por qué. Cinco conversaciones honestas valen más que cualquier tablero.

## 17. Qué hay que hacer con esto

### 17.1 Cambios en el plan de construcción

El panel del proveedor añade una fase que no estaba. Va después de la fase 1, no al final, por una razón práctica: hasta que exista la pantalla de crear empresa, todas las pruebas se hacen con datos metidos a mano.

| Fase | Contenido | Duración |
|---|---|---|
| Nueva fase 1B | Panel del proveedor: crear empresa, listado, ficha, parámetros, catálogos y festivos. Lo mínimo para operar. | 1 semana |
| Se suma a la fase 5 | Suscripciones, consola de alertas, salud del sistema, métricas de negocio y acceso al entorno del cliente. | +1 semana |

*El plan pasa de unas diez semanas a unas doce.*

### 17.2 Cambios en el modelo de datos

El modelo del Documento 3 necesita cuatro tablas más, todas del lado del proveedor y ninguna con identificador de empresa en el sentido habitual:

| Tabla | Contenido |
|---|---|
| planes | Nombre, precio, empleados incluidos, usuarios incluidos, estado. |
| suscripciones | Empresa, plan, fecha de inicio, próxima fecha de cobro, estado, medio de pago. |
| pagos | Suscripción, monto, fecha, estado, número de factura. |
| notas_cliente | Empresa, autor, fecha, texto. Es la bitácora interna. |

Además, la tabla de empresas necesita campos de salud: fecha del último acceso de cualquier usuario, número de empleados cargados y fecha de la última novedad registrada. Se pueden calcular, pero conviene guardarlos porque se consultan en el listado.

### 17.3 Lo que sigue estando pendiente

- El producto no tiene nombre. En los seis documentos se llama por su descripción. Hace falta un nombre, un dominio y una identidad mínima antes de la primera demostración comercial.
- La escalera de mora: a cuántos días se avisa, se vuelve a avisar y se suspende.
- Los niveles de servicio que se van a prometer por escrito en el contrato, a partir de la tabla del capítulo 13.
- El acuerdo entre socios.
- El correo de soporte y el dominio propio, que además son requisito para la entregabilidad de las alertas.

*Fin del documento — Versión 1.0*
