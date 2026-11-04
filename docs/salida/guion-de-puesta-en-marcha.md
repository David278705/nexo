# Guion de la puesta en marcha

> Paso 28 del plan de salida al mercado. Preparado el 2026-11-03 sobre el guion del documento 4, 12.2,
> y las pantallas reales del sistema. **Falta cronometrarlo**: la primera vez, se anota el tiempo real de
> cada bloque en la última columna. Ese número es el que confirma el precio de la implementación.

## Antes de la reunión

| Qué | Quién | Listo |
|---|---|---|
| Contrato y acuerdo de tratamiento listos para que el cliente los lea; los acepta al primer ingreso | Angela | ☐ |
| Certificado de Cámara de Comercio del cliente, en PDF | Angela | ☐ |
| **El correo transaccional funcionando** (paso 21 del plan): sin él no llega la invitación | David | ☐ |
| El ambiente de demostración revisado: Panel → Demostración | David | ☐ |
| Las seis plantillas de `plantillas/` y la hoja de listas desplegables, listas para enviar | David | ☐ |
| Decidir con el cliente quién será el superadministrador: nombre y correo | Angela | ☐ |

## La reunión: una hora

| Minutos | Qué se hace | Dónde, en el sistema | Tiempo real |
|---|---|---|---|
| 0 a 5 | Presentación. Se confirma quién llevará el sistema en la empresa | — | |
| 5 a 20 | Recorrido por la demostración. **Las alertas primero**: es lo que compra el cliente. Después la ficha de un empleado y el inicio | Ambiente de demostración | |
| 20 a 35 | Los parámetros, decididos en vivo: ¿trabajan sábados?, ¿las licencias y suspensiones descuentan vacaciones?, ¿quieren el perfil sociodemográfico? | Se anotan; se cargan en el paso siguiente | |
| 35 a 45 | Crear la empresa delante del cliente, en tres pasos: datos y certificado, parámetros, y plan con el superadministrador. **Leer el resumen en voz alta** y copiarlo para enviárselo | Panel → Empresas → Crear empresa | |
| 45 a 55 | Entregar las plantillas. Explicar que **la de empleados es la obligatoria** y que el saldo de vacaciones es el dato crítico. En obra o labor, pedir la fecha estimada de finalización si la tienen. Acordar la fecha de devolución | Correo, con los archivos de `plantillas/` | |
| 55 a 60 | Cómo pedir soporte, en cuánto se responde, y fecha del seguimiento a los 15 días | El bloque «¿Necesita ayuda?» de la barra lateral | |

> **Un problema que hay que resolver antes de la primera reunión.** El documento 4 dice que **no se le da
> acceso al cliente antes de cargar sus datos**: un sistema vacío da mala impresión. Pero hoy el sistema
> **envía la invitación al superadministrador en el mismo momento en que se crea la empresa**, en el minuto
> 35. Mientras David no decida (ver `ESTADO.md`), la salida práctica es avisarle al cliente en la reunión
> que le llegará un correo de invitación y que **lo use cuando le confirmemos que sus datos están
> cargados**. La invitación vence a los 7 días; si la carga tarda más, se reenvía desde la ficha de la
> empresa al terminar.

## Después de la reunión

| Orden | Qué | Dónde | Quién |
|---|---|---|---|
| 1 | Recibir las plantillas diligenciadas | Correo | Angela |
| 2 | **Simular** cada plantilla, empezando por empleados. Reporta los errores fila por fila y no escribe nada | Panel → Puesta en marcha, o `php artisan carga:simular` | David |
| 3 | Devolverle al cliente los errores que sean suyos, con el número de fila | Correo | Angela |
| 4 | **Cargar**, en el orden de la pantalla: primero empleados, después los históricos. Cada carga entra entera o no entra | Panel → Puesta en marcha | David |
| 5 | Revisar el resultado: empleados cargados y una ficha al azar contra el Excel | Panel → Empresas → la ficha | Ambos |
| 6 | Avisarle al cliente que ya puede entrar. Si la invitación venció, reenviarla | Ficha de la empresa → Usuarios | Angela |
| 7 | El superadministrador entra, define su contraseña y **acepta los documentos legales**. Hasta que no lo haga, nadie de su empresa puede trabajar | En su cuenta | El cliente |
| 8 | El superadministrador crea a los demás usuarios y les asigna módulos | Configuración → Usuarios y permisos | El cliente |
| 9 | **A la mañana siguiente, a las 6:00, salen las primeras alertas.** Comprobar que el proceso corrió y qué se envió | Panel → Alertas y Salud del sistema | David |
| 10 | Anotar en la bitácora del cliente lo prometido en la reunión | Ficha de la empresa → Bitácora | Angela |

## El seguimiento

- **A los 15 días:** ¿entra?, ¿le llegaron las alertas?, ¿qué no entendió? Las dudas que se repitan son
  el borrador de las preguntas frecuentes (documento 4, 13.2).
- **A los 45 días:** la ficha de la empresa → Uso muestra empleados cargados, novedades por mes y
  frecuencia de entrada. Es la conversación de renovación.

## Lo que se mide en cada puesta en marcha

Las horas totales —reunión, simulaciones, correcciones y carga— de cada cliente. Es el número que el
precio de la implementación (`docs/salida/propuesta-de-precios.md`) solo pudo estimar.
