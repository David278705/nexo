# Qué es Nexo y cómo se usa

Nexo es un SaaS multiempresa de gestión humana para pymes colombianas. Centraliza la información del
personal, calcula vacaciones y periodos de prueba, y **avisa por correo antes de que se venza un plazo legal**.
Esa es la promesa que se vende: si las alertas fallan, el producto no sirve.

## Roles

| Rol | Quién | Qué hace |
|---|---|---|
| Owner (proveedor) | El equipo de Nexo. En la simulación, Angela con la cuenta del proveedor. | Crea las empresas, les carga la información inicial desde Excel, envía la invitación al cliente. Entra al **panel del proveedor**. Tiene segundo factor obligatorio. |
| Superadministrador | La cuenta de la empresa cliente. Una por empresa. En la simulación, Angela con su correo. | Acceso total a los módulos de su empresa. Crea usuarios administradores. Recibe todas las alertas. |
| Administrador | Usuarios que crea el superadministrador. | Solo los módulos que le asignaron. Recibe solo las alertas de esos módulos. |

Ningún usuario de una empresa puede ver datos de otra, por ningún camino.

## El panel del proveedor (cuenta owner)

| Sección | Para qué |
|---|---|
| Inicio | Estado del negocio y lo que requiere atención hoy. |
| Empresas | Listado de clientes; **Crear empresa** (asistente de tres pasos) y la **ficha** de cada empresa, con sus usuarios (ahí está el botón **Enviar invitación**), su bitácora y su uso. |
| Puesta en marcha | La carga inicial desde las plantillas de Excel: se elige la empresa y la plantilla, se **simula** (valida fila por fila sin escribir nada) y, si está limpia, se **carga** (entra entera o no entra). |
| Suscripciones | Planes, cobros, mora y suspensiones. |
| Alertas enviadas | Consola de todo lo que el sistema ha enviado, con su estado. |
| Catálogos | EPS, fondos, ARL, cajas, bancos, ciudades y festivos. |
| Salud del sistema | Procesos programados (si corrió el de las 6:00), errores y copias de seguridad. |
| Demostración | El ambiente de demostración comercial (no se usa en esta simulación). |

### Crear empresa: los tres pasos

1. **Datos y certificado**: razón social, NIT con dígito de verificación (único en todo el sistema), teléfono,
   ciudad y, opcional, el certificado de Cámara de Comercio en PDF.
2. **Parámetros**: ¿el sábado es día laboral? · ¿las licencias, ausencias y suspensiones afectan la acumulación
   de vacaciones? · ¿se habilita el perfil sociodemográfico?
3. **Plan y superadministrador**: nombre del plan, empleados incluidos, usuarios administradores, fecha de inicio,
   nombre y correo del superadministrador.

Al final muestra un resumen. **Crear la empresa no envía la invitación**: se envía desde la ficha, después de
cargar los datos, para que el cliente no entre a un sistema vacío. La invitación vence en 7 días.

Al entrar por primera vez, el superadministrador define su contraseña y **acepta los documentos legales**.
Hasta que no los acepte, nadie de su empresa puede trabajar. (Hoy los documentos legales están en borrador:
el sistema lo sabe y no es una falla.)

## El sistema del cliente (cuenta superadministrador)

Menú: **Inicio** (dashboard) · Personal: **Empleados**, **Contratos** · Novedades: **Incapacidades**,
**Vacaciones**, **Licencias, ausencias y suspensiones** · Cambios y salidas: **Movimientos**, **Terminaciones** ·
Sistema: **Alertas**, **Auditoría**, **Configuración**. En la barra lateral está el bloque «¿Necesita ayuda?»
(hoy muestra un correo de soporte provisional; eso está pendiente y no es una falla).

### Reglas comunes a todas las novedades

- Flujo idéntico en las tres novedades: botón **Nuevo** → una ventana pide solo el número de documento → si no es
  un empleado activo de la empresa dice **«Empleado no encontrado»** y no deja seguir → si existe, muestra un
  recuadro con nombre y cargo, y debajo el formulario.
- Se pueden registrar fechas pasadas, editar y eliminar cualquier registro. Al eliminar, los saldos se recalculan.
- Toda creación, edición y eliminación queda en **Auditoría** (fecha y hora, usuario, módulo, acción, registro,
  valor anterior y nuevo). La auditoría es de solo lectura, ni el superadministrador la borra.
- Cada módulo tiene su botón de exportar a Excel con lo que se ve en pantalla. Toda exportación de novedades trae
  documento, nombre completo y cargo.

### Tres reglas de conteo distintas

| Qué | Cómo se cuenta | Ejemplo |
|---|---|---|
| Vacaciones | **Días hábiles**: no cuentan domingos ni festivos; el sábado cuenta solo si la empresa lo tiene como laboral. | Lunes 7 a viernes 11 de diciembre de 2026, sin sábado laboral: 4 días (el 8 es festivo). |
| Incapacidades | **Días corridos**, incluido el primero y el último. | Del 14 al 16 de julio: 3 días. |
| Licencias, ausencias y suspensiones | **Días corridos**: el domingo y el festivo sí cuentan. | Jueves a martes siguiente: 6 días. |

