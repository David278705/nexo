# Especificación Funcional — Sistema de Gestión de Talento Humano

> Documento maestro de construcción
> Documento de referencia del proyecto. Generado desde la fuente original; no editar a mano.

**DOCUMENTO MAESTRO DE CONSTRUCCIÓN**
**Especificación Funcional**
**Sistema de Gestión de Talento Humano**
*Todo lo definido y cerrado, listo para construir*
*Lo que aún está por decidir o validar se encuentra en el documento «Puntos Pendientes»*
**Angela Lozano  ·  Experta en Gestión Humana**
**David Lozano  ·  Desarrollo**
Versión 3.0 — Consolida 4 sesiones de definición y 4 de revisión

## Cómo usar este documento

Este es el documento de construcción. Todo lo que aparece aquí está decidido y validado por Angela; nada está pendiente de discusión. Si al construir surge una duda que no se resuelve aquí, la respuesta está en el documento «Puntos Pendientes» o todavía no existe y hay que plantearla.

| Documento | Qué contiene | Cuándo se consulta |
|---|---|---|
| Especificación Funcional (este documento) | Módulos, campos, reglas de cálculo, alertas, reportes e indicadores. Todo cerrado. | Durante toda la construcción. Es la fuente de verdad. |
| Puntos Pendientes | Decisiones aplazadas, validaciones legales por hacer, preguntas abiertas, riesgos e ideas de fase dos. | Antes de vender y en las reuniones de seguimiento. |
| Acuerdo de Tratamiento de Datos | Borrador contractual para firmar con cada cliente. | Cuando se acerque la primera venta. |

> **Convenciones**
>
> · REGLA marca un comportamiento del sistema que debe implementarse tal cual está escrito.
>
> · IMPORTANTE marca un punto donde es fácil equivocarse o donde una implementación descuidada tendría consecuencias legales para el cliente.
>
> · Los campos marcados como Obligatorio impiden guardar el registro si están vacíos. Los marcados como Opcional no.
>
> · Toda referencia a la ley colombiana tiene su fundamento en el Anexo A, al final del documento.

## Contenido

<!-- tabla de contenido generada por el editor -->

## 1. El producto

### 1.1 Qué es

Un software de acompañamiento administrativo laboral, entregado como servicio en la nube, que centraliza la información de gestión humana de una empresa y automatiza los controles que hoy se llevan a mano. Tres pilares:
- Alertas y automatizaciones: el sistema avisa antes de que un plazo legal se venza, en lugar de depender de la memoria de la persona encargada.
- Reportería: exportaciones e indicadores que permiten ver el estado del personal sin consolidar Excel a mano.
- Trazabilidad: un único lugar donde queda registrada toda la historia laboral de cada empleado dentro de la empresa.

### 1.2 Problemas que resuelve

| Problema | Cómo lo aborda el sistema |
|---|---|
| La información de gestión humana está dispersa en varios Excel y carpetas distintas. | Una sola base de datos por empresa. |
| El registro de novedades no está en un mismo lugar, lo que impide la trazabilidad. | Módulo único de novedades asociado a cada empleado. |
| Incumplimiento laboral y legal por olvidos de fechas clave. | Motor de alertas sobre periodos de prueba, vencimientos, prórrogas y vacaciones. |
| Errores de liquidación por mal conteo de vacaciones. | Cálculo automático del saldo, sin conteo manual de días hábiles. |
| Falta de visibilidad de indicadores clave. | Dashboard construido sobre la información ya registrada. |

### 1.3 Mercado objetivo

| Segmento | Número de empleados |
|---|---|
| Microempresa | Hasta 10 |
| Pequeña empresa | Hasta 50 |
| Mediana empresa | De 52 a 200 |

**Perfil del cliente ideal:** empresas con muy poco personal administrativo, donde la gestión humana la lleva una o dos personas que además hacen otras cosas. Son empresas que llevan años operando, no que están arrancando: esto condiciona por completo el proceso de carga inicial descrito en el capítulo 12.

> **Principio rector**
>
> "El gol de todo esto va a estar en qué tan fácil sea de usar. Teniendo la misma idea y los mismos módulos, la ejecución puede ser muy buena o muy mala."
>
> Ante dos formas de resolver algo, se elige la que le exija menos pasos y menos decisiones al usuario.

## 2. Modelo de operación

### 2.1 SaaS con acompañamiento

El producto se vende como software como servicio, con suscripción. La empresa no se registra sola: el proveedor crea la cuenta, la parametriza y carga la información inicial.

### 2.2 Proceso de puesta en marcha

1. Reunión de aproximadamente una hora. Se recoge la información de la empresa, se define cuántos usuarios administradores tendrá y se le explica el producto.
2. Se solicita y se archiva el certificado de existencia y representación legal de Cámara de Comercio. La verificación es manual y externa: el sistema no tiene ningún módulo ni integración para certificar empresas.
3. El proveedor crea la cuenta empresa y realiza la parametrización inicial, sugiriendo desde la experiencia de Angela cómo dejarla configurada.
4. Se entregan al cliente las plantillas de Excel para la carga inicial. El cliente las diligencia y las devuelve.
5. El proveedor ejecuta la carga inicial. El cliente nunca carga los Excel por su cuenta (capítulo 12).
6. Se entrega la cuenta de superadministrador. A partir de ahí el cliente opera de forma autónoma.

### 2.3 Demostración comercial

**REGLA.** Existe un ambiente de demostración con una empresa ficticia ya cargada: empleados de mentira, novedades, contratos e histórico suficiente para que los indicadores y reportes se vean poblados. Un sistema vacío no permite mostrar el valor del producto.
- El prospecto recibe un usuario de prueba con vigencia limitada a un número de días definido.
- Dentro de la demostración puede agregar, editar y eliminar registros, consultar el dashboard y descargar reportes.
- El proceso comercial es: primero una sesión guiada donde se le muestra el sistema, y después se le entrega el acceso para que lo explore por su cuenta.
- Los datos de la demostración son ficticios. Ningún dato personal real se usa con fines comerciales.

### 2.4 Estructura de licencias

El precio depende de dos variables: el número de empleados gestionados y el número de usuarios administradores que solicita el cliente. No es lo mismo un cliente de 50 empleados con un solo usuario que uno de 50 empleados con tres.
*La tabla de precios concreta no bloquea el desarrollo y se definirá más adelante.* *Ver el documento «Puntos Pendientes».*

## 3. Usuarios, roles y permisos

| Rol | Quién es | Qué puede hacer |
|---|---|---|
| Owner (proveedor) | El equipo dueño del producto. No hace parte de lo que ve el cliente. | Crear y administrar las cuentas empresa. Editar la información de la empresa. Ejecutar la carga inicial. |
| Superadministrador | La cuenta empresa. Una sola por cliente. | Acceso total a todos los módulos. Crea usuarios administradores y les asigna acceso a módulos concretos. Único con acceso al módulo de pago. |
| Administrador | Usuarios creados por el superadministrador. | Acceso únicamente a los módulos que le fueron asignados. |

