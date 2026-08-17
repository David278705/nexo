# Sistema de Gestión de Talento Humano

SaaS multiempresa para la gestión humana de PYMES colombianas. Centraliza la información del
personal, calcula vacaciones y periodos de prueba, y **avisa por correo antes de que se venza un
plazo legal**.

Esa última es la promesa que se vende. Un empleado que pasa de periodo de prueba sin que nadie lo
note, o un contrato a término fijo que se prorroga solo porque nadie avisó a tiempo, le cuesta
dinero real a la empresa. Si las alertas fallan, el producto no sirve.

---

## Qué resuelve

Las PYMES colombianas llevan esto en Excel. El Excel no avisa, no calcula bien los días hábiles y no
sobrevive a que se vaya la persona que lo mantenía.

| Módulo | Qué hace |
|---|---|
| Personas y vinculaciones | Historia laboral completa. Una persona puede entrar, salir y volver a entrar. |
| Periodos contractuales | Contratos, prórrogas, paso a término indefinido, con sus validaciones legales. |
| Novedades | Incapacidades, vacaciones, licencias, ausencias, suspensiones, aumentos, terminaciones. |
| Motor de cálculo | Saldo de vacaciones, periodo de prueba, antigüedad. Con tres reglas de conteo distintas. |
| Alertas | Correos automáticos antes del vencimiento, corridos al día hábil siguiente. |
| Reportería | Exportación a Excel por módulo y panel de inicio con lo que exige acción hoy. |
| Panel del proveedor | Alta de empresas, catálogos, suscripciones, salud del sistema. |

---

## Stack

Laravel 12 (PHP 8.3+) · Inertia 2 + Vue 3 + Tailwind · PostgreSQL 16+ · Pest + Vitest · Laravel Excel

**Un solo proyecto, no dos.** La interfaz se escribe en Vue y el servidor en Laravel, pero con
Inertia **no hay API REST intermedia**: las rutas y los controladores siguen siendo de Laravel y, en
lugar de devolver una vista Blade, devuelven un componente Vue con sus props.

- Nada de `routes/api.php`, ni recursos JSON, ni Sanctum con tokens, ni `axios` para traer datos.
- Los datos llegan como props desde el controlador con `Inertia::render('Empleados/Index', [...])`.
- La navegación es con `<Link>` de Inertia, no con Vue Router.
- Los formularios usan `useForm` de Inertia, que envía a una ruta normal de Laravel.
- La autorización y la validación viven en el servidor. Vue valida además, nunca en lugar de.
- Blade solo se usa para la plantilla raíz y para los correos.

**PostgreSQL es obligatorio, no una preferencia.** El aislamiento entre empresas se apoya en Row
Level Security, y MySQL no lo tiene.

---

## Las cinco reglas que no se rompen

1. **Aislamiento entre empresas.** Toda tabla de negocio lleva `tenant_id`. El filtro lo pone el
   trait `PerteneceATenant`, nunca el código de cada consulta. Las políticas RLS están activas desde
   la primera migración. Un usuario jamás alcanza datos de otra empresa por ningún camino.
2. **El saldo de vacaciones se calcula, no se guarda.** Es
   `saldo_inicial + acumulación − disfrute − suspensiones`. No existe una columna `saldo_vacaciones`
   que se sume y se reste.
3. **Tres reglas de conteo distintas y con nombres distintos.** Vacaciones en días hábiles,
   incapacidades en días corridos, suspensiones en días corridos. Nunca una función con un parámetro
   que se pueda pasar mal.
4. **Nunca borrado físico.** Todo es borrado lógico y toda acción queda en `auditoria`, escrita por
   un observador, no a mano.
5. **Persona y Vinculación son entidades separadas.** El documento es único por empresa en
   `personas`, no en `vinculaciones`.

---

## Convenciones

- **Todo en español**: tablas, columnas, modelos, rutas, componentes, mensajes y comentarios. El
  dominio es laboral colombiano y traducirlo produce nombres que no ayudan a nadie.
- Tablas en plural con guión bajo (`periodos_contractuales`); modelos en singular
  (`PeriodoContractual`).
- Fechas laborales como `date`, sin hora, en zona horaria `America/Bogota`.
- Dinero en enteros o decimales de precisión fija. Nunca coma flotante.
- Enumerados guardados como texto legible, no como números.
- Componentes Vue en `resources/js/Pages/` y `resources/js/Components/`, en PascalCase.

---

## Arranque local

Requiere PHP 8.3+, Composer, Node 20+ y una base PostgreSQL vacía.

```bash
composer install
cp .env.example .env
php artisan key:generate
# apunta .env a tu base PostgreSQL antes de seguir
php artisan migrate
npm install
```

Levantar el entorno de desarrollo:

```bash
composer dev     # servidor, cola, logs y Vite a la vez
```

O por separado, si prefieres controlar los puertos:

