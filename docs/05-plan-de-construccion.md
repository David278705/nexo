# Plan de Construcción del MVP — Sistema de Gestión de Talento Humano

> Plan de trabajo
> Documento de referencia del proyecto. Generado desde la fuente original; no editar a mano.

**PLAN DE TRABAJO**
**Plan de Construcción del MVP**
**Sistema de Gestión de Talento Humano**
*En qué orden se construye y cómo se sabe que una parte está terminada*
*Ocho fases · Cada una termina con algo que se le puede mostrar a Angela*
Documento 5 · Versión 1.0

## Cómo leer este plan

El orden no es arbitrario. Cada fase se apoya en la anterior y está diseñada para que lo más caro de cambiar quede resuelto primero, y para que en cualquier momento exista algo funcionando que se pueda enseñar.

| Principio | Qué significa |
|---|---|
| Lo irreversible primero | El modelo de datos y el aislamiento entre empresas van en la fase 0. Son lo único que no se puede corregir barato después. |
| Cada fase deja algo visible | Al final de cada fase hay una demostración concreta para Angela. Un proyecto de tres meses sin nada que mostrar en el camino es un proyecto que se abandona. |
| Las pruebas del motor de cálculo se escriben antes que el motor | Los casos ya están definidos en la Especificación. Escribirlos primero convierte una discusión en una verificación. |
| Nada de infraestructura propia hasta que haya producto | La decisión de servidor propio frente a plataforma administrada se toma cuando el sistema exista, no antes. |

> **Sobre los tiempos**
>
> Las duraciones están en semanas de una persona trabajando a tiempo completo. Son estimaciones de orden de magnitud, no compromisos.
>
> Si el desarrollo es de medio tiempo, multiplique por dos y sume un poco: los proyectos de medio tiempo pierden tiempo adicional en volver a coger el hilo.
>
> La suma de las ocho fases da entre once y trece semanas a tiempo completo. Eso es el MVP funcionando, no el producto vendido: faltarían las tareas del documento de Puntos Pendientes.

## Contenido

<!-- tabla de contenido generada por el editor -->

## Resumen de fases

| Fase | Qué se construye | Duración | Qué se le muestra a Angela al final |
|---|---|---|---|
| 0 | Cimientos: proyecto, base de datos, aislamiento entre empresas, autenticación, auditoría y estructura visual. | 1,5 sem | Entrar al sistema con usuario y contraseña, ver el menú y la pantalla vacía. Poco vistoso, pero es la mitad del riesgo del proyecto resuelto. |
| 1 | Personas, vinculaciones y contratos, con renovaciones y cambio de modalidad. | 2 sem | Registrar un empleado completo, ver su ficha, renovar su contrato y ver el historial de prórrogas. |
| 1B | Panel del proveedor, versión mínima: crear empresa, listado, ficha, parámetros, catálogos y festivos. | 1 sem | Crear una empresa cliente en cinco minutos, sin tocar la base de datos. |
| 2 | Motor de cálculo y los cuatro módulos de novedades y movimientos. | 2 sem | Registrar una incapacidad, unas vacaciones con la advertencia de saldo, una licencia y una terminación. |
| 3 | Motor de alertas y correos. | 1 sem | Recibir en el correo real las alertas de periodo de prueba y el resumen mensual. |
| 4 | Exportaciones, filtros y dashboard. | 1,5 sem | El panel de inicio completo y la descarga de un Excel con filtros aplicados. |
| 5 | Carga inicial, ambiente de demostración y el resto del panel del proveedor. | 2 sem | Cargar una empresa entera desde las plantillas de Excel, y ver el estado del negocio en el panel. |
| 6 | Pulido, textos, comportamiento en móvil y pruebas finales. | 1 sem | El sistema listo para ponerlo delante de un cliente. |

## Fase 0 — Cimientos

**Duración estimada:** 1,5 semanas · **Es la fase más importante y la que menos se ve.**

### Qué se construye

1. Proyecto creado, repositorio con control de versiones y entorno local que se levanta con un solo comando.
2. Base de datos con el modelo de datos completo del Documento 3, incluidas todas las tablas aunque los módulos aún no existan.
3. Columna de empresa en todas las tablas de negocio, con el trait que aplica el filtro automáticamente.
4. Políticas de Row Level Security activadas en PostgreSQL desde la primera migración.
5. Autenticación: iniciar sesión, cerrar sesión, recuperar contraseña, aceptar invitación.
6. Roles y permisos por módulo, con verificación en el servidor.
7. Registro de auditoría, implementado como observador sobre los modelos.
8. Estructura visual: barra lateral, encabezado, y los componentes base del sistema de diseño.
9. Catálogos cargados: países, departamentos y ciudades de Colombia, y el calendario de festivos del año.