- Un usuario administrador queda anidado a una única empresa. Al iniciar sesión entra directamente al entorno de su empresa. No existe forma de acceder a otra empresa, ni a sus datos, ni a sus reportes, ni a su auditoría.
- Los permisos se asignan por módulo. Una persona puede encargarse solo de vacaciones y otra solo de contratos. También es válido que varias compartan los mismos accesos.
- Datos de un usuario administrador: nombre, correo, contraseña y foto.
- La información de la empresa (nombre, NIT, teléfono, descripción, foto) no la edita el cliente. Si necesita cambiarla, lo solicita al proveedor.

> **Regla de aislamiento — no negociable**
>
> Ningún usuario puede alcanzar datos de una empresa distinta de la suya, por ningún camino: pantalla, reporte, exportación, búsqueda, auditoría o petición directa al servidor.
>
> El filtro por empresa debe estar en la capa de datos, no en la interfaz. Que una pantalla no muestre un dato no significa que ese dato no se pueda pedir.

## 4. Parametrización de la empresa

Configuraciones que se definen al crear la cuenta y que condicionan cómo se comporta el sistema para ese cliente.

| Parámetro | Opciones | Qué afecta | ¿Se puede cambiar después? |
|---|---|---|---|
| Datos de la empresa | Nombre, NIT, teléfono | Identificación de la cuenta. | Solo el proveedor |
| ¿El sábado es día laboral? | Sí / No | El cálculo de días de vacaciones y qué festivos aplican. | Sí |
| ¿Las licencias, ausencias y suspensiones afectan la acumulación de vacaciones? | Sí / No | El cálculo mensual del saldo de vacaciones. | Sí |
| Correos que reciben las alertas | Administradores con acceso a cada módulo | A quién llega cada alerta del capítulo 9. | Sí |
| ¿Se habilita el bloque de perfil sociodemográfico? | Sí / No | Muestra u oculta el bloque de campos de 5.2. | Sí |

> **Sobre habilitar el perfil sociodemográfico después**
>
> Si la empresa lleva un año usando el sistema y decide habilitarlo, los empleados ya registrados quedan con esos campos en blanco. La empresa los llena si quiere; si no lo hace, se quedan vacíos y no pasa nada. El sistema no obliga a completarlos retroactivamente ni lo señala como error.

## 5. Módulo de Contratación

Donde se registra el ingreso de una persona a la empresa y donde nace toda la información sobre la que operan los demás módulos.

### 5.1 Registro del empleado

#### Identificación personal

| Campo | Tipo de dato | Obligatorio | Notas |
|---|---|---|---|
| Primer nombre | Texto | Sí |  |
| Segundo nombre | Texto | No | Hay personas que no tienen segundo nombre. |
| Primer apellido | Texto | Sí |  |
| Segundo apellido | Texto | No | Hay personas que no tienen segundo apellido. |
| Tipo de documento | Lista | Sí | Cédula de ciudadanía · Tarjeta de identidad · Cédula de extranjería · Pasaporte · PPT. La tarjeta de identidad es necesaria porque en Colombia los menores pueden trabajar. |
| Número de documento | Numérico | Sí | Identificador único de la persona dentro de la empresa. Es la llave con la que se vincula toda la información en los demás módulos. |
| Fecha de nacimiento | Fecha | Sí |  |
| País de nacimiento | Lista | Sí | Lista completa de países. |
| Departamento de nacimiento | Lista o texto | Sí | Ver la regla de 5.1.1. |
| Ciudad de nacimiento | Lista o texto | Sí | Ver la regla de 5.1.1. |
| Fecha de expedición del documento | Fecha | Sí |  |
| Lugar de expedición del documento | Lista o texto | Sí | Misma regla de 5.1.1. |
| Género | Lista | Sí |  |

#### 5.1.1 Regla de país, departamento y ciudad

**REGLA.** El país siempre se elige de una lista desplegable completa. El comportamiento de los dos campos siguientes depende de la respuesta:
- Si el país es Colombia: departamento y ciudad se eligen de listas desplegables encadenadas, alimentadas por una fuente de datos oficial. Al elegir el departamento se cargan sus ciudades.
- Si el país es distinto de Colombia: departamento y ciudad se digitan manualmente como texto libre. No se construyen listas de ciudades del mundo entero: el volumen no lo justifica y obligaría a pagar por un servicio externo.

> **Consecuencia que debe quedar en el contrato**
>
> Cuando el dato se digita a mano, el sistema no puede garantizar que esté bien escrito. El contrato con el cliente debe establecer que toda la información que él ingresa manualmente está bajo su responsabilidad y que le corresponde a él verificar que sea correcta.

#### Residencia

**IMPORTANTE.** La residencia siempre es en Colombia. El sistema solo gestiona contratos laborales colombianos, y un contrato laboral solo puede celebrarse con una persona que resida en el país. Vincular a alguien que reside en el exterior se haría por prestación de servicios, figura que este sistema no maneja.

| Campo | Tipo de dato | Obligatorio | Notas |
|---|---|---|---|
| Dirección | Texto | Sí |  |
| Departamento de residencia | Lista | Sí | Listas encadenadas de Colombia. |
| Ciudad de residencia | Lista | Sí |  |

#### Seguridad social

| Campo | Tipo de dato | Obligatorio | Notas |
|---|---|---|---|
| EPS | Lista | Sí | Debe construirse el listado completo de las EPS vigentes en Colombia. |
| Fondo de pensión | Lista | Sí |  |
| ARL | Lista | Sí |  |
| Tipo de riesgo | Lista | Sí | Niveles I a V. |
| Caja de compensación | Lista | Sí |  |

#### Información laboral y contractual

| Campo | Tipo de dato | Obligatorio | Notas |
|---|---|---|---|
| Cargo | Texto | Sí |  |
| Tipo de contrato | Lista | Sí | Tres opciones: Término indefinido · Término fijo · Obra o labor. "Obra o labor" es una sola opción, no dos. |
| Fecha de ingreso | Fecha | Sí | Sobre ella se calcula la acumulación de vacaciones y el periodo de prueba. |
| Fecha de finalización | Fecha | Solo en término fijo | Alimenta el periodo de prueba, las alertas de renovación y el control del tope legal. |
| Salario mensual | Numérico | Sí |  |
| Bonificación | Numérico | Sí | Puede quedar en cero. |
| Entidad bancaria | Lista | Sí | Solo la entidad, no el número de cuenta. |
| Estado | Automático | — | Ver 5.3. No lo digita el usuario. |

### 5.2 Perfil sociodemográfico

