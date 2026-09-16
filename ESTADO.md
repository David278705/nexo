# Estado del proyecto

**Última sesión:** 2026-09-16 · Se convirtieron en backlog las tres grabaciones de David probando el
sistema (8 y 10 de septiembre). No se escribió código de producto: la sesión fue de montaje del
entorno local y de organización del trabajo que viene.
**Verificación:** sin cambios de código que verificar. La última corrida conocida quedó en
**1.227 de 1.228** pruebas de Pest —ver «El rojo conocido» más abajo, que no es un fallo del
producto—, Pint y PHPStan sin errores, build y 401 de Vitest.
**Siguiente tarea:** `HD-01` · La pestaña «Novedades» de la ficha del empleado devuelve siempre una
lista vacía.

## Qué se hizo (2026-09-16)

### El entorno local quedó montado

El `.env` venía de otra máquina y faltaba todo lo demás. Quedó funcionando:

- **PostgreSQL 16 por Homebrew**, no por XAMPP. Los binarios son keg-only y viven en
  `/opt/homebrew/opt/postgresql@16/bin`, así que `psql` no está en el PATH. Se creó el rol
  `postgres`/`postgres` para que coincidiera con el `.env`, y las bases `nexo` y `nexo_pruebas`.
- Las 45 migraciones corridas, con sus políticas RLS, y los catálogos globales sembrados
  (`CatalogosSeeder`): 1.101 municipios, 18 EPS, festivos.
- `composer install`, `npm install` y `npm run build`.
- **Cuenta de proveedor nueva: `owner@nexo.local` / `password`**, rol `owner`, creada a mano porque
  el `Test User` del `DatabaseSeeder` tiene `tenant_id` nulo y rol `admin` —un usuario de empresa sin
  empresa, inservible para entrar—. Al entrar desvía a activar el segundo factor TOTP, que es el
  comportamiento diseñado.
- El ambiente de demostración poblado con `php artisan demostracion:restaurar`. Es de **solo
  lectura**; para trabajar hay que crear una empresa normal desde el panel del proveedor.
- La app se levanta con `php artisan serve --port=8014`, que es el puerto que ya esperaba `APP_URL`.

### El correo local pasó a `log`

`MAIL_MAILER=log` en el `.env`. El puerto 2525 de Mailtrap está bloqueado en esta red —la salida
general a internet sí funciona, se comprobó— y los envíos se colgaban hasta agotar el tiempo de
espera. Ahora invitaciones y alertas quedan en `storage/logs/laravel.log`, que además es de donde
hay que sacar los enlaces de invitación. Las credenciales de Mailtrap siguen en el archivo y el
comentario dice cómo volver.

### Las tres grabaciones se volvieron dieciséis tareas

David recorrió el sistema con datos reales y grabó lo que encontró. **Cada hallazgo se verificó
contra el código antes de escribir su tarea**, y lo que ya estaba resuelto no abrió tarea en falso.
Las dieciséis, `HD-01` a `HD-16`, están al final de `BACKLOG.md` en su propia sección y en orden de
ejecución.

Lo que la verificación confirmó, por si sirve para entrar en materia:

- `FichaDeEmpleado::novedades()` es `return [];` con un comentario que dice «vacío hasta que existan
  sus módulos». Existen desde la fase 2. Mismo patrón que REV-12.
- **No hay una sola comprobación de traslape en todo `app/`**: ni `traslap`, ni `solap`, ni
  `superpon`, ni `overlap`. Por eso se pueden registrar dos periodos de vacaciones que se pisan.
- `personas` no tiene ninguna columna de contacto: ni celular ni correo.
- `festivos` solo tiene 2025, 2026 y 2027. El histórico se carga de años anteriores.
- El catálogo dice `Sura EPS`; el ejemplo de la plantilla de incapacidades dice `SURA`.
- `Modulo::grupo()` agrupa `Movimientos` y `Terminaciones` bajo un encabezado llamado, también,
  «Movimientos».
