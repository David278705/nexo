# Andamiaje para Claude Code

Todo lo que Claude Code necesita para construir el sistema por sesiones, sin que tengas que
explicarle nada cada vez.

---

## Puesta en marcha, una sola vez

### 1. Crea el proyecto Laravel en blanco

```bash
laravel new gestion-talento-humano
cd gestion-talento-humano
git init && git add -A && git commit -m "Proyecto inicial"
```

Elige el starter kit de **Vue** cuando lo pregunte. Si ya tienes el proyecto creado, sáltate esto.

### 2. Copia el contenido de esta carpeta a la raíz del proyecto

Todo lo que está aquí dentro va **al mismo nivel** que `composer.json`:

```
gestion-talento-humano/
├── composer.json          ← ya existía
├── CLAUDE.md              ← de esta carpeta
├── ESTADO.md              ←
├── BACKLOG.md             ←
├── .claude/               ←
├── docs/                  ←
├── prototipos/            ←
├── plantillas/            ←
├── scripts/               ←
└── bitacora/              ←
```

### 3. Configura PostgreSQL

En `.env`, apunta a una base PostgreSQL vacía. Es obligatorio: el aislamiento entre empresas usa
Row Level Security y MySQL no lo tiene.

### 4. Commit

```bash
git add -A && git commit -m "Andamiaje de documentacion y reglas"
```

Listo. Ya no tienes que volver a tocar nada de esto.

---

## Cada vez que tengas créditos

**Windows:**

```powershell
.\scripts\vibe.ps1
```

**macOS o Linux:**

```bash
chmod +x scripts/vibe.sh    # solo la primera vez
./scripts/vibe.sh
```

Eso es todo. El script trabaja tarea por tarea hasta que se acaben los créditos, se acabe el backlog,
o lo detengas con Ctrl+C. Cuando vuelvas a tener créditos, lo ejecutas otra vez y **retoma exactamente
donde iba**.

### Opciones

```powershell
.\scripts\vibe.ps1 -Vueltas 10          # tope de 10 tareas en esta corrida
.\scripts\vibe.ps1 -SinLimitePermisos   # no pregunta nada, va más rápido
.\scripts\vibe.ps1 -MaxTurnos 400       # para tareas grandes que se quedan cortas
```

En macOS y Linux son variables de entorno: `VUELTAS=10 ./scripts/vibe.sh`

---

## Por qué esto no se atasca

Es la parte del diseño que importa.

**Cada tarea es una sesión nueva.** El script no mantiene una conversación larga que se va degradando.
Lanza Claude Code, le da una tarea, espera a que termine y lo vuelve a lanzar desde cero. El contexto
siempre está limpio.

**El estado vive en archivos, no en la conversación.** `BACKLOG.md` dice qué falta, `ESTADO.md` dice
dónde quedó todo y `bitacora/` guarda el detalle. Cualquier sesión nueva se sitúa leyendo dos archivos.

**Cada tarea trae su criterio de terminado.** No es «haz el módulo de vacaciones», es «hecho cuando
registrar y eliminar unas vacaciones deja el saldo exactamente como estaba». Se puede verificar.

**Hay tres frenos contra el atasco:**

- Si una tarea falla la verificación dos veces, se revierte, se marca `[!]` y se pasa a la siguiente.
- Si tres vueltas seguidas no completan ninguna tarea, el script se detiene y te avisa.
- `--max-turns` corta cualquier tarea que se vuelva un pozo sin fondo.

**Nunca inventa reglas de negocio.** Si algo no está en los documentos, marca la tarea bloqueada y
escribe la pregunta en `ESTADO.md`. Prefiere parar a inventar una regla laboral.

---

## Cómo se protege la calidad

Las instrucciones escritas son sugerencias que el modelo puede ignorar en un mal día. Lo que de verdad
protege este proyecto son tres cosas:

**1. `composer verificar`.** Formato, análisis estático, pruebas del servidor, compilación del
frontend y pruebas de componentes. Una tarea no se marca hecha con esto en rojo.

**2. La batería de aislamiento.** `tests/Feature/AislamientoTest.php` intenta leer datos de otra
empresa por todos los caminos posibles. Si alguno lo consigue, se pone roja. Es la única defensa real
contra el fallo que mata un SaaS.

**3. Los diez casos del motor de cálculo.** Salen de las sesiones con Angela: la incapacidad del 14 al
16 de julio, la licencia de jueves a martes con el domingo incluido, el saldo de 12,75 días. Se
escriben **antes** que el motor.

Para la interfaz, el freno es distinto: los prototipos HTML. Antes de construir una pantalla, Claude
abre `prototipos/sistema.html` y ve cómo debe verse. No la diseña sobre la marcha.

---

## Qué hay en cada carpeta

| Ruta | Qué es |
|---|---|
| `CLAUDE.md` | Se carga en **todas** las sesiones. Las cinco reglas que no se rompen y dónde está cada cosa. |
| `.claude/rules/` | Reglas que se cargan solas al tocar ciertos archivos: multiempresa, cálculo, interfaz, pruebas. |
| `.claude/skills/` | Procedimientos: `siguiente` (el del bucle), `crear-modulo`, `agregar-alerta`, `revisar`. |
| `.claude/settings.json` | Qué comandos puede ejecutar sin preguntar y cuáles tiene prohibidos. |
| `BACKLOG.md` | Las 58 tareas de las ocho fases, en orden, con criterio de terminado. |
| `ESTADO.md` | Dónde quedó la última sesión. Lo reescribe Claude al terminar cada tarea. |
| `docs/` | Los seis documentos del proyecto en Markdown. |
| `prototipos/` | Las pantallas en HTML. Se abren con doble clic. |
| `plantillas/` | Los seis Excel de carga inicial. |
| `bitacora/` | Un archivo por tarea y los registros del bucle. |

---

## Cosas que conviene saber

**Revisa la bitácora de vez en cuando.** No hace falta mirar cada tarea, pero cada diez o quince vale
la pena leer un par de resúmenes y abrir el sistema en el navegador. Si algo se está desviando,
mejor enterarse pronto.

**Las tareas bloqueadas son información, no fallos.** Cuando aparezca una `[!]`, mira la pregunta en
`ESTADO.md`, resuélvela con Angela, escribe la respuesta en el documento que corresponda y vuelve a
marcarla `[ ]`. La próxima corrida la retoma.

**Los puntos de control importan.** El backlog tiene cuatro tareas de revisión repartidas. No las
saltes: son las que evitan que la deuda se acumule sin que nadie la vea.

**Sobre `-SinLimitePermisos`.** Va más rápido porque no se detiene a pedir autorización, pero en ese
modo la lista de comandos prohibidos **no aplica**. Úsalo solo en un repositorio con git al día y sin
datos reales. Sin esa opción, el script funciona igual: solo pedirá confirmación para comandos que no
estén en la lista de permitidos.

**Si cambias algo de los documentos**, cámbialo en `docs/*.md`, que es lo que Claude lee. Los Word de
la carpeta de arriba son para Angela; los Markdown son para el agente.

---

## Cuando termine el backlog

Tendrás el MVP funcionando. Lo que falta para vender no es programación y está en
`docs/06-decisiones-pendientes.md`: constituir la sociedad, la revisión legal del contrato y del
acuerdo de datos, validar las reglas de contrato a término fijo con un asesor laboral, calcular el
costo por cliente y ponerle un nombre al producto.