Bloque opcional que la empresa activa o desactiva en la parametrización. Su razón de ser no es estadística: es un insumo que el Sistema de Gestión de Seguridad y Salud en el Trabajo exige a las empresas para elaborar el diagnóstico de condiciones de salud.
**REGLA.** Aunque el bloque esté habilitado, **todos sus campos son opcionales** . A diferencia del resto del formulario, dejarlos en blanco no impide guardar el registro. Si el cliente decide no llenarlos, es su decisión.

| Campo | Tipo de dato |
|---|---|
| Estado civil | Lista |
| Nivel educativo alcanzado | Lista |
| Número de hijos | Numérico |
| Número de personas a cargo | Numérico |
| Tipo de vivienda | Propia / Arrendada / Familiar |
| Estrato socioeconómico | Lista 1 a 6 |
| Medio de transporte al trabajo | Lista |
| Tiempo de desplazamiento al trabajo | Lista por rangos |

### 5.3 Estado del empleado

**REGLA.** El estado no lo digita el usuario. Al crear un empleado queda **ACTIVO** automáticamente. Cambia a **INACTIVO** únicamente cuando se registra su salida en el módulo de Terminaciones.
- Sobre un empleado inactivo no se pueden registrar novedades.
- Sobre un empleado inactivo el sistema deja de generar alertas.

### 5.4 Reglas del tipo de contrato

| Tipo | Fechas requeridas | Observación |
|---|---|---|
| Término indefinido | Solo fecha de ingreso | Es la regla legal por defecto desde la reforma laboral de 2025. |
| Término fijo | Fecha de inicio y fecha de finalización | Sujeto a las reglas de prórroga y tope de duración de 5.5. |
| Obra o labor | Solo fecha de ingreso | El contrato termina cuando termina la obra para la que se contrató a la persona. |

Los contratos por prestación de servicios quedan expresamente fuera del sistema: no son laborales sino comerciales, y ninguna funcionalidad del sistema les aplica.

### 5.5 Renovaciones y cambio de modalidad

Un contrato a término fijo que se prorroga no genera una vinculación nueva: no hay salida de la persona ni interrupción del vínculo. Se prorroga el mismo contrato.

#### Acción "Renovar contrato"

- Disponible únicamente en contratos a término fijo.
- El administrador ingresa la nueva fecha de finalización. La duración de la prórroga la define el cliente y no tiene que ser igual a la del contrato inicial: puede ser inferior, igual o superior.
- Queda registrada en el historial con la fecha en que se hizo y las fechas de inicio y fin del nuevo periodo.
- **Es una acción distinta de editar la fecha de finalización.** Editar sirve para corregir un error de digitación; renovar es un hecho jurídico nuevo. El sistema ofrece las dos acciones por separado, con nombres y consecuencias visiblemente distintos, y no permite que una se use en lugar de la otra.

#### Validaciones bloqueantes al renovar

**IMPORTANTE.** Estas dos validaciones **no permiten guardar** . No son advertencias: registrar la prórroga sería registrar algo ilegal.

| Validación | Comportamiento |
|---|---|
| La nueva fecha de finalización hace que el vínculo a término fijo supere los cuatro años | Se bloquea el guardado. Mensaje: el contrato superaría el tope legal de duración a término fijo; al superarlo se convierte en indefinido por ley. Se ofrece la acción "Cambiar a término indefinido". |
| El contrato es inferior a un año, ya acumula el número de prórrogas permitidas y la nueva prórroga es inferior a un año | Se bloquea el guardado. Mensaje: a partir de esta renovación el contrato no puede prorrogarse por menos de un año. |

#### Historial de renovaciones

- Cada contrato a término fijo tiene una vista de historial de renovaciones.
- El historial incluye el periodo inicial del contrato, no solo las prórrogas posteriores.
- Sobre este historial se calculan el número de prórrogas acumuladas y la duración total del vínculo, que son los insumos de las validaciones anteriores y de las alertas del capítulo 9.
- La fecha límite del preaviso debe ser visible en la ficha del contrato, no solo llegar como alerta por correo.

#### Acción "Cambiar a término indefinido"

- Disponible únicamente en contratos a término fijo. El cambio inverso, de indefinido a fijo, no existe.
- Queda registrado con la fecha en que se hizo el cambio, para efectos de trazabilidad y reportes.
- **La fecha de ingreso del empleado no cambia: sigue siendo la del inicio del contrato a término fijo.** El vínculo es el mismo, solo cambia su modalidad. Los cálculos de vacaciones y antigüedad siguen corriendo desde la fecha original.
- Puede usarse de forma voluntaria por la empresa o como respuesta a la validación de tope legal.

> **Contratos de obra o labor**
>
> No se ofrece la acción de cambio de modalidad en contratos de obra o labor. Para pasar a indefinido, la empresa debe suscribir un otrosí con el trabajador o firmar un contrato nuevo, lo cual ocurre fuera del sistema.
>
> Sí existe una conversión que opera por ley y que la empresa debe conocer: si terminada la obra el trabajador sigue prestando servicios sin nuevo acuerdo escrito, el contrato se entiende celebrado a término indefinido desde el inicio de la relación. Por eso el sistema incluye la alerta de contratos de obra o labor prolongados descrita en 9.6.

### 5.6 Un registro por vinculación

Cada vinculación laboral es un registro independiente. Si una persona se retiró y vuelve a ser contratada, se crea un registro nuevo: no se reactiva ni se edita el anterior.

#### Advertencia de reingreso

**REGLA.** Al crear una vinculación cuyo número de documento ya tuvo un registro anterior en esa empresa, el sistema muestra en pantalla la siguiente advertencia:

> **Texto de la advertencia**
>
> "Esta persona ya tuvo una vinculación anterior con la empresa. Verifique si es válido pactar periodo de prueba en este contrato."
>
> Y a continuación, la nota:
>
> "Por configuración del sistema usted recibirá las alertas de periodo de prueba estipuladas. Sin embargo, le corresponde a usted verificar si ese periodo de prueba es válido, considerando que la persona ya tuvo una vinculación previa."

- La advertencia se muestra con especial énfasis si el reingreso ocurre dentro de los 30 días siguientes a la salida anterior.
- El sistema **no decide** si el periodo de prueba aplica y **sí genera** las alertas de periodo de prueba por defecto. La validez es un asunto legal que depende de cada caso y le corresponde a la empresa.

## 6. Módulo de Novedades de personal

### 6.1 Patrón común de registro

Las tres novedades comparten el mismo flujo. Debe ser idéntico en las tres para que el usuario lo aprenda una sola vez.
1. El usuario está en la tabla del módulo, con sus filtros. Pulsa el botón "Nuevo".
2. Se abre una ventana pequeña que pide únicamente el número de documento del empleado.
3. El sistema valida el documento al salir del campo. Si no corresponde a un empleado activo de esa empresa, muestra el mensaje "Empleado no encontrado" y no deja continuar.
4. Si el documento existe, se abre la ventana grande con un recuadro resumen del empleado —nombre completo, cargo y datos básicos— y debajo el formulario de la novedad.

