# Opciones de SLA

> Paso 3 del plan de salida al mercado. Preparado el 2026-11-03 para que David y Angela decidan **antes**
> de que el abogado redacte el contrato. La decisión es de ellos; aquí van las opciones y una
> recomendación.

## Qué hay que decidir

Un SLA tiene dos partes, y las dos van en el contrato (documento 6, A.3):

1. **Cuánto tiempo funciona el sistema** al mes, en porcentaje.
2. **En cuánto tiempo se responde** cuando algo falla, según la gravedad.

Y una tercera que casi siempre se olvida: **qué pasa si se incumple**. Sin consecuencia, no es un
compromiso exigible.

## 1. Disponibilidad mensual

| Opción | Caída máxima al mes | Para quién tiene sentido |
|---|---|---|
| **99 %** | 7 horas y 18 minutos | Dos personas, un solo servidor, sin guardias nocturnas |
| 99,5 % | 3 horas y 39 minutos | Un equipo con alguien disponible fuera de horario |
| 99,9 % | 43 minutos | Infraestructura redundante y monitoreo con guardia |

**Recomendación: 99 %.** El documento 6 lo dice: es mejor prometer 99 % y cumplirlo que prometer 99,9 %
y fallar. Railway es un solo proveedor y un solo servidor; no hemos podido confirmar que el plan que se
va a contratar traiga un compromiso de disponibilidad por escrito. Prometer más de lo que el proveedor de
infraestructura promete es comprometerse con algo que no se controla.

### Lo que no cuenta como caída

Estas exclusiones son las habituales y conviene que el contrato las enumere:

- Mantenimiento programado, **avisado con al menos 48 horas** y fuera del horario laboral.
- Fallas de servicios de terceros que no controla el proveedor: el proveedor de correo, la red del
  cliente, su navegador.
- Fuerza mayor.
- Lo causado por el uso indebido del cliente.

### Cómo se mide

**Esto hoy no existe y hace falta**: sin una medición independiente no se puede demostrar que se
cumplió. Un monitor externo que consulte `/up` cada pocos minutos y guarde el historial —UptimeRobot,
Better Stack o similar, con plan gratuito— basta. Lo configura David al desplegar.

## 2. Tiempos de respuesta

El documento 4, 13.1, ya los define, y son conservadores a propósito para dos personas con otros
trabajos. **Recomendación: adoptarlos tal cual.**

| Gravedad | Qué es | Respuesta | Solución |
|---|---|---|---|
| Crítica | El sistema no está disponible, o los datos se ven mal para todos | Mismo día hábil | Lo antes posible, con avisos de avance |
| Alta | Una función central no funciona: no se pueden registrar novedades, no llegan las alertas | Un día hábil | Tres días hábiles |
| Media | Algo funciona mal pero se puede seguir trabajando | Dos días hábiles | Siguiente actualización |
| Baja | Duda de uso, sugerencia o detalle cosmético | Tres días hábiles | Sin compromiso de fecha |

> **Corrección al plan.** La primera versión de `docs/09-plan-de-salida-al-mercado.md` proponía «cuatro horas si el
> sistema está caído para todos». El documento 4 dice «mismo día hábil», que es lo que se puede cumplir.
> Manda el documento 4.

El **horario de atención** tiene que ser el mismo en tres sitios: el contrato, la variable
`SOPORTE_HORARIO` que ve el cliente en la barra lateral, y la práctica real. Hoy la variable trae
«Lunes a viernes, de 8:00 a. m. a 5:00 p. m.».

## 3. Las alertas: un compromiso propio

El SLA genérico no cubre lo que el cliente de verdad compra, que es el aviso a tiempo. **Recomendación:
agregar un compromiso específico**, que el sistema ya puede cumplir:

> Si el proceso diario de alertas falla, el proveedor lo repone **el mismo día hábil** y avisa a los
> clientes afectados si algún vencimiento quedó a menos de cinco días.

El plan de incidentes ya describe cómo reponer un día sin duplicar correos
(`docs/07-plan-de-incidentes.md`, sección 1).

## 4. La consecuencia del incumplimiento

| Disponibilidad del mes | Descuento sobre la factura de ese mes |
|---|---|
| Entre 98 % y 99 % | 10 % |
| Entre 95 % y 98 % | 25 % |
| Menos de 95 % | 50 % |

Reglas habituales que conviene que el contrato diga:

- El descuento lo **solicita el cliente** dentro de los 30 días siguientes al mes afectado.
- Se aplica a la factura siguiente; **no se paga en efectivo**.
- Es la **única compensación** por la indisponibilidad, sin perjuicio del límite de responsabilidad.

## Resumen de la recomendación

99 % de disponibilidad, los tiempos de respuesta del documento 4, el compromiso propio sobre las alertas,
y descuentos de 10 %, 25 % y 50 %. Con eso, la decisión que les queda a David y Angela es confirmar o
cambiar estos números, y pasárselos al abogado junto con `docs/salida/encargo-al-abogado.md`.
