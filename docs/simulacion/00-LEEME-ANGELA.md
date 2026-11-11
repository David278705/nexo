# Simulación de Nexo con una empresa como si fuera real

Este paquete es para que pruebes Nexo de punta a punta durante unas tres o cuatro semanas, acompañada por una
sesión de Claude que ya sabe todo lo que hace el sistema: qué alertas tienen que llegar, qué día y a qué hora,
qué tiene que pasar cuando registras una novedad, y cómo revisar en Railway que todo esté funcionando.

## Antes de empezar (una sola vez)

1. **En tu cuenta de Claude** (claude.ai):
   - Crea un **Proyecto** llamado «Simulación Nexo» y sube como conocimiento del proyecto **todos** los archivos
     de esta carpeta: los `.md`, el `generar_escenario.py` y las cuatro plantillas de Excel (ver la lista abajo).
   - En Ajustes → Funciones, activa **ejecución de código y creación de archivos**. Sin eso Claude no puede
     generar los Excel ni el reporte.
   - En Ajustes → Conectores, conecta **Railway** (David te da acceso al proyecto `nexo`) y **Resend** (tu cuenta,
     la misma desde la que salen los correos del sistema).
   - Usa el modelo más capaz que tengas disponible.
2. **En tu celular**: instala una app de autenticación (Google Authenticator, Microsoft Authenticator o Authy).
   El panel del proveedor pide segundo factor la primera vez que entras.
3. **Los datos de entrada** te los da David por fuera de este paquete: la dirección del sistema, el correo de la
   cuenta del proveedor y su clave. No los escribas en el chat de Claude.

### Archivos que van al proyecto de Claude

| Archivo | Para qué |
|---|---|
| `00-LEEME-ANGELA.md` | Este archivo |
| `01-instrucciones-para-claude.md` | Cómo se tiene que comportar Claude durante toda la simulación |
| `02-que-es-nexo.md` | El sistema: roles, pantallas, módulos y campos |
| `03-motor-de-alertas.md` | Todas las alertas: cuándo salen, a quién, qué dicen y cuáles NO deben salir |
| `04-plan-de-la-simulacion.md` | El guion día por día, con las novedades y lo que se espera de cada una |
| `05-verificacion-tecnica.md` | Cómo revisar Railway y Resend, y cómo armar el reporte de fallas para David |
| `06-contexto-completo-del-sistema.md` | Todo el contexto para tus preguntas: en qué estado está Nexo, qué reglas sigue y con qué fundamento, qué está decidido y qué sigue abierto |
| `generar_escenario.py` | Genera los Excel y el calendario de alertas a partir del día en que empiezas |
| `1 - Empleados actuales (obligatoria).xlsx` | Plantilla original (está en la carpeta `plantillas/` del proyecto) |
| `3 - Historico de incapacidades (opcional).xlsx` | Plantilla original |
| `4 - Historico de vacaciones (opcional).xlsx` | Plantilla original |
| `6 - Historico de aumentos y promociones (opcional).xlsx` | Plantilla original |

## Cómo arrancar

Abre un chat nuevo dentro del proyecto y escribe:

> Hoy empiezo la simulación de Nexo. Hoy es el Día 1. Sigue el archivo 01-instrucciones-para-claude.md.

A partir de ahí Claude te guía. Cada día hábil, después de las 6:00 a. m., vuelves **al mismo chat** (o a uno
nuevo dentro del proyecto, diciéndole qué día de la prueba es) y le dices:

> Día N. Ya revisé el correo.

Claude te dice qué alertas tenían que llegar ese día, te pide confirmar si llegaron, te da las novedades del día
con todos sus campos, y al final revisa Railway y Resend. Si algo no cuadra, te entrega un archivo
`reporte-de-fallas-AAAA-MM-DD.md` para que se lo mandes a David.

En cualquier momento puedes preguntarle cosas como «¿en qué estado está el sistema?», «¿qué regla sigue para
las prórrogas y en qué norma se basa?» o «¿qué pasa con los datos cuando un cliente se va?». Responde con
`06-contexto-completo-del-sistema.md`, y si algo no está ahí, te lo dice en vez de inventarlo.

## Tres cosas que no hay que olvidar

- **Usa siempre tu correo** como superadministradora de la empresa que crees. Mientras el sistema está en
  pruebas, solo salen correos a la lista de correos permitidos y desde la cuenta de Resend que conectaste;
  a cualquier otra dirección no llega nada, y eso es normal.
- **El Día 1 debe ser un día hábil** (lunes a viernes, no festivo), y lo ideal es empezar antes del día 25
  del mes, para que el cierre de mes caiga dentro de la prueba: ese día llegan los resúmenes mensuales.
- **No toques nada en Railway** por tu cuenta, ni le pidas a Claude que cambie variables o reinicie servicios.
  La revisión es solo de lectura. Si algo hay que cambiar, va en el reporte para David.