> **Por qué la validación es explícita**
>
> El caso real: una persona ingresó ayer y hoy se incapacitó. Quien registra la novedad puede no saber que el empleado todavía no fue creado en el sistema. Sin la validación, el usuario llenaría el formulario completo para descubrir el problema al final, o peor, generaría una novedad sin empleado asociado.
>
> La regla es absoluta: ninguna novedad de ningún módulo puede registrarse sobre un documento que no corresponda a un empleado activo.

### 6.2 Reglas comunes a todas las novedades

| Regla | Detalle |
|---|---|
| Se pueden registrar fechas pasadas | Sin límite hacia atrás. El caso típico: una licencia de maternidad puede entregarse cuatro meses después de haber empezado. El sistema no exige que la fecha sea futura ni reciente. |
| Se pueden editar | Cualquier registro de novedad puede modificarse, sin importar su antigüedad. |
| Se pueden eliminar | Cualquier registro puede eliminarse. Al hacerlo, los saldos afectados se recalculan. |
| Todo queda en la auditoría | Cada creación, edición y eliminación se registra en el módulo de Auditoría (capítulo 8). Es la contrapartida de dar libertad total sobre los registros. |

### 6.3 Incapacidades

| Campo | Tipo de dato | Notas |
|---|---|---|
| Número de documento del empleado | Numérico | Con validación de existencia. |
| Fecha de inicio | Fecha |  |
| Fecha de terminación | Fecha |  |
| Días de incapacidad | Calculado | Días corridos, incluyendo el día inicial y el final. Del 14 al 16 de julio son tres días. |
| Código de diagnóstico | Texto | Dato sensible. Ver el recuadro. |
| Prórroga | Sí / No |  |
| EPS que expide la incapacidad | Lista |  |
| Origen | Laboral / Común |  |

> **El código de diagnóstico se mantiene**
>
> El empleador es responsable de tramitar y transcribir la incapacidad ante la EPS, de modo que recibe legítimamente el certificado, que incluye el diagnóstico. Además, el Sistema de Gestión de Seguridad y Salud en el Trabajo lo obliga a llevar el registro de ausentismo por causa médica y a desarrollar programas de vigilancia epidemiológica.
>
> Lo prohibido es exigir al trabajador la historia clínica o la epicrisis, e indagar sobre su padecimiento más allá de lo que consta en el certificado. El sistema no almacena documentos, así que ese riesgo está estructuralmente descartado.
>
> Por ser dato sensible, el campo debe tener acceso restringido a los usuarios con permiso sobre el módulo de novedades, y en el dashboard debe mostrarse solo de forma agregada.

### 6.4 Vacaciones

#### Acumulación

**REGLA.** Desde el momento en que se registra un empleado con su fecha de ingreso, el sistema acumula automáticamente 1,25 días de vacaciones por cada mes trabajado. El administrador nunca calcula ni digita este saldo.
- El saldo debe poder consultarse por empleado.
- Los días que la persona pasa en vacaciones siguen causando acumulación.
- Cuando el empleado viene de la carga inicial, la acumulación parte del saldo cargado, no de cero (capítulo 12).

#### Registro

| Campo | Tipo de dato | Notas |
|---|---|---|
| Número de documento del empleado | Numérico | Con validación de existencia. |
| Día inicial de vacaciones | Fecha |  |
| Día final de vacaciones | Fecha |  |
| Días de vacaciones | Calculado | Se descuenta del saldo acumulado. |

#### Reglas de conteo

- Se cuentan días hábiles. El cálculo no es lineal como el de las incapacidades.
- Los domingos y los festivos en Colombia no cuentan como días de vacaciones.
- El sábado cuenta o no según lo parametrizado para esa empresa.

#### Alerta de saldo insuficiente

**REGLA.** El sistema compara los días solicitados contra el saldo que el empleado tendrá **en la fecha en que va a disfrutarlas** , no contra el saldo de hoy.
- El mensaje debe decir cuántos días le faltan, o cuántos días tendrá disponibles ese día. Un mensaje genérico no sirve: quien decide necesita el número.
- Ejemplo de mensaje: "Al 28 de agosto esta persona tendrá 12,75 días disponibles. Está registrando 14 días: faltan 1,25 días."
- La advertencia no bloquea el registro. Hay empresas que aprueban vacaciones anticipadas y el saldo queda en negativo.

### 6.5 Licencias no remuneradas, ausencias y suspensiones

Un solo tipo de novedad con un campo de clasificación. Las tres situaciones se calculan igual y todas se entienden como suspensión del contrato.

| Campo | Tipo de dato | Notas |
|---|---|---|
| Número de documento del empleado | Numérico | Con validación de existencia. |
| Tipo de novedad | Lista | Licencia no remunerada · Ausencia · Suspensión. |
| Fecha inicial | Fecha |  |
| Fecha final | Fecha |  |
| Días | Calculado | Días corridos. Ver la regla de conteo. |

> **Regla de conteo**
>
> Los días se cuentan en DÍAS CORRIDOS. Si dentro del periodo cae un domingo o un festivo, ese día SÍ se cuenta dentro de la novedad y, por lo tanto, dentro de la suspensión del contrato.
>
> Ejemplo: una licencia del jueves al martes siguiente, en una empresa donde se descansa el domingo, son seis días y seis días de suspensión, incluido el domingo.
>
> Esto hace que estas novedades se cuenten igual que las incapacidades y distinto de las vacaciones. Son tres reglas de conteo distintas y no deben confundirse en el código.

#### Efecto sobre la acumulación de vacaciones

Cuando la empresa tiene parametrizado que estas novedades afectan la acumulación, el sistema reduce proporcionalmente el 1,25 mensual del mes o meses involucrados.
- Licencia del 16 de marzo al 15 de abril: en marzo acumula la mitad, y en abril también.
- Cuatro días de licencia en un mes: ese mes acumula en proporción a 26 días trabajados y no a 30.

## 7. Módulo de Terminaciones

### 7.1 Campos

| Campo | Tipo de dato | Notas |
|---|---|---|
| Número de documento del empleado | Numérico | Con validación de existencia. |
| Fecha de salida | Fecha |  |
| Tipo de salida | Lista | Ver 7.2. |
| Motivo de la baja | Lista dependiente | Las opciones cambian según el tipo de salida. Ver 7.3. |
| Observaciones | Texto libre | Opcional. |

- Al registrar la terminación, el empleado pasa a estado inactivo y el sistema deja de generar alertas sobre él.

### 7.2 Tipos de salida