```bash
php artisan serve --port=8014
npm run dev
```

Verificación completa — formato, análisis estático, pruebas del servidor, compilación del frontend y
pruebas de componentes:

```bash
composer verificar
```

> El comando `verificar` se define en la tarea **F0-01**. Hasta que esa tarea esté hecha, el proyecto
> conserva el esqueleto de Laravel y solo existe `composer test`.

---

## Estructura del repositorio

| Ruta | Qué es |
|---|---|
| `CLAUDE.md` | Instrucciones que se cargan en toda sesión del agente. Las cinco reglas y dónde está cada cosa. |
| `BACKLOG.md` | Las 58 tareas de las ocho fases, en orden, cada una con su criterio de terminado. |
| `ESTADO.md` | Dónde quedó la última sesión. Se reescribe al cerrar cada tarea. |
| `docs/` | Los seis documentos del proyecto. Fuente de verdad funcional y técnica. |
| `prototipos/` | Las pantallas en HTML. Se abren con doble clic. |
| `plantillas/` | Los Excel de carga inicial. |
| `bitacora/` | Un resumen por tarea y los registros del bucle de desarrollo. |
| `scripts/` | `vibe.ps1` y `vibe.sh`, el bucle de construcción por sesiones. |
| `.claude/` | Reglas por contexto, skills de procedimiento y permisos del agente. |

### Documentación

| Archivo | Cuándo consultarlo |
|---|---|
| `docs/01-especificacion-funcional.md` | Qué hace cada módulo, campos, reglas y alertas. |
| `docs/02-arquitectura-y-datos.md` | Modelo de datos, multiempresa, motor de cálculo, alertas, seguridad. |
| `docs/03-diseno-e-interfaz.md` | Sistema de diseño, navegación y comportamiento de cada pantalla. |
| `docs/04-panel-del-proveedor.md` | El panel interno del owner. |
| `docs/05-plan-de-construccion.md` | Las ocho fases y sus criterios de terminado. |
| `docs/06-decisiones-pendientes.md` | Lo que aún no está decidido. Si una tarea depende de algo de aquí, no se inventa. |

---

## Cómo se construye

El proyecto se construye **una tarea por sesión**, con Claude Code, siguiendo `BACKLOG.md` en orden:

```powershell
.\scripts\vibe.ps1          # Windows
```

```bash
./scripts/vibe.sh           # macOS o Linux
```

Cada vuelta es una sesión nueva con contexto limpio. El estado vive en los archivos, no en la
conversación: `BACKLOG.md` dice qué falta y `ESTADO.md` dice dónde quedó todo, así que cualquier
sesión se sitúa leyendo dos archivos. Si se acaban los créditos, se vuelve a ejecutar y retoma
exactamente donde iba.

Hay tres frenos contra el atasco: una tarea que falla la verificación dos veces se revierte y se
marca `[!]`; tres vueltas seguidas sin completar nada detienen el bucle; y `--max-turns` corta
cualquier tarea que se vuelva un pozo sin fondo.

Una tarea bloqueada `[!]` no es un fallo, es información: la pregunta queda escrita en `ESTADO.md`
para resolverla y reintentarla después.

### Qué protege la calidad

Las instrucciones escritas son sugerencias que el modelo puede ignorar en un mal día. Lo que de
verdad protege este proyecto son cuatro cosas:

1. **`composer verificar`.** Ninguna tarea se marca hecha con la verificación en rojo.
2. **La batería de aislamiento** (`tests/Feature/AislamientoTest.php`) intenta alcanzar datos de otra
   empresa por todos los caminos posibles. Es la única defensa real contra el fallo que mata un SaaS.
3. **Los diez casos del motor de cálculo**, salidos de sesiones con la experta del dominio. Se
   escriben **antes** que el motor.
4. **Los prototipos HTML.** Antes de construir una pantalla se abre el prototipo. No se diseña sobre
   la marcha.

---

## Fases

| Fase | Contenido |
|---|---|
| 0 | Cimientos: multiempresa, RLS, aislamiento, usuarios, auditoría, sistema de diseño, catálogos. |
| 1 | Personas, vinculaciones y periodos contractuales, con sus pantallas. |
| 1B | Panel del proveedor, mínimo indispensable para dar de alta empresas. |
| 2 | Motor de cálculo y las novedades que lo alimentan. |
| 3 | Alertas: infraestructura, corrimiento al día hábil, entregabilidad. |
| 4 | Reportería, exportación y panel de inicio. |
| 5 | Carga inicial, ambiente de demostración y panel del proveedor completo. |
| 6 | Pulido: estados vacíos, textos, accesibilidad, pantallas pequeñas, operación. |

Al terminar el backlog queda el MVP funcionando. Lo que falta para vender no es programación y está
en `docs/06-decisiones-pendientes.md`.
#   n e x o  
 