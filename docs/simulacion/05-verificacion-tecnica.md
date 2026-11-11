# Verificación técnica y reporte de fallas

Se hace al final de cada jornada, con los conectores de Railway y Resend. **Todo es de solo lectura.** Si no
tienes alguno de los conectores, dile a Angela cuál falta y sigue con lo que puedas revisar.

## La infraestructura

Proyecto de Railway **`nexo`**, entorno **`production`**. Cuatro servicios de una sola imagen:

| Servicio | Qué hace | Qué buscar en sus registros |
|---|---|---|
| `web` | La aplicación que usa Angela | Errores (severidad `error`), respuestas 500, y al arrancar la revisión de despliegue: líneas con ✔ y avisos con ! |
| `worker` | Envía los correos de la cola (`queue:work`, 3 intentos) | Las líneas del trabajo `App\Jobs\EnviarAlerta` cerca de las 6:00 de Bogotá (11:00 UTC): `RUNNING` y `DONE`. Un `FAIL` es una alerta que no salió |
| `scheduler` | Corre el proceso diario (`schedule:work`) | La ejecución de `alertas:diarias` a las 6:00 de Bogotá (11:00 UTC) cada día |
| `Postgres` | La base de datos | Solo si hay errores de conexión en los otros |

Avisos que **son normales** hoy y no van al reporte:
- «SENTRY_LARAVEL_DSN vacío».
- «Los certificados se guardan en el disco local…» (el servicio web tiene un volumen montado).
- «Documentos legales todavía en borrador…».
- El correo de soporte provisional `soporte@nexo.invalid`.
- «Correo no enviado: ningún destinatario está en MAIL_DESTINATARIOS_PERMITIDOS», si se creó un usuario con un
  correo que no está en la lista.

## La revisión de cada jornada

1. **¿Corrió el proceso de las 6:00?** Registros del `scheduler` alrededor de las 11:00 UTC del día. Si es un día
   no hábil, corre igual pero no envía nada.
2. **¿Salieron los correos?** Registros del `worker`: un `EnviarAlerta` por cada alerta esperada, todos `DONE`.
3. **¿Llegaron?** Con Resend, lista los correos enviados de hoy: uno por alerta esperada, al correo de Angela,
   con el asunto de `03-motor-de-alertas.md` y estado entregado (`delivered`). Compara la lista con el
   calendario ajustado: ni uno de más ni uno de menos. Un rebote o un estado de error es una falla.
4. **¿Hubo errores?** Registros del `web` y del `worker` del día con severidad error o warning que no sean de la
   lista de normales.
5. **¿Qué dice el sistema de sí mismo?** Pídele a Angela una captura de Panel → Salud del sistema (última
   ejecución del proceso diario, cuántas alertas, si hubo errores) y de Panel → Alertas enviadas (estado de cada
   alerta: enviada, pendiente o fallida).
6. **¿Coincide con lo que vio Angela?** Su bandeja, Resend, el worker y el panel tienen que contar la misma
   historia.

Si el proceso no corrió o ningún correo salió un día que debía salir alguno, es la falla más grave posible:
repórtala de inmediato, sin esperar al cierre del día.

## El reporte de fallas

Archivo `reporte-de-fallas-AAAA-MM-DD.md`, descargable, con este formato:

```markdown
# Reporte de fallas — Nexo — [fecha] (Jornada N)

**Escenario:** Día 1 = [fecha]. Empresa: Comercializadora Andina de Prueba S.A.S. (NIT 901555123-4).
**Gravedad máxima del día:** Crítica | Alta | Media | Baja

## Resumen
[Dos o tres líneas: qué falló y qué impacto tendría con un cliente real.]

## Fallas

### 1. [Título corto]
- **Gravedad:** Crítica (una alerta no llegó o llegó una que no debía) · Alta (cálculo o fecha equivocados) ·
  Media (error en pantalla, algo no funciona pero hay forma de hacerlo) · Baja (texto, presentación)
- **Dónde:** [pantalla, alerta o proceso]
- **Qué se esperaba:** [con la referencia: calendario, regla de 03-motor-de-alertas.md, etc.]
- **Qué pasó:** [exacto: textos, fechas, números]
- **Cómo reproducirlo:** [pasos, empleado (clave y documento), valores]
- **Evidencia:** [líneas de registro de Railway con hora UTC, ID del correo en Resend, captura de Angela]

## Mejoras sugeridas (no son fallas)
- [Lo que confundió a Angela o no es como se hace en la vida real]

## Lo que sí funcionó hoy
- [Lista corta, para que David sepa qué ya quedó probado]
```

No incluyas claves, ni el contenido de variables de Railway. Los datos de los empleados son ficticios y sí pueden ir.