| Tipo de salida | Cuándo se usa |
|---|---|
| Renuncia | El trabajador decide voluntariamente terminar el contrato. |
| Terminación de contrato por mutuo acuerdo | Ambas partes acuerdan poner fin al contrato. |
| Terminación en periodo de prueba | La empresa termina el contrato dentro del periodo de prueba. |
| Despido con justa causa | La empresa termina el contrato invocando una justa causa legal. No es únicamente disciplinario: el reconocimiento de la pensión también es una justa causa. |
| Terminación sin justa causa | La empresa termina el contrato sin invocar justa causa, con la indemnización correspondiente. |
| Expiración del plazo fijo pactado | El contrato a término fijo llegó a su fecha de finalización y no se prorrogó. |
| Terminación de la obra o labor contratada | Terminó la obra para la cual se contrató a la persona. |

### 7.3 Motivos de la baja

El campo se comporta como una lista dependiente: al elegir el tipo de salida se cargan solo los motivos que tienen sentido para ese tipo.

#### Renuncia

- Mejor oferta laboral — salario
- Mejor oferta laboral — desarrollo profesional
- Inconformidad con el salario o los beneficios
- Clima laboral o relación con el jefe inmediato
- Motivos personales o familiares
- Motivos de salud
- Estudios
- Cambio de ciudad o de país
- Distancia o tiempo de desplazamiento
- Emprendimiento propio
- No informó el motivo
*La pensión o jubilación no aparece aquí: cuando la persona se pensiona no renuncia, es una justa causa para terminar el contrato.*

#### Terminación por mutuo acuerdo

- Reestructuración organizacional
- Acuerdo económico de retiro
- Cambio en las condiciones del cargo
- Motivos personales del trabajador
- Otro

#### Terminación en periodo de prueba

- No cumple el perfil técnico del cargo
- Desempeño insuficiente durante la inducción
- Falta de adaptación al equipo o a la cultura
- Incumplimiento de horarios o asistencia
- Otro
*Este tipo de salida corresponde siempre a una decisión del empleador. Si es el trabajador quien se va durante el periodo de prueba, se registra como renuncia.*

#### Despido con justa causa

- Problemas disciplinarios
- Bajo desempeño
- Incumplimiento de las obligaciones del cargo
- Reconocimiento de pensión
- Otra justa causa legal
*Deliberadamente son categorías generales y no la enumeración completa de las causales del artículo 62 del Código Sustantivo del Trabajo: una lista de veinte opciones legales no la usa nadie y ensucia el reporte.*

#### Terminación sin justa causa

- Reestructuración o reducción de personal
- Cierre de un área o de un proyecto
- Situación económica de la empresa
- Desempeño no satisfactorio sin proceso disciplinario
- Duplicidad de funciones
- Decisión de la dirección
- Otro

#### Expiración del plazo fijo pactado

Sin submotivos. El motivo es el mismo tipo de salida: se acabó el contrato. El sistema lo autocompleta y no pide nada adicional.

#### Terminación de la obra o labor contratada

Un solo motivo: "Finalización de la obra contratada". El sistema lo autocompleta.

### 7.4 Agrupación para el indicador de rotación

Cada tipo de salida pertenece además a una categoría macro que el usuario no digita: la deduce el sistema.

| Categoría | Tipos de salida que agrupa |
|---|---|
| Rotación voluntaria | Renuncia |
| Rotación involuntaria | Despido con justa causa · Terminación sin justa causa · Terminación en periodo de prueba · Terminación por mutuo acuerdo |
| Fin natural del vínculo | Expiración del plazo fijo pactado · Terminación de la obra o labor contratada |

> **Por qué el mutuo acuerdo va en involuntaria**
>
> Se ubica ahí por criterio de Angela y tiene sentido operativo: cuando la empresa acuerda con un trabajador su salida, la motivación primera casi siempre es de la empresa. Clasificarlo como voluntario inflaría artificialmente el indicador de rotación voluntaria y ocultaría un problema real.
>
> Como el tipo de salida queda guardado por separado, el reporte de rotación siempre permite desagregarlo si en algún momento se quiere analizar aparte.

## 8. Módulos de Aumentos y de Auditoría

### 8.1 Aumentos salariales y promociones

| Campo | Tipo de dato | Notas |
|---|---|---|
| Número de documento del empleado | Numérico | Con validación de existencia. |
| Tipo de movimiento | Lista | Aumento salarial (mismo cargo, sube el salario) · Promoción (cambia de cargo). |
| Salario actual | Automático | Se muestra el salario vigente. |
| Nuevo salario | Numérico |  |
| Cargo actual | Automático | Se muestra el cargo vigente. |
| Nuevo cargo | Texto | Si el movimiento es solo aumento salarial, se repite el mismo cargo. |
| Fecha de efectividad | Fecha |  |

- Al guardar, el salario y el cargo del empleado se actualizan en su registro principal.
- Existe una vista de histórico con todos los movimientos de la persona, mostrando en cada uno el salario y el cargo anterior frente al nuevo.

### 8.2 Módulo de Auditoría

Una tabla que muestra toda acción realizada por cualquier usuario dentro de la empresa. Es la contrapartida de haber dado libertad total para editar y eliminar registros.

| Columna | Contenido |
|---|---|
| Fecha y hora | Momento exacto de la acción. |
| Usuario | Quién la realizó. |
| Módulo | Dónde ocurrió. |
| Acción | Creó · Editó · Eliminó. |
| Registro afectado | Identificación del empleado o del registro. |
| Detalle | Qué cambió: valor anterior y valor nuevo. |

- El módulo es visible para el cliente, filtrado exclusivamente a las acciones realizadas dentro de su empresa.
- **La tabla es de solo lectura.** Nadie puede editarla ni borrarla, ni siquiera el superadministrador.
- Responde la pregunta que va a llegar tarde o temprano: "¿quién eliminó este registro?".

## 9. Motor de alertas

### 9.1 Reglas transversales

- Una alerta solo se envía si el empleado está activo.
- Si la fecha de envío cae en sábado, domingo o festivo, la alerta se envía el día hábil inmediatamente anterior.
- Cuando se adelanta el envío, el texto se ajusta: si decía "mañana" y se adelantó dos días, debe decir "en dos días".
- Las alertas no son configurables por la empresa: son parte del servicio que se vende.
- Las alertas de contrato y periodo de prueba se envían de forma individual, una por empleado. Las de resumen mensual se envían agrupadas.

> **Regla de redacción de los mensajes — aplica a todas las alertas**
>
> Toda referencia temporal debe ir acompañada de la fecha exacta entre paréntesis.
>
> Correcto: "Mañana (14 de junio) es el último día del periodo de prueba."
>
> Correcto: "Esta persona completará en 15 días (14 de junio) su periodo de prueba."
>
> Incorrecto: "Mañana es el último día del periodo de prueba."
>
> Quien recibe el correo no debe tener que calcular nada.

