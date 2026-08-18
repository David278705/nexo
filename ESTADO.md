# Estado del proyecto

**Última sesión:** 2026-08-17 · Tarea F0-05
**Verificación:** verde
**Siguiente tarea:** F0-06 · Usuarios, roles y permisos

## Qué se hizo

- **`tests/Feature/AislamientoTest.php`**, la batería de aislamiento. No repite lo que ya prueban sus
  vecinas: `MultiempresaTest` prueba el trait, `RlsTest` prueba la política de PostgreSQL, y esta
  prueba **los caminos** por los que un dato podría salir.
- **`casoDeAislamiento($etiqueta, $caso)`**: una sola llamada por tabla de negocio genera cinco
  pruebas. El `$caso` es un arreglo con `tabla`, `modelo`, `campo` de texto, la `migracion` y el
  cierre `crear`. Agregar una tabla al backlog de aislamiento cuesta seis líneas.
- **Los cinco casos por tabla**: por modelo (`find` y `count` desde la empresa B), por ruta (la ficha
  responde 404), por exportación (el CSV no contiene el texto ajeno), por búsqueda (buscar el texto
  literal desde la B devuelve cero), y el **contrapeso**: la empresa dueña sí lo ve por los cuatro
  caminos.
- **Tres rutas de juguete** registradas en el grupo `web` bajo `/aislamiento/{tabla}`: listado con
  `?buscar=`, `/exportar` y la ficha `/{id}`. `exportar` se registra antes que la ficha para que no
  se lea como identificador.
- **Comprobado a mano el «Hecho cuando»**: comentando `addGlobalScope(new AlcanceTenant)` en el trait,
  cuatro de los cinco casos se ponen en rojo y solo pasa el contrapeso. Se restauró el trait y
  `composer verificar` quedó verde: 45 pruebas de Pest, build y Vitest.

## Decisiones tomadas

- **La estructura es una fábrica de casos, no un archivo que se copia y pega.** El «Hecho cuando» de
  cada tabla de negocio futura es agregar una llamada; si el caso hubiera que escribirlo entero, la
  regla de `.claude/rules/10-multiempresa.md` se dejaría de cumplir a la tercera tabla.
- **El contrapeso es obligatorio en cada caso.** Sin él, una ruta mal escrita o una tabla vacía dejan
  los cuatro casos de aislamiento en verde sin haber comprobado nada. Es el mismo razonamiento que
  llevó a la prueba «sí devuelve las filas propias» de `RlsTest`.
- **Los usuarios se guardan sin autenticar.** `test()->actingAs()` devuelve siempre la misma instancia
  de prueba, así que autenticar a los dos usuarios al montar la escena dejaría autenticado solo al
  último y las pruebas de la empresa dueña mentirían.
- **La exportación se prueba como CSV construido desde el modelo.** Laravel Excel todavía no está
  instalado; lo que importa del camino es que la consulta pase por el modelo, y eso ya se comprueba.
  Cuando entre el paquete, el caso apunta a la ruta real sin cambiar la estructura.
- **La búsqueda se hace con `ilike` sobre el texto secreto exacto.** Es el camino que más fácilmente
  devolvería un registro ajeno si faltara el filtro, porque el término lo pone el usuario.
- **La migración del caso incluye `PoliticaRls::activarEn`.** No cambia el resultado en local —la
  conexión es superusuario y se salta RLS— pero deja el ejemplo igual a como serán las migraciones
  reales.

## Preguntas para Angela

*(ninguna)*

## Decisiones para David

- **El rol de conexión de PostgreSQL.** Sigue abierta desde F0-04: `.env` se conecta como `postgres`,
  que es superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción
  hay que usar un rol corriente con `SELECT, INSERT, UPDATE, DELETE` sobre las tablas. Conviene hacerlo
  también en local para no descubrirlo el día del despliegue.
- Sigue abierta de cuatro sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo sensible).
  Faltan por añadir a la sección «Capa 2» tres líneas: que la migración llama a
  `PoliticaRls::activarEn()` copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se
  llama a `fijarEmpresa()` a mano —cambiar de empresa es siempre `paraTenant()`—, y que el rol de
  conexión no puede ser superusuario.

## Notas para la próxima sesión

- La siguiente es **F0-06 · Usuarios, roles y permisos**: rol `owner | superadmin | admin` en `users`,
  tabla `user_modulos`, políticas y middleware de módulo. El «Hecho cuando» exige **403 del servidor**
  al pedir la ruta directamente, no solo que el menú deje de mostrar el módulo.
- `users` lleva `tenant_id` pero **no** es una tabla de negocio corriente: el `owner` atraviesa
  empresas. Antes de ponerle el trait o RLS, decidir cómo se resuelve ese caso; F0-04 dejó pendiente
  lo mismo para `tenants`.
- Cuando F1-01 cree `personas`, su cierre son dos cosas: la migración copia
  `database/stubs/migracion-tabla-de-negocio.stub` y se agrega su `casoDeAislamiento()` al final de
  `tests/Feature/AislamientoTest.php`. Sin las dos, la tabla no está terminada.
- El helper `conRolDeAplicacion()` sigue viviendo solo en `RlsTest`. `AislamientoTest` no lo necesitó
  —prueba la capa 1, donde el rol de base de datos da igual—, así que no se movió a `tests/Pest.php`.
