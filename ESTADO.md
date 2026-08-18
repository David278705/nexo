# Estado del proyecto

**Última sesión:** 2026-08-18 · Tarea F0-08
**Verificación:** verde
**Siguiente tarea:** F0-09 · Sistema de diseño

## Qué se hizo

- **Tabla `auditoria`** (`2026_08_18_100000_create_auditoria_table.php`): empresa y usuario nulables,
  clase y clave del registro tocado, acción, `valores_anteriores` y `valores_nuevos` en JSON, IP,
  agente y solo `created_at`. Índices por `tenant_id, created_at` (la pantalla del cliente) y por
  `modelo, registro_id` (el historial de una ficha).
- **`App\Auditoria\ObservadorDeAuditoria`**: se engancha con **comodín** a los eventos de Eloquent
  (`eloquent.created: *`, `updated`, `deleted`, `restored`) desde `AppServiceProvider::boot()`, así que
  **audita todo modelo que exista o llegue a existir**, sin una línea por módulo que se pueda olvidar.
- **`App\Auditoria\AccionAuditada`**: enumerado de texto legible —`creado`, `editado`, `eliminado`,
  `restaurado`— con su etiqueta.
- **`App\Models\Auditoria`**: inmutable desde `booted()`; `updating` y `deleting` lanzan
  `AuditoriaInmutable`. Scope `deLaEmpresaActual()` como único camino de lectura del lado del cliente.
- **`tests/Feature/AuditoriaTest.php`** (12 casos) y el modelo de apoyo `Tests\Fixtures\RegistroAuditable`,
  que sí tiene borrado lógico. Ninguna prueba llama a `Auditoria::create()`: si aparece una línea, la
  escribió el observador. `composer verificar` en verde: 100 pruebas de Pest, PHPStan sin errores,
  build y Vitest.

## Decisiones tomadas

- **El observador es global por eventos con comodín, no `Model::observe()` por modelo.** El documento
  pide un observador «para que no se pueda olvidar», y uno registrado modelo por modelo sí se olvida:
  basta con que el módulo siguiente no ponga su línea. Lo que **no** se audita es una lista corta y
  explícita: `Auditoria` (sería recursión infinita), `Acceso` e `Invitacion`.
- **`auditoria` no lleva RLS ni el trait, y es a propósito** —la tercera tabla así, tras `accesos` e
  `invitaciones`—. La línea se escribe dentro de la transacción de la operación auditada: con
  `with check`, escribirla fallaría en toda operación sin empresa en contexto (consola, aceptar una
  invitación, el panel del proveedor) y tumbaría la operación legítima con ella. Una auditoría que
  puede impedir guardar es peor que no tenerla. El aislamiento de lectura lo pone
  `Auditoria::deLaEmpresaActual()`, que falla cerrado igual que el global scope: sin empresa, cero filas.
- **La empresa de la línea se toma del registro tocado, no del contexto.** Es la que tiene que verla.
  `Tenant` es su propio caso: la línea sobre una empresa es de esa empresa.
- **Crear y eliminar guardan la fila entera; editar guarda solo lo que cambió.** En una edición el
  interés es el diferencial; en un borrado, el estado completo que desapareció.
- **`updated_at`, `created_at` y `deleted_at` no cuentan como cambio.** Sin esto, restaurar dejaría
  dos líneas —una edición de `deleted_at` y la restauración— contando lo mismo, y un `touch()` dejaría
  una línea vacía. Una edición sin nada más que cambie no escribe línea.
- **Las contraseñas y los tokens nunca se copian**: el campo figura con el marcador `(oculto)`, así
  que la auditoría puede decir «cambió la contraseña» sin ser el sitio más fácil del sistema para
  robarlas. Hay prueba de que no queda ni un hash `$2y$` en la tabla.

## Preguntas para Angela

*(ninguna)*

## Decisiones para David

- **Vigencia del enlace de invitación: siete días.** Sigue abierta desde F0-07. El documento fija la
  hora del enlace de recuperación pero no dice nada de la invitación, que es otra cosa. Está en
  `Invitaciones::DIAS_DE_VIGENCIA`, en un solo sitio. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Sigue abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`. Conviene hacerlo también en local para
  no descubrirlo el día del despliegue.
- Sigue abierta de siete sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo sensible).
  Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()` copiando
  `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a mano
  —cambiar de empresa es siempre `paraTenant()`—, y que el rol de conexión no puede ser superusuario.

## Notas para la próxima sesión

- La siguiente es **F0-09 · Sistema de diseño**. Los componentes `CampoTexto`, `Boton` y `Aviso`
  nacieron en F0-07 por necesidad: **F0-09 no los crea de nuevo, los revisa contra
  `prototipos/sistema.html` y les quita cualquier color literal.**
- **La auditoría todavía no se ve por ninguna pantalla.** La tabla y el observador están; el listado
  para el cliente no está en el backlog como tarea propia, así que probablemente entre con F1-12
  (Configuración). Cuando llegue, la consulta se hace **siempre** con `deLaEmpresaActual()`.
- **La auditoría de la aplicación es de la aplicación.** Un `DB::table(...)->update()` directo no pasa
  por Eloquent y no deja línea. Es el precio de auditar por eventos, y es el motivo de que la capa 2
  (RLS) siga siendo la que defiende de las consultas crudas.
- **`auditoria` no suma caso a `AislamientoTest`**: no es tabla de negocio, no lleva el trait. Su
  aislamiento se prueba en `AuditoriaTest` con los dos casos de `deLaEmpresaActual()`. Las tablas de
  negocio de verdad —empezando por `personas` en F1-01— sí lo suman.
- Sigue pendiente para F1-01: la migración de `personas` copia
  `database/stubs/migracion-tabla-de-negocio.stub` y suma su `casoDeAislamiento()`.
