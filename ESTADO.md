# Estado del proyecto

**Última sesión:** 2026-09-20 · Dos hallazgos de David probando el sistema, fuera del backlog:
la planta de la empresa de prueba estaba casi vacía y no había forma de entrar en una máquina nueva.
**Verificación:** verde. 1.319 de 1.319 pruebas de Pest, Pint y PHPStan sin errores, build y 415 de
Vitest — once pruebas de Pest más que `HD-16`, las de Vitest no cambian.
**Siguiente tarea:** ninguna. **El backlog sigue completo**: no queda ninguna tarea `[ ]`.

## Qué se hizo

### La empresa de prueba parecía usada pero estaba vacía

David entraba a una ficha cualquiera y no encontraba nada. El motivo estaba en
`AmbienteDeDemostracion::planta()`: ocho personas, y **una sola novedad cada una como mucho**. Era
una demostración de *situaciones* —una persona por caso— y por eso siete de cada ocho fichas salían
en blanco.

- **`App\Proveedor\PlantaDeMuestra` es nueva** y se quedó con la planta: 28 personas, 7 de ellas
  inactivas. Cubre los tres tipos de contrato, los tres de licencia, los dos orígenes de incapacidad
  y **los siete tipos de salida**, uno cada uno, para que el filtro del listado de terminaciones
  tenga algo que filtrar. Gente con tres temporadas de vacaciones, prórrogas encadenadas, aumentos y
  promociones.
- **Los datos siguen siendo fijos, no aleatorios**, que era la propiedad que la planta anterior
  cuidaba: la demostración tiene que lucir igual de poblada siempre. Lo único relativo a hoy son las
  fechas. Los diez «veteranos» se derivan del índice en vez de estar escritos uno por uno —derivar no
  es sortear: el mismo índice da siempre la misma persona—.
- **Ninguna persona tiene dos novedades que se pisen.** Las novedades de cada quien se reparten en
  meses distintos a propósito, porque desde `HD-03` el sistema rechaza los traslapes y una planta que
  los tuviera estaría enseñando datos que sus propios formularios no dejarían registrar.

### No había con qué entrar en una máquina nueva

El `Test User` del seeder de Laravel tiene `tenant_id` nulo y rol `admin`: un usuario de empresa sin
empresa, que no sirve para entrar a ninguna parte. Y la única empresa que existía era la de
demostración, que es de **solo lectura**, así que tampoco servía para probar nada que se registre.

- **`DesarrolloSeeder` es nuevo**: crea la cuenta del proveedor (`owner@nexo.local`), una empresa
  normal y **escribible** (`Empresa de Pruebas S.A.S.`, sin `es_ambiente_demo`) con su
  superadministrador (`admin@empresademo.local`), y le escribe la misma `PlantaDeMuestra`. Las dos
  claves son `password`.
- **No corre fuera de local**, y el guardia está dentro del propio seeder y no en quien lo llama:
  crea contraseñas conocidas, que en un servidor de verdad sería una puerta trasera.
- **`DatabaseSeeder` ya no crea el `Test User`** y llama al seeder de desarrollo solo en local. Con
  eso, `php artisan migrate:fresh --seed` deja la máquina lista de un golpe.
- **Escribe con auditoría, al revés que la demostración.** `ObservadorDeAuditoria::sinAuditar()` solo
  se deja usar dentro del ambiente de demostración —es una protección de REV-07 que se respetó en vez
  de rodear—, y además el rastro le da contenido a la pantalla de auditoría, que si no nacería vacía.

## Decisiones tomadas

- **La empresa de demostración sigue siendo de solo lectura.** El candado protege lo que explora un
  prospecto con un acceso de prueba. Por eso se hicieron **dos** empresas y no una: la de
  demostración, rica pero intocable, y la de desarrollo, rica y escribible. Enriquecer solo la
  primera habría dejado a David mirando sin poder registrar.
- **`WithoutModelEvents` se mudó de `DatabaseSeeder` a `CatalogosSeeder`.** Desde el padre silenciaba
  también lo que viniera después, y lo que viene después necesita justamente esos eventos: de ellos
  cuelgan el `tenant_id` que pone `PerteneceATenant` y el periodo inicial que abre
  `ObservadorDeVinculacion`. Fue la causa real del primer fallo del seeder, no la que parecía.
- **Las pruebas dejaron de repetir el número 8.** `AmbienteDeDemostracionTest` lee ahora
  `PlantaDeMuestra::TOTAL`, y el `16` de las filas borradas lógicamente pasó a ser `TOTAL * 2`, que es
  lo que de verdad significa.

## Preguntas para Angela

- (vacío, no cambia respecto a la sesión anterior)

## Decisiones para David

- **Las verificaciones manuales pendientes ahora sí se pueden hacer.** La lista larga que venía
  arrastrándose desde `HD-04` estaba bloqueada en la práctica porque no había una empresa con datos y
  escribible. Ya la hay: entra como `admin@empresademo.local` / `password`.
- **`HD-12` mencionaba una «carga masiva de terminaciones» que no existe.** ¿Quieres una plantilla de
  terminaciones —y entonces es tarea nueva— o el punto se da por descartado?
- **`HD-05` sigue bloqueada**, esperando el calendario de festivos de 2016-2025 verificado a mano.
- **`F6-07` y `F3-06b` siguen esperándote** (Railway y proveedor transaccional de correo).
- **La tarea `HD-10` decía «siete plantillas» y el código solo tiene seis.**
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.**

## Notas para la próxima sesión

- **No hay tarea `[ ]` en el backlog.** Sigue conviniendo un punto de control con `/revisar` antes de
  inventar trabajo.
- **Hay dos empresas en local y hacen cosas distintas.** `Empresa Demo Nexo S.A.S.` es de solo
  lectura y se restaura con `demostracion:restaurar`; `Empresa de Pruebas S.A.S.` es escribible y la
  escribe `db:seed --class=DesarrolloSeeder`, que es idempotente —si ya hay personas, no duplica—.
- **Para correr un archivo de Pest suelto que use `usuarioDe()`** hay que incluir
  `RolesYPermisosTest.php`, y `empleadoParaNovedad()` vive en `FlujoDeNovedadTest.php`. Y
  `php -d memory_limit=1G vendor/bin/pest`, que con el límite del php.ini de esta máquina se muere.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo.
