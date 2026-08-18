# Estado del proyecto

**Última sesión:** 2026-08-17 · Tarea F0-03
**Verificación:** verde
**Siguiente tarea:** F0-04 · Multiempresa, capa 2 (RLS)

## Qué se hizo

- **Tabla `tenants`** (`2026_08_17_100000`): datos de la empresa (`nombre`, `nit` único, `telefono`,
  `ciudad`), `estado` como texto legible, los **tres parámetros de cálculo** (`sabado_laboral`,
  `suspensiones_afectan_vacaciones`, `perfil_sociodemografico_habilitado`) y los **tres campos de
  salud** del doc. 02 §3.5 (`ultimo_acceso_en`, `empleados_cargados`, `ultima_novedad_en`).
  Con `timestamps` y `softDeletes`.
- **`users.tenant_id`** nulable con clave foránea (`2026_08_17_100001`): es de donde el middleware
  saca la empresa. Nulable porque el `owner` del panel del proveedor no es de ninguna.
- **`App\Multiempresa\ContextoTenant`**, singleton: `fijar`, `olvidar`, `tenant`, `id`,
  `idObligatorio`, y sobre todo `paraTenant()` y `sinTenant()`, que restauran el contexto anterior
  en un `finally`. Son la vía para jobs en cola y comandos que recorren empresas.
- **`App\Multiempresa\AlcanceTenant`**, el global scope. Ahí vive la constante `COLUMNA`.
- **`App\Multiempresa\PerteneceATenant`**, el trait: registra el scope y rellena `tenant_id` en
  `creating`. Trae la relación `tenant()`.
- **`App\Http\Middleware\ResolverTenant`**, añadido al grupo `web` **antes** de
  `HandleInertiaRequests`. Resuelve la empresa desde `$request->user()`, nunca desde la URL.
- **Helpers `tenant_actual()` y `tenant_actual_id()`** en `app/Multiempresa/helpers.php`, cargados
  por `composer.json` (`autoload.files`).
- `Tenant` modelo + `TenantFactory` (estados `suspendida()` y `conSabadoLaboral()`).
- `tests/Fixtures/RegistroDePrueba.php`: un modelo de negocio mínimo con el trait y nada más, con su
  tabla creada por la propia prueba.
- **`tests/Feature/MultiempresaTest.php` con once casos**, todos verdes: relleno automático, el
  filtro presente en el SQL, no ver los registros de la otra empresa ni por `all()` ni por `find()`,
  no ver nada sin contexto, no poder crear sin contexto, ignorar un `tenant_id` puesto a mano,
  restaurar el contexto tras una excepción, y los tres casos del middleware.

## Decisiones tomadas

- **El global scope falla cerrado.** Sin empresa en contexto añade `where 1 = 0` en lugar de no
  filtrar. Un comando mal escrito devuelve una lista vacía, no la nómina de todos los clientes.
  Es lo contrario de lo que hace un scope ingenuo y es deliberado.
- **El trait ignora un `tenant_id` puesto a mano** y siempre escribe el del contexto. Si respetara
  el valor recibido, un campo escondido en un formulario podría sembrar filas en otra empresa.
- **Crear sin contexto lanza `SinContextoDeTenant`.** Mejor reventar que escribir una fila huérfana
  o en la empresa equivocada.
- **`User` no lleva el trait.** El inicio de sesión tiene que encontrar al usuario por su correo
  *antes* de saber de qué empresa es. Su aislamiento se resolverá con las políticas de F0-06.
- **Los tres parámetros booleanos de `tenants` no tienen valor por omisión en la base de datos.**
  Cuál es el correcto lo decide el cliente en el onboarding; ponerle un valor por defecto a
  `suspensiones_afectan_vacaciones` sería inventar una regla de negocio.
- **`ciudad` queda como texto** hasta que F0-11 traiga el catálogo de ciudades de Colombia.
- **La constante `COLUMNA` vive en `AlcanceTenant`, no en el trait.** PHP no deja leer una constante
  de trait por el nombre del trait, solo por el de la clase que lo usa; se descubrió en rojo.
- **`tests/Fixtures` entra en las rutas de PHPStan.** Son clases PHP normales, sin closures de Pest,
  y sin ellas PHPStan no veía ni un consumidor del trait y no lo analizaba.
- Se commiteó aparte el trabajo de F0-02, que había quedado en el árbol sin commit.

## Preguntas para Angela

*(ninguna)*

## Decisiones para David

- Sigue abierta de dos sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`. Hay que actualizar el PHP local o dejar
  constancia de que el mínimo real del proyecto es 8.2.

## Notas para la próxima sesión

- La siguiente es **F0-04 · Multiempresa, capa 2 (RLS)**. Lo que falta es la parte de PostgreSQL:
  `ALTER TABLE ... ENABLE ROW LEVEL SECURITY`, la política que compara contra una variable de sesión,
  y que `ResolverTenant` haga el `SET` de esa variable. El middleware ya existe: solo hay que
  agregarle esa línea y su equivalente al fijar contexto desde `ContextoTenant::paraTenant()`.
  Ojo: la variable hay que limpiarla igual que el contexto, o una conexión reutilizada del pool
  arrastra la empresa anterior.
- La migración de ejemplo que pide F0-04 puede apoyarse en el mismo patrón que ya usa
  `registros_de_prueba` dentro de `MultiempresaTest`.
- `tests/Feature/AislamientoTest.php` **todavía no existe**: es F0-05. `MultiempresaTest` prueba el
  trait; `AislamientoTest` probará los caminos (ruta, exportación, búsqueda) tabla por tabla.
- Recordatorio del trait: la regla es que **nunca** aparezca un `where('tenant_id', ...)` en un
  controlador. Si aparece, es que el modelo perdió el trait.