> **Cómo se cuentan los meses**
>
> Un mes se cumple el día anterior al mismo número del mes siguiente. Si la persona entró el 15 de abril, cumple un mes el 14 de mayo y dos meses el 14 de junio.

### 9.2 Periodo de prueba — contratos a término indefinido

El periodo de prueba es de dos meses.

| Momento | Mensaje |
|---|---|
| Al cumplir 1 mes desde el ingreso | Esta persona ha completado un mes de periodo de prueba. |
| Al cumplir 45 días desde el ingreso | Esta persona completará en 15 días (fecha) su periodo de prueba. |
| Un día antes de cumplirse los dos meses | Mañana (fecha) es el último día del periodo de prueba. |

*Ejemplo completo: persona que ingresa el 15 de abril. Primera alerta el 14 de mayo. Segunda el 30 de mayo, diciendo "completará en 15 días (14 de junio)". Tercera el 13 de junio, diciendo "mañana (14 de junio) es el último día".*

### 9.3 Periodo de prueba — contratos a término fijo

El sistema calcula el periodo de prueba a partir de la duración pactada: una quinta parte de la duración, sin exceder dos meses, para contratos inferiores a un año. Para contratos de un año o más, el máximo es dos meses.

| Momento | Propósito |
|---|---|
| Dos días antes de finalizar el periodo de prueba | Aviso de que se aproxima el vencimiento, con la fecha. |
| Un día antes de finalizar el periodo de prueba | Último aviso, con la fecha. |

### 9.4 Vencimiento, prórrogas y topes del término fijo

Es el conjunto de alertas de mayor valor comercial del producto.

| Alerta | Cuándo se dispara | Contenido |
|---|---|---|
| Plazo para definir la renovación | 32 días antes de la fecha de finalización | Recuerda que en dos días (fecha) vence el plazo legal de 30 días para avisarle al trabajador si el contrato no se va a renovar. |
| Efecto del silencio | 30 días antes de la fecha de finalización, si no se ha registrado prórroga ni terminación | Si no se avisa hoy (fecha), el contrato queda prorrogado automáticamente por un término igual al último pactado. |
| Última renovación corta posible | Cuando el contrato es inferior a un año y llega al límite de prórrogas cortas | La próxima renovación de este contrato deberá ser de mínimo un año. |
| Cercanía al tope legal | Cuando el vínculo a término fijo se aproxima a los cuatro años | Este contrato se acerca al tope de duración a término fijo. Al superarlo se convierte en indefinido por ley. |
| Contratos vencidos sin gestionar | Resumen mensual, último día hábil del mes | Listado de contratos que superaron su fecha de finalización sin que se registrara prórroga ni terminación. |

### 9.5 Vacaciones

| Alerta | Frecuencia | Para qué sirve |
|---|---|---|
| Acumulación de 15 días | Resumen mensual | Lista las personas que ese mes completan 15 días de vacaciones acumuladas. Ya causaron un periodo completo y conviene programarlas. |
| Año cumplido sin disfrute mínimo | Resumen mensual | Lista las personas que al mes siguiente cumplen un año en la empresa sin haber tomado al menos seis días hábiles continuos. La ley exige ese disfrute mínimo anual. |
| Saldo insuficiente | En pantalla, al registrar | Descrita en 6.4. |

### 9.6 Contratos de obra o labor

No generan alertas de vencimiento, porque no hay fecha previsible. Sí generan una alerta de gestión dentro del resumen mensual: contratos de obra o labor que llevan un tiempo prolongado sin registrar terminación, lo cual puede indicar que el vínculo ya mutó a indefinido sin que nadie lo notara. Aplican también, cuando corresponda, las alertas de periodo de prueba y de vacaciones.

### 9.7 Resumen del calendario

| Frecuencia | Alertas |
|---|---|
| Individuales, por empleado | Periodo de prueba en indefinido (3 avisos) · Periodo de prueba en término fijo (2 avisos) · Plazo de renovación a 32 días · Efecto del silencio a 30 días · Última renovación corta · Cercanía al tope legal. |
| Resumen mensual | Contratos vencidos sin gestionar · Acumulación de 15 días de vacaciones · Año cumplido sin disfrute mínimo · Contratos de obra o labor prolongados. |
| En pantalla, al momento | Saldo de vacaciones insuficiente · Documento inexistente · Advertencia de reingreso · Validaciones bloqueantes de prórroga. |

## 10. Calendario de festivos

**IMPORTANTE.** De esta tabla dependen el cálculo de vacaciones, el de licencias y el corrimiento de todas las alertas al día hábil anterior. Un error aquí afecta a todos los clientes a la vez.
- Los festivos no se calculan con fórmulas en el código: se cargan como una tabla, año por año, verificada manualmente.
- **Es una tarea anual del proveedor** . Debe quedar asignada a una persona y hecha antes de que empiece cada año.
- **Deben cargarse también los festivos que caen en sábado.** Para las empresas que tienen el sábado como día laboral, un festivo en sábado sí afecta el cálculo de vacaciones. Omitirlos produciría cálculos equivocados en ese grupo de clientes.
- La tabla es única del sistema, no por empresa. Lo que varía por empresa es si el sábado es laboral.

## 11. Reportería

### 11.1 Dónde vive la exportación

**REGLA.** Cada módulo tiene su propio botón de exportar, que genera un Excel con exactamente lo que el usuario está viendo, con los filtros que ya aplicó. No existe un módulo de reportes centralizado en la primera versión.

### 11.2 Columnas obligatorias en toda exportación

Todo Excel exportado desde cualquier módulo de novedades debe incluir, además de los campos propios de la novedad:
- Número de documento
- Nombre completo del empleado
- Cargo
*Un reporte que solo trae cédulas y fechas obliga a cruzarlo a mano con otro archivo, que es justamente lo que el producto pretende eliminar.*

### 11.3 Filtros por módulo

Cada módulo tiene los filtros que tienen sentido para su información. No se replican filtros que no aportan.

| Módulo | Filtros |
|---|---|
| Empleados | Estado (activo / inactivo) · Tipo de contrato · Cargo · Rango de fecha de ingreso · Búsqueda por nombre o documento |
| Contratos y renovaciones | Rango de fecha de vencimiento · Tipo de contrato · Número de prórrogas acumuladas · Estado del contrato |
| Incapacidades | Rango de fechas · Origen (común / laboral) · EPS · Empleado |
| Vacaciones | Rango de fechas · Empleado · Cargo |
| Licencias, ausencias y suspensiones | Rango de fechas · Tipo de novedad · Empleado |
| Terminaciones | Rango de fecha de salida · Tipo de salida · Motivo de la baja |
| Aumentos y promociones | Rango de fecha de efectividad · Tipo de movimiento · Cargo |
| Auditoría | Rango de fechas · Usuario · Módulo · Tipo de acción |

