# Sistema de Gestión de Talento Humano

SaaS multiempresa para gestión humana de PYMES colombianas. Centraliza información de personal,
calcula vacaciones y periodos de prueba, y **avisa por correo antes de que se venza un plazo legal**.
Esa última es la promesa que se vende: si las alertas fallan, el producto no sirve.

## Stack

Laravel 12 (PHP 8.3+) · Inertia 2 + Vue 3 + Tailwind · PostgreSQL 16+ · Pest + Vitest · Laravel Excel

**Un solo proyecto, no dos.** Toda la interfaz se escribe en Vue y todo el servidor en Laravel, pero
con Inertia **no hay API REST intermedia**: las rutas y los controladores siguen siendo de Laravel y,
en lugar de devolver una vista Blade, devuelven un componente Vue con sus props.

- Nada de `routes/api.php`, ni recursos JSON, ni Sanctum con tokens, ni `axios` para traer datos.
- Los datos llegan como props desde el controlador con `Inertia::render('Empleados/Index', [...])`.
- La navegación es con `<Link>` de Inertia, no con Vue Router.
- Los formularios usan el helper `useForm` de Inertia, que envía a una ruta normal de Laravel.
- La autorización y la validación viven en el servidor. Vue valida además, nunca en lugar de.
- Blade solo se usa para la plantilla raíz (`app.blade.php`) y para los correos.

## Cómo trabajas en este proyecto

1. Lee `ESTADO.md` para saber dónde quedó la sesión anterior.
2. Toma la **primera** tarea `[ ]` de `BACKLOG.md` en orden. No saltes tareas.
3. Impleméntala completa, incluidas sus pruebas.
4. Ejecuta `composer verificar`. No termines con la verificación en rojo.
5. Actualiza `BACKLOG.md` y `ESTADO.md`, y haz commit.

Una tarea por sesión. Si terminas antes de tiempo, **no empieces otra**: cierra bien y termina.

## Las cinco reglas que no se rompen

1. **Aislamiento entre empresas.** Toda tabla de negocio lleva `tenant_id`. El filtro lo pone el
   trait `PerteneceATenant`, nunca el código de cada consulta. Las políticas RLS de PostgreSQL están
   activas desde la primera migración. Un usuario jamás alcanza datos de otra empresa por ningún camino.
2. **El saldo de vacaciones se calcula, no se guarda.** Es `saldo_inicial + acumulación − disfrute − suspensiones`.
   No existe ninguna columna `saldo_vacaciones` que se sume y se reste.
3. **Tres reglas de conteo distintas y con nombres distintos.** Vacaciones en días hábiles,
   incapacidades en días corridos, suspensiones en días corridos. Nunca una función con un parámetro
   que se pueda pasar mal.
4. **Nunca borrado físico.** Todo es borrado lógico y toda acción queda en `auditoria`, escrita por
   un observador, no a mano.
5. **Persona y Vinculación son entidades separadas.** Una persona puede tener varias vinculaciones a
   lo largo del tiempo. El documento es único por empresa en `personas`, no en `vinculaciones`.

## Documentación

Está en `docs/`. Léela cuando la tarea lo pida; no la cargues entera sin motivo.

| Archivo | Cuándo consultarlo |
|---|---|
| `docs/01-especificacion-funcional.md` | Qué hace cada módulo, campos, reglas y alertas. Es la fuente de verdad funcional. |
| `docs/02-arquitectura-y-datos.md` | Modelo de datos, multiempresa, motor de cálculo, alertas, seguridad. |
| `docs/03-diseno-e-interfaz.md` | Sistema de diseño, navegación y comportamiento de cada pantalla. |
| `docs/04-panel-del-proveedor.md` | El panel interno del owner. |
| `docs/05-plan-de-construccion.md` | Las ocho fases y sus criterios de terminado. |
| `docs/06-decisiones-pendientes.md` | Lo que aún no está decidido. **Si tu tarea depende de algo de aquí, no lo inventes.** |
| `prototipos/sistema.html` | Cómo se ve el sistema del cliente. Ábrelo antes de construir una pantalla. |
| `prototipos/panel-proveedor.html` | Cómo se ve el panel interno. |

## Convenciones

- **Todo en español**: tablas, columnas, modelos, rutas, componentes, mensajes y comentarios.
  El dominio es laboral colombiano y traducirlo produce nombres que no ayudan a nadie.
- Tablas en plural y minúscula con guión bajo: `periodos_contractuales`. Modelos en singular: `PeriodoContractual`.
- Fechas laborales como `date`, sin hora, en zona horaria `America/Bogota`.
- Dinero en enteros o decimales de precisión fija. Nunca coma flotante.
- Enumerados guardados como texto legible, no como números.
- Componentes Vue en `resources/js/Pages/` y `resources/js/Components/`, en PascalCase.

## Si te bloqueas

No adivines reglas de negocio ni valores legales. Marca la tarea `[!]` en `BACKLOG.md`, escribe el
motivo y la pregunta concreta en `ESTADO.md` bajo «Preguntas para Angela», y **pasa a la siguiente tarea**.
Nunca dejes el repositorio a medias ni la verificación en rojo por seguir adelante.
