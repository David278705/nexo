# Estado del proyecto

**Última sesión:** 2026-08-17 · Tarea F0-04
**Verificación:** verde
**Siguiente tarea:** F0-05 · Batería de aislamiento

## Qué se hizo

- **`App\Multiempresa\PoliticaRls`**: las dos mitades de la capa 2 en una sola clase, a propósito.
  - `activarEn($tabla)` / `desactivarEn($tabla)`: `ENABLE` + `FORCE ROW LEVEL SECURITY` y la política
    `tenant_id = empresa_actual()`, con `USING` y `WITH CHECK`. La llaman las migraciones.
  - `fijarEmpresa(?int)` / `empresaFijada()`: la variable de sesión `nexo.tenant_id`.
  - Todo guardado tras un chequeo de driver, así que en una conexión que no sea `pgsql` no revienta.
- **Migración `2026_08_17_100002_create_funcion_empresa_actual`**: la función SQL `empresa_actual()`,
  `stable`, que devuelve `nullif(current_setting('nexo.tenant_id', true), '')::bigint`. Existe para
  que ninguna política repita la fórmula: las treinta tablas de negocio dirán `= empresa_actual()`.
- **`ContextoTenant` mueve las dos capas a la vez.** `fijar()` y `olvidar()` ahora pasan por un
  `establecer()` privado que cambia la propiedad *y* la variable de sesión. `paraTenant()` y
  `sinTenant()` restauran por el mismo camino en su `finally`, así que la sesión de PostgreSQL
  también se restaura si un job falla.
- **`ResolverTenant` empieza por `olvidar()`.** Antes solo fijaba; ahora limpia primero, de modo que
  una conexión reutilizada no llega con la empresa de la petición anterior puesta.
- **`database/stubs/migracion-tabla-de-negocio.stub`**: la migración de ejemplo que pedía la tarea,
  con las cuatro cosas obligatorias (columna, índice con `tenant_id` primero, `softDeletes`,
  `PoliticaRls::activarEn` en la misma migración) y qué tablas no la siguen.
- **`tests/Feature/RlsTest.php`, doce casos verdes.** Todas las consultas van por el query builder
  para saltarse el global scope a propósito: leer, buscar por clave, insertar, actualizar y borrar
  filas de otra empresa; sin empresa en la sesión no sale nada; y los dos casos del middleware.
  Se comprobó a mano que comentar `PoliticaRls::activarEn` pone cinco de esos casos en rojo.

## Decisiones tomadas

- **La política se declara con `FORCE ROW LEVEL SECURITY`.** Sin `FORCE`, el dueño de la tabla se
  salta su propia política, y el dueño es justamente el rol que corrió las migraciones.
- **La política falla cerrada, igual que el global scope.** `fijarEmpresa(null)` deja la variable en
  cadena vacía, el `nullif` la vuelve nula, y `tenant_id = null` no es cierto para ninguna fila.
- **`set_config(..., false)`, de sesión y no de transacción.** De transacción se perdería en cada
  commit y la política dejaría de encontrar la empresa a mitad de la petición.
- **Una sola política por tabla, con `USING` y `WITH CHECK`.** Sin la segunda mitad se podría
  insertar una fila en otra empresa aunque después no se pudiera leer.
- **La prueba cambia de rol con `set role`.** `postgres` es superusuario y PostgreSQL **nunca**
  aplica RLS a un superusuario: sin cambiar de rol, las doce pruebas pasarían sin comprobar nada.
  El cambio va dentro de una transacción anidada —un savepoint— porque una consulta rechazada por la
  política aborta la transacción y solo un rollback la deja usable para el resto de la prueba.
- **La función `empresa_actual()` es global, no por empresa.** Es una función de lectura de la
  sesión; no toca datos de nadie.
- **`tenants` no lleva RLS.** No es una tabla de negocio: no tiene `tenant_id`, se identifica por su
  propia clave. Su acceso se resolverá con las políticas de F0-06.

## Preguntas para Angela

*(ninguna)*

## Decisiones para David

- **El rol de conexión de PostgreSQL.** Hoy `.env` se conecta como `postgres`, que es superusuario y
  tiene `BYPASSRLS`: **en local la capa 2 no se aplica nunca**. En producción hay que conectarse con
  un rol corriente (sin `SUPERUSER` ni `BYPASSRLS`) y darle `SELECT, INSERT, UPDATE, DELETE` sobre
  las tablas. Es la única forma de que RLS sirva de algo. Conviene hacerlo también en local para no
  descubrirlo el día del despliegue.
- Sigue abierta de tres sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible y pidió permiso). Faltan por añadir a la sección «Capa 2» tres líneas: que la migración
  llama a `PoliticaRls::activarEn()` copiando `database/stubs/migracion-tabla-de-negocio.stub`, que
  nunca se llama a `fijarEmpresa()` a mano —cambiar de empresa es siempre `paraTenant()`—, y que el
  rol de conexión no puede ser superusuario.

## Notas para la próxima sesión

- La siguiente es **F0-05 · Batería de aislamiento**: `tests/Feature/AislamientoTest.php`. Ojo con la
  diferencia de propósito: `MultiempresaTest` prueba el trait, `RlsTest` prueba la política de
  PostgreSQL, y `AislamientoTest` prueba **los caminos** —modelo, ruta, exportación, búsqueda—, una
  tabla de negocio por caso. El «Hecho cuando» exige que quitar el trait a propósito la ponga en
  rojo, así que el caso tiene que apoyarse en la capa 1, no solo en la capa 2.
- Todavía no hay ninguna tabla de negocio real. La de F0-05 tendrá que apoyarse otra vez en
  `Tests\Fixtures\RegistroDePrueba` y en una tabla creada por la propia prueba, con su ruta de
  juguete registrada en el test.
- Cuando llegue la primera tabla de verdad (F1-01, `personas`), su migración copia el stub y añade su
  caso a `AislamientoTest`. No se da por terminada una tabla de negocio sin las dos cosas.
- El helper `conRolDeAplicacion()` de `RlsTest` es reutilizable; si `AislamientoTest` lo necesita,
  conviene sacarlo a `tests/Pest.php` antes de copiarlo.