### Cómo se sabe que está terminada

- Un usuario entra, ve el menú con los módulos a los que tiene acceso y no ve los demás.
- Existe una batería de pruebas de aislamiento que intenta leer datos de otra empresa por al menos cinco caminos distintos y falla en todos.
- Crear un registro cualquiera deja una línea en la auditoría sin que nadie la haya escrito a mano.
- Un desarrollador que escriba una consulta olvidando el filtro de empresa no obtiene datos ajenos.

> **La tentación de esta fase**
>
> Saltarse el aislamiento y la auditoría porque "todavía no hay clientes" es el error más caro posible. Ambas cosas cuestan dos días ahora y semanas de auditoría completa después.
>
> La otra tentación es empezar por la pantalla bonita. Una pantalla bonita sobre un modelo de datos equivocado hay que rehacerla entera.

### Lo que NO se hace en esta fase

- Nada de lógica de negocio.
- Nada de decidir la infraestructura definitiva. Basta un despliegue simple para probar.

## Fase 1 — Personas y contratos

**Duración estimada:** 2 semanas

### Qué se construye

1. Listado de empleados con filtros, búsqueda y paginación.
2. Asistente de cuatro pasos para registrar un empleado, con la lógica de país, departamento y ciudad.
3. Detección de reingreso y la advertencia correspondiente.
4. Bloque de perfil sociodemográfico, condicionado al parámetro de la empresa.
5. Ficha del empleado con sus pestañas.
6. Edición de empleado, con la advertencia sobre la fecha de ingreso.
7. Listado de contratos con la vista por defecto de vencimientos.
8. Detalle de contrato con el historial de periodos.
9. Acción de renovar, con el cálculo de duración acumulada y las validaciones de tope legal y prórroga mínima.
10. Acción de cambiar a término indefinido.
11. Pantalla de parámetros de la empresa.

### Cómo se sabe que está terminada

- Se puede registrar un empleado de principio a fin sin ayuda de nadie.
- Registrar un empleado con un documento que ya existió dispara la advertencia de reingreso.
- El historial de un contrato prorrogado tres veces muestra cuatro filas, empezando por el periodo inicial.
- Intentar registrar una prórroga que supere los cuatro años muestra el error y no deja guardar.
- Cambiar a indefinido no altera la fecha de ingreso ni la antigüedad.

### Demostración a Angela

Es la primera vez que se puede enseñar algo reconocible. Vale la pena hacerla con calma y tomar nota de todo lo que Angela encuentre incómodo: en esta fase todavía es barato cambiarlo.

## Fase 1B — Panel del proveedor, versión mínima

**Duración estimada:** 1 semana · **Es la fase que faltaba en la versión anterior de este plan.**

### Por qué va aquí y no al final

Hasta que exista la pantalla de crear empresa, cada prueba con datos realistas exige insertar filas a mano en la base de datos. Es lento, se presta a errores y, sobre todo, impide hacer una demostración con un cliente potencial. Una semana aquí ahorra semanas de fricción después.

### Qué se construye

1. Aplicación del panel, separada de la del cliente, con su propia dirección y su propio acceso.
2. Crear empresa, en los tres pasos descritos en el Documento 6, con el resumen final de configuración.
3. Listado de empresas con los indicadores de salud y de pago.
4. Ficha de la empresa: resumen, parámetros editables por el owner y gestión de sus usuarios.
5. Catálogos: EPS, fondos, ARL, cajas, bancos y ciudades, administrables sin tocar código.
6. Calendario de festivos por año, con el aviso cuando falte el año siguiente.
7. Constantes legales y umbrales configurables, con su norma de respaldo.
8. Segundo factor de autenticación obligatorio para las cuentas del equipo.

### Cómo se sabe que está terminada

- Se crea una empresa cliente completa en menos de cinco minutos, sin abrir la base de datos.
- Cambiar un parámetro de una empresa desde el panel se refleja de inmediato en sus cálculos.
- Agregar una EPS nueva no requiere desplegar código.
- Un usuario del equipo no puede entrar sin segundo factor.

> **Lo que NO entra en esta fase**
>
> Suscripciones, cobros, consola de alertas, métricas de negocio y salud del sistema quedan para la fase 5. Con cero clientes no hacen falta y sí consumen tiempo.
>
> Lo único que se necesita ahora es poder crear y configurar empresas.

## Fase 2 — Motor de cálculo y novedades

**Duración estimada:** 2 semanas · **Es el corazón del producto.**

