---
name: agregar-alerta
description: Agrega una alerta nueva al motor de avisos por correo, con su regla de disparo, su plantilla, su idempotencia y sus pruebas. Úsalo cuando la tarea sea construir o modificar una alerta de periodo de prueba, vencimiento de contrato, prórrogas, vacaciones acumuladas o cualquier aviso automático.
---

# Agregar una alerta

Las alertas son la promesa que se vende. Una alerta que no llega, que llega dos veces o que llega con
la fecha mal, destruye la confianza en el producto entero.

## Anatomía

Cada alerta tiene cuatro partes:

1. **Regla de disparo** en `app/Dominio/Alertas/Reglas/` — dice a quién y cuándo aplica.
2. **Plantilla de correo** en `resources/views/correos/` — hereda de la plantilla base.
3. **Clave de idempotencia** — `tipo + vinculacion_id + fecha_programada`, con índice único.
4. **Pruebas** — disparo, idempotencia, corrimiento y empleado inactivo.

## Reglas obligatorias

- **Solo si el empleado está activo.** Si hay terminación registrada, no se envía nada.
- **Corrimiento**: si la fecha cae en sábado, domingo o festivo, se envía el día hábil anterior y el
  texto se ajusta («mañana» pasa a «en dos días»).
- **La fecha exacta va siempre entre paréntesis**: «Mañana (14 de junio) es el último día del periodo
  de prueba». Quien recibe el correo no debe calcular nada.
- **Individual o resumen**: las de contrato y periodo de prueba se envían una por empleado. Las de
  vacaciones, contratos vencidos y obra o labor prolongada van agrupadas en un resumen mensual.
- **Idempotencia**: ejecutar el proceso dos veces el mismo día no puede producir un correo duplicado.
  Lo garantiza el índice único, no una comprobación en el código.
- Todo envío queda en `alertas_enviadas`, con destinatarios, estado, intentos y motivo del fallo.

## Cómo se dispara

El proceso diario recorre las empresas una por una, fijando el contexto en cada iteración. Nunca una
consulta global.

Cada día no busca solo las alertas de hoy: busca también las de los próximos días no hábiles, porque
hay que adelantarlas. Un viernes evalúa viernes, sábado, domingo y el lunes si es festivo.

## Pruebas

```
✓ se dispara en la fecha correcta
✓ no se dispara si el empleado está inactivo
✓ ejecutar el proceso dos veces no duplica el correo
✓ si cae en domingo se envía el viernes con el texto ajustado
✓ el cuerpo incluye la fecha entre paréntesis
✓ queda registrada en alertas_enviadas con sus destinatarios
```

## El catálogo completo

Las once alertas y sus textos están en `docs/01-especificacion-funcional.md` capítulo 9 y en
`docs/02-arquitectura-y-datos.md` sección 5.4. No inventes textos: úsalos.
