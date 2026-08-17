---
paths:
  - "app/Dominio/**/*.php"
  - "app/Models/Vinculacion.php"
  - "app/Models/Vacacion*.php"
  - "app/Models/Incapacidad*.php"
  - "app/Models/Suspension*.php"
  - "config/reglas_laborales.php"
---

# Motor de cálculo y reglas legales

Un error aquí no rompe nada visible: produce números equivocados en **todos los clientes a la vez**,
y el cliente puede haber liquidado a alguien con ese número. Es la parte más delicada del sistema.

## Un solo motor

Toda la lógica vive en `app/Dominio/Calculo/`. Ningún controlador, componente Vue ni modelo repite
un conteo de días. Si necesitas contar días, llamas al motor.

## Las tres reglas de conteo

Son **tres funciones con nombres distintos**, no una con un parámetro:

| Función | Regla | Se usa en |
|---|---|---|
| `diasHabilesVacaciones(inicio, fin, empresa)` | Excluye domingos y festivos. Excluye sábados solo si la empresa no los tiene laborales. | Vacaciones |
| `diasCorridos(inicio, fin)` | Todos los días, incluidos el inicial y el final. | Incapacidades, licencias, ausencias, suspensiones |
| `acumulacionMes(vinculacion, mes)` | 1,25 días, reducidos en proporción a los días de suspensión del mes si la empresa tiene ese parámetro activo. | Saldo de vacaciones |

Confundirlas es el error más probable del proyecto. Por eso tienen nombres largos y explícitos.

## El saldo de vacaciones

```
saldo(fecha) = saldo_inicial
             + acumulación desde fecha_corte_saldo hasta fecha
             − días de vacaciones disfrutados registrados
             − reducción por suspensiones, si la empresa lo tiene activo
```

No existe una columna `saldo` que se sume y se reste. Esto es lo que hace que corregir la fecha de
ingreso recalcule todo solo, que eliminar unas vacaciones restituya el saldo sin operación inversa,
y que la carga inicial funcione sin inventar periodos falsos.

Para el dashboard hay una tabla de caché refrescada por el proceso diario. **La caché nunca es la
fuente de verdad**: si hay diferencia, manda el cálculo.

## Las constantes legales van en configuración

`config/reglas_laborales.php`. Cada valor con un comentario que cite la norma y la fecha en que se
verificó. Nunca un número suelto dentro de la lógica.

Valores actuales: 1,25 días de vacaciones por mes (art. 186 CST) · 2 meses de periodo de prueba en
indefinido (art. 78) · una quinta parte en fijo inferior a un año, máximo 2 meses (art. 78) ·
30 días de preaviso (art. 46, mod. Ley 2466 de 2025) · tope de 4 años en término fijo (art. 46 reformado) ·
6 días hábiles continuos de disfrute mínimo anual (art. 190) · 4 prórrogas pactadas cortas y
3 automáticas antes del mínimo de un año (Concepto MinTrabajo 6157 de 2026).

## Festivos

Tabla `festivos`, cargada por año. **Nunca calculados con fórmulas**: Colombia traslada varios al
lunes siguiente y las excepciones rompen cualquier fórmula. Incluye los que caen en sábado, porque
afectan a las empresas con sábado laboral.

## Fechas

`date` sin hora, zona `America/Bogota`. El error clásico es un servidor en UTC que interpreta el
1 de agosto como 31 de julio a las 19:00.

## Pruebas primero

Antes de tocar el motor, escribe la prueba. Los diez casos obligatorios están en
`docs/02-arquitectura-y-datos.md`, sección 4.2. Si un caso nuevo aparece durante el trabajo,
se agrega a la batería, no se resuelve a mano.