El rango de fechas es el filtro más usado y debe estar en todos los módulos donde exista una fecha significativa. Donde no la hay —por ejemplo, el saldo de vacaciones, que es una foto de hoy— no se fuerza un filtro de fechas: se ofrece en cambio una fecha de corte.

### 11.4 Catálogo de reportes

Estos son los reportes que el sistema debe poder producir. El catálogo se irá construyendo y ajustando con el uso real; no es una lista cerrada.

#### Planta de personal

| Reporte | Contenido y utilidad |
|---|---|
| Maestro de empleados activos | Toda la información de las personas vinculadas hoy. Es el reporte que más se pide para cualquier trámite. |
| Histórico de vinculaciones | Todas las vinculaciones, activas e inactivas, con fechas de ingreso y salida. |
| Directorio de seguridad social | EPS, fondo de pensión, ARL, tipo de riesgo y caja por empleado. Útil para verificar afiliaciones y para el pago de la planilla. |
| Cumpleaños y aniversarios laborales | Insumo para actividades de bienestar. Simple de construir y muy apreciado por el área. |

#### Contratación

| Reporte | Contenido y utilidad |
|---|---|
| Contratos por vencer | Contratos a término fijo con vencimiento en los próximos 30, 60 y 90 días, con la fecha límite de preaviso. |
| Periodos de prueba en curso | Personas dentro del periodo de prueba y fecha en que termina. |
| Historial de prórrogas | Por contrato: número de prórrogas, duración de cada una y duración total acumulada del vínculo. |
| Contratos vencidos sin gestionar | Contratos cuya fecha pasó sin registrar prórroga ni terminación. |
| Cambios de modalidad contractual | Contratos que pasaron de término fijo a indefinido, con su fecha. |

#### Novedades

| Reporte | Contenido y utilidad |
|---|---|
| Saldos de vacaciones | Días acumulados, disfrutados y saldo por empleado a una fecha de corte. |
| Vacaciones en riesgo | Personas con más de 15 días acumulados y personas próximas a cumplir un año sin disfrutar los seis días continuos. |
| Vacaciones disfrutadas por periodo | Detalle de los periodos tomados, con fechas y días. |
| Incapacidades por periodo | Detalle con origen, diagnóstico, días y EPS. |
| Ausentismo por causa médica | Días perdidos por incapacidad frente a días programados. Alimenta el indicador exigido por el SG-SST. |
| Licencias, ausencias y suspensiones | Detalle de las suspensiones del contrato en el periodo. |

#### Movimientos, salidas y control

| Reporte | Contenido y utilidad |
|---|---|
| Ingresos y retiros del periodo | Altas y bajas del mes, base de los cálculos de rotación. |
| Rotación con motivos | Salidas clasificadas por tipo y motivo, con la agrupación macro. |
| Movimientos salariales | Aumentos y promociones del periodo, con salario y cargo anterior y nuevo. |
| Registros incompletos | Empleados con información faltante o inconsistente. |
| Auditoría de cambios | Quién creó, modificó o eliminó cada registro y cuándo. Disponible para el cliente, filtrado a su empresa. |

## 12. Dashboard

El dashboard muestra de forma dinámica lo que los reportes entregan en Excel. Al igual que el catálogo de reportes, se irá construyendo con el uso real.

### 12.1 Orden de la pantalla

El criterio del orden es sencillo: primero lo que exige una acción, después lo que describe el estado de la empresa. Quien entra al sistema quiere saber qué tiene que hacer hoy.

| Orden | Bloque | Indicadores |
|---|---|---|
| 1 | Lo que exige acción | Contratos próximos a vencer, en tres cifras acumuladas: los que vencen en los próximos 30 días, en los próximos 60 y en los próximos 90. El de 60 incluye a los de 30, y el de 90 incluye a ambos. |
| 2 | Vacaciones | Días acumulados por toda la planta, que es un pasivo real de la empresa · Número de personas con acumulación excesiva · Días disfrutados en el periodo. |
| 3 | Planta | Número de empleados activos con su variación frente al mes anterior · Distribución por tipo de contrato. |
| 4 | Rotación | Índice de rotación del mes · Rotación voluntaria frente a involuntaria · Rotación temprana, entendida como salidas de personas con menos de seis meses de vinculación · Principales motivos de salida del periodo. |
| 5 | Ausentismo | Índice de ausentismo por causa médica · Días perdidos por incapacidad separando origen común de laboral · Número de incapacidades · Distribución por diagnóstico · Días perdidos por ausencias no justificadas. |
| 6 | Composición | Distribución por cargo, por género y por rango de edad · Antigüedad promedio de la planta y distribución por rangos de antigüedad. |
| 7 | Movimientos | Número de aumentos y promociones del mes. |

### 12.2 Indicadores descartados

Se evaluaron y se decidió no incluirlos:
- Tasa de retención a 12 meses.
- Duración promedio de las incapacidades.
- Ingresos del mes como indicador propio.
- Masa salarial total y su variación.
- Salario promedio por cargo.
- Porcentaje promedio de incremento salarial.
- Proporción de promociones internas sobre el total de movimientos.

## 13. Carga inicial de información

Este capítulo resuelve el problema más importante de la puesta en marcha. Las empresas que van a comprar el sistema llevan años operando: no se puede empezar con todo en cero.

### 13.1 Principios

- **La carga la ejecuta el proveedor, no el cliente.** El cliente diligencia las plantillas y las devuelve; el proveedor las procesa. Esto evita que se dañe la información y garantiza que el formato sea el correcto.
- **Es un proceso de una sola vez,** parte del onboarding. No es una funcionalidad permanente a la que el cliente tenga acceso.
- **El Excel solo se usa para cargar.** No se almacena ni se conserva: se procesan sus valores y se guardan en la base de datos.
- **No se inventan registros.** Si la empresa no tiene el histórico de vacaciones, no se crean periodos ficticios con fechas falsas. Se carga el saldo como dato inicial.
- El identificador que une toda la información entre plantillas es el número de documento.

### 13.2 Plantilla obligatoria: empleados actuales

Es la única plantilla que toda empresa debe entregar. Contiene todos los empleados activos hoy, con los campos del capítulo 5, más una columna adicional:

> **Días de vacaciones disponibles a la fecha de carga**
>
> Es el dato más importante de toda la carga inicial. Sin él, el sistema empezaría a acumular vacaciones desde cero y el saldo de una persona con diez años en la empresa quedaría radicalmente equivocado, que es justamente el error que el producto promete evitar.
>
> La empresa no necesita reconstruir los periodos tomados en el pasado: basta con decir cuántos días tiene disponibles hoy cada persona. Ese es el dato que las empresas sí tienen a la mano.
>
> A partir de ese saldo inicial, el sistema sigue acumulando 1,25 días por mes y descontando los periodos que se vayan registrando.