### Orden dentro de la fase

**Primero las pruebas, después el motor, después las pantallas.** Los diez casos de prueba están escritos en la Especificación Funcional y en el Documento 3; escribirlos antes convierte cada regla en algo verificable en lugar de algo opinable.
1. Escribir las pruebas de los diez casos definidos. Todas fallan, que es lo esperado.
2. Implementar el motor de cálculo: las tres funciones de conteo y el cálculo de saldo. Las pruebas pasan.
3. Módulo de incapacidades, con el flujo de modal de documento y ficha del empleado.
4. Módulo de vacaciones, con la proyección de saldo a la fecha de disfrute y la advertencia con el número exacto de días faltantes.
5. Módulo de licencias, ausencias y suspensiones, con su efecto sobre la acumulación.
6. Módulo de aumentos y promociones.
7. Módulo de terminaciones, con los motivos dependientes del tipo de salida.

### Cómo se sabe que está terminada

- Los diez casos de prueba pasan.
- El saldo de vacaciones de un empleado cambia correctamente al registrar, editar y eliminar novedades.
- Cambiar la fecha de ingreso recalcula el saldo sin dejar inconsistencias.
- Registrar una novedad sobre un documento inexistente muestra el mensaje correcto y no deja continuar.
- Registrar una terminación pasa al empleado a inactivo y bloquea el registro de novedades sobre él.

> **El riesgo de esta fase**
>
> Es donde aparecen las preguntas de negocio que nadie previó. Conviene llevar una lista y consultarlas todas juntas con Angela en lugar de decidir sobre la marcha.
>
> La respuesta por defecto ante una duda de cálculo es no adivinar: dejar el caso documentado y preguntar. Un cálculo mal decidido se replica en todos los clientes.

## Fase 3 — Alertas

**Duración estimada:** 1 semana

### Qué se construye

1. Configuración del correo transaccional con dominio propio, SPF, DKIM y DMARC.
2. Plantilla base de correo y las plantillas de cada alerta, con la fecha exacta entre paréntesis.
3. Proceso diario que recorre las empresas y evalúa las reglas.
4. Clave de idempotencia con índice único y registro de envíos.
5. Corrimiento al día hábil anterior, con ajuste del texto.
6. Las once alertas de la Especificación: seis individuales y cuatro de resumen mensual, más la de obra o labor prolongado.
7. Panel de alertas dentro del sistema como canal alterno.

### Cómo se sabe que está terminada

- Un correo de prueba llega a la bandeja de entrada de Gmail y de Outlook, no a spam.
- Ejecutar el proceso dos veces el mismo día no produce correos duplicados.
- Una alerta programada para un domingo llega el viernes con el texto ajustado.
- Un empleado inactivo no genera ninguna alerta.
- Existe una forma de ver, para cualquier alerta, a quién se envió y cuándo.

### Demostración a Angela

Recibir el correo real en su bandeja es el momento en que el producto deja de ser una promesa. Vale la pena preparar datos que disparen varias alertas a la vez.

## Fase 4 — Reportería y dashboard

**Duración estimada:** 1,5 semanas

### Qué se construye

1. Exportación a Excel desde cada módulo, respetando los filtros activos.
2. Columnas de documento, nombre y cargo en todas las exportaciones.
3. Los filtros específicos de cada módulo definidos en la Especificación.
4. Tabla de caché de saldos de vacaciones, refrescada por el proceso diario.
5. Panel de inicio completo, con el bloque de acción arriba y los indicadores debajo.
6. Enlaces desde cada tarjeta de acción al listado filtrado correspondiente.

### Cómo se sabe que está terminada

- Aplicar tres filtros y exportar produce un Excel con exactamente esos registros y esas columnas.
- El panel de inicio carga en menos de dos segundos con una empresa de doscientos empleados.
- Pulsar la tarjeta de contratos por vencer lleva al listado ya filtrado.
- Volver a una tabla desde una ficha conserva los filtros y la página.

> **Alcance de esta fase**
>
> El catálogo de reportes y los indicadores se irán construyendo con el uso real; es una decisión ya tomada. Para el MVP basta con la exportación por módulo y los indicadores del panel de inicio.
>
> El módulo de reportes centralizado quedó fuera del MVP. No hay que construirlo ahora.

## Fase 5 — Carga inicial, demostración y panel completo

**Duración estimada:** 2 semanas

### Qué se construye