- `CambioAIndefinido::fechaDelCambio()` devuelve `Carbon::today()` sin alternativa.
- La EPS está fuera de la tabla de incapacidades a propósito, y `columnasDeOrden()` no admite `dias`.
- **Ya estaba resuelto:** el orden del país de expedición en la plantilla de empleados. REV-08 lo
  movió a su sitio; la grabación del 8 de septiembre a las 12:48 es anterior a ese arreglo. Solo
  queda partir «Lugar de expedición» en departamento y ciudad, que es parte de `HD-08`.

## Decisiones tomadas

- **Las cuatro dudas de las grabaciones quedaron resueltas por David el 2026-09-16**, antes de
  escribir las tareas, justamente para que ninguna naciera `[!]` y frenara el bucle. Están en
  `docs/06-decisiones-pendientes.md` D.7, con su razonamiento: el catálogo de Bogotá no se toca y se
  arregla la guía; las prórrogas van en columnas dentro de una sola plantilla; la incapacidad que
  pisa unas vacaciones las recalcula y deja nota; y la terminación se bloquea mientras el contrato
  esté en el aire.
- **`MAIL_MAILER=log` en el entorno local**, por el puerto bloqueado. No afecta a producción ni al
  `.env.example`.

## Preguntas para Angela

- **La norma que sustenta que la incapacidad interrumpe el disfrute de vacaciones.** Hace falta para
  citarla en `config/reglas_laborales.php` con su fecha de verificación, como manda
  `.claude/rules/20-calculo-y-reglas-legales.md`. **No bloquea `HD-04`**: el comportamiento ya está
  decidido y la tarea se puede construir. Lo que no se puede es inventar la norma: mientras no
  llegue, la constante se escribe con la nota de que falta la cita.
- **B.2 sigue abierta**: periodo de prueba en cambio de modalidad contractual.
- **El fondo de cesantías sigue abierto**: si hace falta pedirlo, y dónde.

## Decisiones para David

- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway,
  en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Con `MAIL_MAILER=log` en local, ninguna alerta sale de la máquina de verdad.
- **Verificaciones manuales heredadas y todavía sin hacer**: la ficha de empresa del proveedor tras
  REV-12, el formulario de acceso de prueba tras F5-08, la pestaña «Facturación» de REV-11 y el flujo
  completo de REV-10.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## El rojo conocido de `composer verificar`

**Una prueba falla y no es un fallo del producto.** Es la de buscar «Ramírez» en
`tests/Feature/ExportacionesYFiltrosTest.php`. El síntoma es `Undefined array key "incapacidades"`,
que tapa un error de Postgres: `invalid byte sequence for encoding "UTF8": 0xc3 0x5f`.

La prueba llama `$this->get('/incapacidades?buscar=Ramírez')` con la tilde **cruda, sin codificar**.
`Symfony\Request::create()` pasa esa URI por `parse_url()`, que reemplaza por `_` los bytes
0x80–0x9F y 0xAD sin saber de UTF-8; como «í» es `0xC3 0xAD`, el segundo byte se convierte en `_` y
a Postgres le llega una secuencia rota. Pasa igual en PHP 8.3 y 8.4.

**La app real no se ve afectada**: el navegador codifica la tilde como `%C3%AD` antes de mandarla, y
en producción Laravel lee `$_GET` del SAPI, no de `parse_url()`. Se comprobó contra el servidor
corriendo.

**Si alguna sesión quiere arreglarlo**, es un cambio de una línea en la prueba —`urlencode('Ramírez')`
en la URI—, no en el código de producción. No se hizo porque no era la tarea de esta sesión, y
conviene decidirlo a propósito y no de paso.

## Notas para la próxima sesión

- **El bucle arranca por `HD-01`.** Es la primera `[ ]` del backlog y es autocontenida: conectar
  `FichaDeEmpleado::novedades()` a las tres tablas que ya existen.
- **`HD-03` antes que `HD-04`, y en ese orden están.** La detección de traslapes que construye
  `HD-03` es la que `HD-04` necesita para saber qué días se pisan. Tomarlas al revés obliga a
  escribir dos veces lo mismo.
- **`HD-06`, `HD-08` y `HD-09` tocan las tres la plantilla de empleados.** Están seguidas a
  propósito: hacerlas juntas evita tres migraciones de plantilla y tres rondas de pruebas sobre el
  mismo archivo.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`,
  que levanta servidor, cola y Vite a la vez.