### 13.3 Plantillas opcionales: histórico

Cada empresa decide si las entrega. Hay empresas que no tienen ningún registro estructurado de estas novedades y arrancan solo con lo básico; eso es perfectamente válido.

| Plantilla | Contenido |
|---|---|
| Histórico de incapacidades | Incapacidades pasadas con sus fechas, origen, diagnóstico y EPS. |
| Histórico de vacaciones | Periodos de vacaciones ya disfrutados, con fechas reales. Solo si la empresa los tiene registrados. |
| Histórico de licencias, ausencias y suspensiones | Novedades pasadas con sus fechas. |
| Histórico de aumentos y promociones | Movimientos salariales y de cargo anteriores. |

- Las plantillas se le entregan al cliente antes de la reunión de carga, para que las diligencie con calma.
- El formato debe respetarse exactamente: mismas columnas, mismo orden, mismos valores permitidos.
- El sistema valida cada plantilla antes de cargarla y devuelve un reporte de errores por fila.

### 13.4 El escenario mínimo

Debe estar contemplado el caso de la empresa que dice: "no tengo nada de eso registrado". En ese escenario se carga únicamente:
1. La información básica de cada empleado.
2. El perfil sociodemográfico, si la empresa lo va a manejar.
3. Los días de vacaciones disponibles de cada persona.
Con eso el sistema queda operativo. Todo lo demás empieza a registrarse desde ese momento hacia adelante.

## 14. Alcance

### 14.1 Qué entra en la primera versión

| Componente | Estado |
|---|---|
| Cuenta empresa, superadministrador y administradores por módulo | Entra |
| Parametrización de la empresa | Entra |
| Módulo de Contratación con registro de empleado | Entra |
| Perfil sociodemográfico opcional | Entra |
| Renovaciones y cambio de modalidad contractual | Entra |
| Módulo de Novedades: incapacidades, vacaciones, licencias, ausencias y suspensiones | Entra |
| Módulo de Terminaciones con tipos y motivos | Entra |
| Módulo de Aumentos y promociones | Entra |
| Módulo de Auditoría | Entra |
| Motor de alertas, individuales y de resumen mensual | Entra |
| Calendario de festivos cargado por año | Entra |
| Exportación y filtros desde cada módulo | Entra |
| Dashboard con los indicadores del capítulo 12 | Entra |
| Carga inicial de información con plantillas | Entra |
| Ambiente de demostración con datos ficticios | Entra |

### 14.2 Qué queda expresamente fuera

| Componente | Decisión |
|---|---|
| Expediente documental virtual (carga de archivos) | Descartado. El sistema no almacena archivos de ninguna clase. |
| Enlace a carpeta de Google Drive del cliente | Descartado del MVP. Idea a explorar más adelante, condicionada a resolver el riesgo de que un cliente exponga públicamente su propia carpeta. |
| Contratos por prestación de servicios | Descartado. No son laborales. |
| Módulo de reportes centralizado | Fuera del MVP. La exportación por módulo cubre la necesidad. |
| Espacio de preguntas frecuentes para empleados | Descartado. |
| Módulo de llamados de atención | Fuera del MVP. Se revisará con los primeros clientes. |
| Asistente de inteligencia artificial | Fuera del MVP. |
| Sábado laboral configurable por empleado | Descartado. La parametrización es por empresa. |
| Listas de departamentos y ciudades de países distintos de Colombia | Descartado. Se digitan a mano. |

## Anexo A — Fundamento legal de las reglas

Cada regla automatizada del sistema tiene un respaldo normativo. Esta tabla es la referencia para quien construya y para quien deba defender el comportamiento del sistema ante un cliente.

| Regla del sistema | Fundamento |
|---|---|
| Acumulación de 1,25 días de vacaciones por mes | Artículo 186 del CST: 15 días hábiles por año cumplido de servicio. 15 ÷ 12 = 1,25. |
| Vacaciones en días hábiles; domingos y festivos no cuentan | Artículo 186 del CST. |
| Alerta de disfrute mínimo de seis días continuos al año | Artículo 190 del CST: el trabajador debe gozar anualmente de al menos seis días hábiles continuos, que no son acumulables. |
| Licencias, ausencias y suspensiones en días corridos | Artículos 51 y 53 del CST. La suspensión del contrato no se interrumpe porque en medio caiga un domingo o un festivo, y el empleador puede descontar el día de descanso remunerado porque el trabajador no laboró la semana completa. |
| Las suspensiones reducen la acumulación de vacaciones | Artículo 53 del CST: el tiempo de suspensión no se computa para vacaciones ni cesantías. |
| Periodo de prueba de dos meses en contrato indefinido | Artículo 78 del CST. |
| Periodo de prueba de una quinta parte en término fijo, máximo dos meses | Artículo 78 del CST. La regla de la quinta parte aplica a contratos inferiores a un año; en los de un año o más el máximo es dos meses. La reforma de 2025 no modificó este artículo. |
| Advertencia de periodo de prueba en reingresos | Artículo 78 del CST: en contratos sucesivos entre el mismo empleador y trabajador no es válido estipular periodo de prueba, salvo en el primero. La ley no fija un plazo mínimo entre contratos, por eso el sistema advierte pero no decide. |
| Preaviso de no renovación con 30 días | Artículo 46 del CST, modificado por el artículo 6 de la Ley 2466 de 2025. |
| Tope de cuatro años del contrato a término fijo | Artículo 46 del CST reformado. Al superarlo, el contrato muta a indefinido por disposición legal, sin necesidad de liquidar prestaciones. |
| Límite de prórrogas cortas en contratos inferiores a un año | Artículo 46 del CST reformado y Concepto MinTrabajo 6157 de 2026. Ver el detalle en el documento «Puntos Pendientes». |
| Prórroga automática por silencio | Artículo 46 del CST reformado: el contrato se entiende renovado por un término igual al inicialmente pactado o al de su última prórroga. |
| Expiración del plazo fijo como modo de terminación | Artículo 61, literal c, del CST. |
| Conversión de obra o labor a indefinido | Si terminada la obra el trabajador sigue prestando servicios sin nuevo acuerdo escrito, el contrato se entiende celebrado a término indefinido desde el inicio de la relación. |
| Registro del código de diagnóstico | Decreto 019 de 2012 (el trámite de incapacidades ante la EPS corresponde al empleador) y Decreto 1072 de 2015 con Resolución 0312 de 2019 (registro de ausentismo por causa médica y vigilancia epidemiológica). |
| Perfil sociodemográfico | Decreto 1072 de 2015: insumo del diagnóstico de condiciones de salud dentro del SG-SST. |
| Traslado de festivos al lunes | Ley 51 de 1983. Por eso la tabla de festivos se carga y no se calcula. |

*Fin de la Especificación Funcional — Versión 3.0*
