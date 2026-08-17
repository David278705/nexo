# Estado del proyecto

**Última sesión:** 2026-08-17 · Tarea F0-01
**Verificación:** verde
**Siguiente tarea:** F0-02 · Configuración base

## Qué se hizo

- Laravel 12 conectado a **Inertia 2 + Vue 3 + Tailwind 4**. Se quitó el arranque de Vue suelto
  (`components/App.vue`, `bootstrap.js` con axios) y se reemplazó por `createInertiaApp` en
  `resources/js/app.js`, con resolución de páginas desde `resources/js/Pages/`.
- `resources/views/app.blade.php` es ya la plantilla raíz de Inertia (`@inertia`, `@inertiaHead`).
  Es el único Blade de la interfaz.
- Middleware `App\Http\Middleware\HandleInertiaRequests` registrado en `bootstrap/app.php`.
- Página de humo `Pages/Inicio.vue` servida por `Route::get('/')` con `Inertia::render`.
- **PostgreSQL** como base de datos de desarrollo (`nexo`) y de pruebas (`nexo_pruebas`, fijada en
  `phpunit.xml`). `.env.example` actualizado a `pgsql`.
- **Pest 3** instalado (`tests/Pest.php` aplica `RefreshDatabase` a todo `Feature`).
  `tests/Feature/CimientosTest.php` cubre: la página de inicio responde como componente de Inertia,
  las pruebas corren contra el driver `pgsql`, y `/up` responde.
- **Pint** (`pint.json`, preset Laravel) y **PHPStan/Larastan nivel 6** (`phpstan.neon`).
- **Vitest + @vue/test-utils + jsdom** (`vitest.config.js`), con `Inicio.test.js` como primera prueba
  de componente.
- Script `composer verificar`: `pint --test` → `phpstan analyse` → `pest` → `npm run build` →
  `npm run test`. Corre entero y termina en verde.

## Decisiones tomadas

- **PHPStan analiza `app`, `config`, `database` y `routes`, no `tests`.** Pest reasigna `$this`
  dentro de las closures y PHPStan no lo resuelve, así que analizar las pruebas solo produce ruido
  (`Call to an undefined method PHPUnit\Framework\TestCase::get()`). Quien valida las pruebas es Pest
  al ejecutarlas. Está anotado como comentario dentro de `phpstan.neon`.
- **Las pruebas corren contra PostgreSQL real, no SQLite en memoria.** Es obligatorio: F0-04 monta
  Row Level Security, que SQLite no tiene. Mejor pagar el costo desde la primera prueba que
  descubrirlo en la fase 0 tarde. Requiere que exista la base `nexo_pruebas`.
- **`@inertiajs/vue3` fijado a `^2.0`** (quedó en 2.3.27). `npm install` sin restricción traía la 3.x,
  que no corresponde al adaptador de servidor `inertiajs/inertia-laravel` v2. El backlog pide Inertia 2.
- **`axios` fuera de `package.json`.** La convención del proyecto es que los datos llegan como props
  de Inertia, no por peticiones sueltas. Inertia lo sigue trayendo como dependencia propia.
- El `Head` de Inertia se sustituye con `vi.mock` en las pruebas de Vitest, porque necesita el gestor
  de encabezado que solo instala `createInertiaApp` en el navegador. Si una segunda prueba lo
  necesita, ese mock se extrae a un archivo de setup compartido.

## Preguntas para Angela

*(ninguna; las que ya existen están en `docs/06-decisiones-pendientes.md` bloque D)*

## Decisiones para David

- **La máquina tiene PHP 8.2.12 (XAMPP), y `CLAUDE.md` pide PHP 8.3+.** Laravel 12 corre con 8.2, y la
  verificación está verde, así que no bloquea nada hoy. Pero `composer.json` quedó en `"php": "^8.2"`.
  Si el destino de producción va a ser 8.3+, conviene actualizar el PHP local antes de que aparezca
  código que use sintaxis de 8.3, o dejar constancia de que el mínimo real del proyecto es 8.2.

## Notas para la próxima sesión

- La siguiente tarea es **F0-02 · Configuración base**: `config/reglas_laborales.php` con las nueve
  constantes legales, cada una con su norma citada y fecha de verificación, más zona horaria
  `America/Bogota` y locale `es`. Fuente: `.claude/rules/20-calculo-y-reglas-legales.md`.
  Ojo: `.env` y `config/app.php` siguen con `APP_LOCALE=en`; eso lo arregla F0-02, no se tocó aquí
  para no adelantar tarea.
- Para levantar el proyecto en una máquina nueva hay que crear a mano las dos bases de PostgreSQL
  (`nexo` y `nexo_pruebas`); todavía no hay comando que lo haga.
- `composer verificar` tarda ~30 s. Si algo sale rojo, el orden ayuda: Pint y PHPStan fallan rápido
  y antes de tocar la base de datos.
