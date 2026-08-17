---
name: siguiente
description: Toma la siguiente tarea pendiente del BACKLOG, la implementa completa, la verifica y deja el estado listo para la próxima sesión. Es el procedimiento que ejecuta el bucle automático de desarrollo. Úsalo cuando el usuario diga "siguiente", "continúa", "sigue con el proyecto" o cuando la sesión arranque sin instrucción concreta.
---

# Trabajar la siguiente tarea

Ejecutas **una sola tarea** y terminas. No encadenes varias aunque sobre tiempo.

## 1. Situarte

Lee, en este orden:

1. `ESTADO.md` — dónde quedó la sesión anterior y qué notas dejó.
2. `BACKLOG.md` — busca la **primera** tarea marcada `[ ]`. Esa es la tuya. No elijas otra.

Si no queda ninguna `[ ]`, escribe en `ESTADO.md` que el backlog está completo y termina.

Si la primera tarea `[ ]` depende de algo listado en `docs/06-decisiones-pendientes.md` que aún no
está resuelto, márcala `[!]`, anota la pregunta en `ESTADO.md` y toma la siguiente.

## 2. Entender antes de escribir

Lee **solo** los documentos que la tarea nombra en su campo «Fuente». No cargues los seis.
Si la tarea construye una pantalla, abre también el prototipo correspondiente.

Si algo de la tarea es ambiguo, no inventes: aplica el procedimiento de bloqueo del paso 6.

## 3. Implementar

Sigue el orden natural: migración → modelo → lógica de dominio → controlador → componente Vue → prueba.
Para el motor de cálculo, la prueba va **primero**.

Respeta las reglas de `.claude/rules/`. Se cargan solas cuando tocas los archivos correspondientes.

Reutiliza. Si vas a escribir por segunda vez el mismo bloque de tabla, filtros o modal, extráelo a
`resources/js/Components/`.

## 4. Verificar

Ejecuta `composer verificar`.

- **Verde** → sigue al paso 5.
- **Rojo** → arregla y vuelve a correr. Máximo **dos** intentos.
- Si tras el segundo intento sigue rojo: revierte los cambios que lo rompieron, deja el repositorio
  compilando, y ve al paso 6.

Nunca dejes el repositorio con la verificación en rojo.

## 5. Cerrar

1. Marca la tarea `[x]` en `BACKLOG.md`.
2. Reescribe `ESTADO.md` completo con la plantilla de abajo.
3. `git add -A && git commit -m "<id de tarea>: <descripción corta>"`.
4. Escribe un resumen de 5 líneas en `bitacora/AAAA-MM-DD-<id>.md`: qué hiciste, qué decidiste y qué
   conviene saber para la próxima.

## 6. Si te bloqueas

No adivines reglas de negocio, valores legales ni decisiones de producto.

1. Marca la tarea `[!]` en `BACKLOG.md` con el motivo en una línea.
2. Anota la pregunta concreta en `ESTADO.md`, bajo «Preguntas para Angela» o «Decisiones para David»
   según a quién corresponda.
3. Haz commit de lo que sí quedó funcionando.
4. **Termina la sesión.** No pases a otra tarea después de bloquear una.

Una tarea bloqueada no es un fracaso: es información. Lo que sí es un fracaso es inventar una regla
laboral o dejar el repositorio roto.

## Plantilla de ESTADO.md

```markdown
# Estado del proyecto

**Última sesión:** AAAA-MM-DD · Tarea <id>
**Verificación:** verde | rojo
**Siguiente tarea:** <id y título de la primera [ ] del backlog>

## Qué se hizo
- …

## Decisiones tomadas
- …

## Preguntas para Angela
- (vacío si no hay)

## Decisiones para David
- (vacío si no hay)

## Notas para la próxima sesión
- …
```