1. Lector de las seis plantillas de Excel, con validación fila por fila.
2. Modo simulación: valida sin escribir y devuelve el informe de errores con número de fila y motivo.
3. Carga dentro de una transacción: o entra todo o no entra nada.
4. Control de dependencias: no se puede cargar un histórico sin haber cargado empleados.
5. Registro de la carga realizada.
6. Empresa de demostración con datos ficticios y proceso que la restaura cada noche.
7. Resto del panel del proveedor: suscripciones y cobros, consola de alertas enviadas, salud del sistema, métricas de negocio, bitácora de cliente y acceso registrado al entorno del cliente.

### Cómo se sabe que está terminada

- Una plantilla con treinta empleados y tres errores deliberados produce un informe que señala las tres filas correctas.
- Corregir el archivo y volver a cargar funciona sin dejar registros a medias de la carga anterior.
- Después de la carga, el saldo de vacaciones de cada persona coincide con el informado en la plantilla más lo acumulado desde la fecha de corte.
- El entorno de demostración se restaura solo y siempre luce igual de poblado.
- Se puede responder en menos de un minuto a la pregunta "¿a quién y cuándo se envió esta alerta?".
- Entrar al entorno de un cliente deja constancia visible para él en su propia auditoría.

## Fase 6 — Pulido

**Duración estimada:** 1 semana · **Es la fase que separa un producto de un prototipo.**

### Qué se hace

1. Estados vacíos con su texto propio en las siete pantallas de listado.
2. Revisión de todos los mensajes de error y confirmación contra los textos definidos en el Documento 4.
3. Confirmaciones de acciones destructivas con la consecuencia concreta calculada.
4. Indicadores de carga en toda acción que tarde.
5. Comportamiento en pantallas pequeñas: menú colapsable, tablas en formato de tarjeta, modales a pantalla completa.
6. Revisión de accesibilidad: contraste, recorrido con teclado, etiquetas de campo.
7. Monitoreo de errores en producción.
8. Copias de seguridad automáticas y una restauración probada de principio a fin.

### Cómo se sabe que está terminada

- Una persona que nunca ha visto el sistema registra una incapacidad sin ayuda.
- Ninguna pantalla muestra un mensaje escrito para programadores.
- El sistema se puede recorrer entero con el teclado.
- Existe una copia de seguridad que se restauró y funcionó.

> **La prueba de fuego**
>
> Sentar a alguien que no sea Angela ni David —alguien que trabaje en administración en una empresa pequeña— y pedirle que registre un empleado y una incapacidad, sin explicarle nada y sin intervenir.
>
> Todo lo que pregunte, todo lo que dude y todo donde se detenga es una lista de tareas. Media hora de esa prueba vale más que una semana de opiniones internas.

## Riesgos del cronograma

| Riesgo | Señal temprana | Qué hacer |
|---|---|---|
| La fase 2 se alarga porque aparecen preguntas de negocio | Más de tres dudas sin resolver en la misma semana | Reunión con Angela para resolverlas todas juntas. No decidir sobre la marcha ni bloquear el avance. |
| Las alertas terminan en spam | El correo de prueba llega a spam en Gmail | Resolver antes de seguir. Es la promesa central del producto y no admite un arreglo posterior. |
| El alcance crece | Ideas nuevas que se empiezan a construir sin pasar por el documento de Puntos Pendientes | Toda idea nueva va al bloque E de ese documento. Ninguna entra al MVP. |
| El pulido se recorta por falta de tiempo | La fase 6 se comprime a dos días | Es preferible recortar indicadores del dashboard antes que recortar el pulido. Lo primero no se nota; lo segundo es lo que hace que el producto parezca improvisado. |
| Los listados maestros no llegan | La fase 1 avanza con listas de EPS incompletas | No bloquea: se construye con la tabla vacía y se carga después. Pero conviene pedirlos en la fase 0. |

## Después del MVP

Terminada la fase 6 existe un sistema que funciona y un panel con el que operarlo. Falta lo que está en el documento de Puntos Pendientes y que no es trabajo de programación:
- Constituir la sociedad y habilitar la facturación electrónica.
- Revisar con abogado el contrato de servicio y el acuerdo de tratamiento de datos.
- Validar con un asesor laboral las reglas de contrato a término fijo antes de que un cliente las use de verdad.
- Calcular el costo por cliente y definir la tabla de precios.
- Elegir la infraestructura definitiva y la pasarela de pagos.
- Documentar y cronometrar el guion de la reunión de onboarding.

**Ninguna de esas tareas bloquea la construcción** , que es exactamente lo que se decidió en la última sesión de revisión. Pero todas bloquean la primera venta, y conviene irlas resolviendo en paralelo a las fases 4, 5 y 6, cuando el desarrollo ya no tiene incertidumbre.

*Fin del Plan de Construcción — Versión 1.0*
