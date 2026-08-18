# Estado del proyecto

**Última sesión:** 2026-08-17 · Tarea F0-06
**Verificación:** verde
**Siguiente tarea:** F0-07 · Autenticación

## Qué se hizo

- **`App\Autorizacion\Rol`** (`owner | superadmin | admin`) y **`App\Autorizacion\Modulo`**, los dos
  enumerados guardados como texto legible. `Modulo` trae `etiqueta()`, `seccion()` —para agrupar las
  novedades en el menú—, `esDeTodos()`, `esSoloDelSuperadministrador()` y `asignables()`.
- **Columna `rol` en `users`**, con `admin` por defecto: si alguna vez se crea un usuario sin decir su
  rol, el error tiene que ser que no ve algo, nunca que lo ve todo.
- **Tabla `user_modulos`** siguiendo el stub de tabla de negocio: `tenant_id`, índice con la empresa
  primero, borrado lógico y `PoliticaRls::activarEn()` en la misma migración. Su índice único es
  **parcial** (`where deleted_at is null`), porque con borrado lógico un módulo revocado bloquearía
  para siempre volver a concederlo.
- **`App\Autorizacion\PoliticaDeModulos`**, el único sitio donde se decide el acceso, registrada como
  la habilidad `modulo` en `AppServiceProvider`. Expone también `modulosPermitidos()`.
- **Middleware `modulo:`** (`VerificarModulo`), con alias registrado en `bootstrap/app.php`. Se declara
  en la ruta: `->middleware('modulo:vacaciones')`. Un nombre de módulo inexistente **revienta**, no
  responde 403: es una errata en la ruta, no una petición inválida.
- **`HandleInertiaRequests`** comparte `usuario` con nombre, correo, rol, empresa y los módulos
  permitidos, calculados con la misma política. F0-10 arma el menú con eso.
- **`tests/Feature/RolesYPermisosTest.php`**, 14 casos, todos con petición HTTP real: el «Hecho cuando»
  es el primero. También el caso de aislamiento de `user_modulos` en `AislamientoTest`.
- `composer verificar` en verde: 64 pruebas de Pest, PHPStan sin errores, build y Vitest.

## Decisiones tomadas

- **Las tres subsecciones de novedades son módulos independientes.** El capítulo 3 dice que «una
  persona puede encargarse solo de vacaciones», y la sección 4.2 del documento de diseño agrupa las
  tres bajo «Novedades». Se resuelve con permiso por subsección y `seccion()` para el menú, que
  cumple las dos cosas sin inventar nada.
- **`Inicio` y `Configuracion` no se asignan.** El primero lo ve todo usuario de una empresa; el
  segundo es del superadministrador y el rol manda por encima de la tabla: aunque alguien inserte la
  fila, un administrador sigue recibiendo 403. Hay prueba de ese caso.
- **`user_modulos` es tabla de negocio, no de sistema.** Quién puede ver qué es información de la
  empresa, y quitar un permiso debe quedar registrado como cualquier otro cambio. Por eso lleva el
  trait, RLS y su caso de aislamiento.
- **El `owner` no entra a los módulos del cliente.** Su sitio es el panel del proveedor. Cuando F5-07
  le dé acceso al entorno de una empresa, será de solo lectura y quedando en la auditoría de esa
  empresa; hasta entonces la política responde que no, y hay prueba.
- **`users` sigue sin el trait `PerteneceATenant`.** Hay que poder buscar por correo antes de saber de
  qué empresa es el usuario, que es justo lo que hace el inicio de sesión. El aislamiento de los
  usuarios se apoya en `user_modulos` y en la política, no en un global scope sobre `users`.
- **`AislamientoTest` admite ahora una clave `secreto` opcional** en cada caso. `user_modulos` la usa
  porque su único campo de texto es de catálogo cerrado; lo que importa no es que el texto sea
  secreto, sino que solo lo tenga la empresa A.

## Preguntas para Angela

*(ninguna)*

## Decisiones para David

- **El rol de conexión de PostgreSQL.** Sigue abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`. Conviene hacerlo también en local para
  no descubrirlo el día del despliegue.
- Sigue abierta de cinco sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo sensible).
  Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()` copiando
  `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a mano
  —cambiar de empresa es siempre `paraTenant()`—, y que el rol de conexión no puede ser superusuario.

## Notas para la próxima sesión

- La siguiente es **F0-07 · Autenticación**: iniciar y cerrar sesión, recuperar y restablecer
  contraseña, aceptar invitación, bloqueo tras intentos fallidos y registro de accesos incluidos los
  fallidos. El proyecto **no trae starter kit de autenticación**: no hay rutas ni pantallas de acceso
  todavía, hay que construirlas.
- La pantalla «Aceptar invitación» muestra al usuario un resumen de sus módulos: ya se puede sacar de
  `PoliticaDeModulos::modulosPermitidos()`.
- Cuando F0-10 monte las rutas reales de cada módulo, cada una lleva `->middleware('modulo:<nombre>')`
  y el menú se pinta con `usuario.modulos` de las props compartidas. Las rutas de juguete de
  `RolesYPermisosTest` desaparecen cuando existan las reales.
- La pantalla de usuarios de Configuración (F1-12 y siguientes) concede módulos escribiendo en
  `user_modulos` y los revoca con borrado lógico. `Modulo::asignables()` es la lista que se ofrece.
- Sigue pendiente para F1-01: la migración de `personas` copia el stub y suma su `casoDeAislamiento()`.