### Empleados y contratos

- Al crear un empleado queda ACTIVO. Pasa a INACTIVO solo al registrar su terminación. Sobre un inactivo no se
  registran novedades ni salen alertas.
- Tipos de contrato: **Término indefinido** (solo fecha de ingreso; periodo de prueba de 2 meses) · **Término
  fijo** (ingreso y fin; prueba = quinta parte de la duración, máximo 2 meses) · **Obra o labor** (ingreso y,
  opcional, **fecha de finalización estimada**; si la tiene, se le sugiere un periodo de prueba con la regla del
  fijo y el sistema muestra una advertencia de que es una estimación; si no la tiene, no hay periodo de prueba).
- **Renovar contrato** (solo término fijo): nueva fecha de fin; queda en el historial de renovaciones. Es distinto
  de editar la fecha (que es corregir un error de digitación).
  - **Bloquea** si con la renovación el vínculo a término fijo supera **cuatro años** (contados desde el inicio o
    desde el 25 de junio de 2025, lo que sea más tarde). Ofrece **Cambiar a término indefinido**.
  - **Advierte pero deja guardar** si el contrato inicial es menor de un año, ya lleva tres prórrogas menores de un
    año y la nueva también es menor de un año.
- **Cambiar a término indefinido**: disponible en término fijo y en obra o labor. La fecha de ingreso no cambia.
- **Reingreso**: si se crea un empleado con un documento que ya tuvo una vinculación en la empresa, aparece la
  advertencia «Esta persona ya tuvo una vinculación anterior con la empresa. Verifique si es válido pactar
  periodo de prueba en este contrato.», con énfasis si la salida anterior fue hace menos de 30 días. Igual se
  generan las alertas de prueba.

### Campos de cada novedad

| Módulo | Campos |
|---|---|
| Incapacidades | Documento · fecha de inicio · fecha de terminación · días (calculado, corridos) · código de diagnóstico (dato sensible) · ¿es prórroga? · EPS que la expide · origen (Común / Laboral) |
| Vacaciones | Documento · día inicial · día final · días (calculado, hábiles). **Saldo insuficiente**: compara contra el saldo que tendrá la persona **el día que empiezan**, dice el número exacto («Al [fecha] esta persona tendrá X días disponibles. Está registrando Y días: faltan Z días.») y **no bloquea**. |
| Licencias, ausencias y suspensiones | Documento · tipo (Licencia no remunerada / Ausencia / Suspensión) · fecha inicial · fecha final · días (calculado, corridos). Si la empresa lo parametrizó, reducen proporcionalmente la acumulación de vacaciones de esos meses. |
| Movimientos | Documento · tipo (Aumento salarial / Promoción) · salario y cargo actuales (automáticos) · nuevo salario · nuevo cargo · fecha de efectividad. Al guardar se actualizan salario y cargo del empleado. |
| Terminaciones | Documento · fecha de salida · tipo de salida · motivo (lista que depende del tipo) · observaciones (opcional). El empleado pasa a inactivo. |

Tipos de salida y motivos: **Renuncia** (Mejor oferta laboral — salario · …— desarrollo profesional ·
Inconformidad con el salario o los beneficios · Clima laboral o relación con el jefe inmediato · Motivos personales
o familiares · Motivos de salud · Estudios · Cambio de ciudad o de país · Distancia o tiempo de desplazamiento ·
Emprendimiento propio · No informó el motivo) · **Terminación por mutuo acuerdo** · **Terminación en periodo de
prueba** (No cumple el perfil técnico del cargo · Desempeño insuficiente durante la inducción · Falta de
adaptación al equipo o a la cultura · Incumplimiento de horarios o asistencia · Otro) · **Despido con justa
causa** · **Terminación sin justa causa** · **Expiración del plazo fijo pactado** (sin submotivo) · **Terminación
de la obra o labor contratada** (motivo único autocompletado).

### Vacaciones: el saldo

Se acumulan **1,25 días por mes**. El saldo se calcula, no se guarda: saldo inicial cargado + acumulación desde
la fecha de carga − días disfrutados − (si aplica) el efecto de licencias y suspensiones. Se consulta por
empleado.

### Dashboard (Inicio)

En orden: lo que exige acción (contratos que vencen en 30, 60 y 90 días, acumulados) · vacaciones (días
acumulados de toda la planta, personas con acumulación excesiva) · planta (activos, por tipo de contrato) ·
rotación · ausentismo · composición · movimientos del mes.

## La carga inicial (Puesta en marcha)

- La ejecuta el proveedor, no el cliente. La plantilla obligatoria es la de **empleados actuales**; las de
  histórico son opcionales y se cargan **después** de la de empleados.
- El archivo se busca por los títulos de la fila 1. Fechas DD/MM/AAAA. Nombres de EPS, fondos, ARL, cajas y bancos
  exactos al catálogo.
- **Simular** reporta los errores fila por fila y no escribe nada. **Cargar** entra todo o nada.
- La columna más importante es **«Días de vacaciones disponibles hoy»**: es el saldo inicial.
