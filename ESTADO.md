# Estado del proyecto

**Última sesión:** 2026-08-18 · Tarea F0-11
**Verificación:** verde
**Siguiente tarea:** F1-01 · Personas y vinculaciones
*(F0-11 queda `[!]`: lo suyo está hecho salvo el contenido de las cinco listas de entidades, que
depende de la decisión D.5. Se retoma sola cuando Angela responda.)*

## Qué se hizo

- **Nueve tablas de catálogo global**, en tres migraciones: `paises`, `departamentos` y `ciudades`;
  las cinco de entidades —`eps`, `fondos_pension`, `arls`, `cajas_compensacion`, `bancos`—; y
  `festivos`. **Ninguna lleva `tenant_id`, ni el trait, ni política de RLS**, porque son iguales para
  todos los clientes. Una prueba lo comprueba tabla por tabla y modelo por modelo.
- **Los datos reales de Colombia**: 33 departamentos con su código DANE, **1.101 municipios**
  colgados de su departamento, y **232 países** con su código ISO. Viven en
  `database/data/ubicaciones.php` y `database/data/paises.php`, no dentro del seeder: son datos, no
  código, y así se corrigen sin tocar lógica.
- **El calendario de festivos de 2025, 2026 y 2027**, escrito a mano fecha por fecha en
  `database/data/festivos.php`, con la Ley 51 de 1983 aplicada uno a uno. **Incluye los que caen en
  sábado** —1 de mayo, 7 de agosto y 25 de diciembre de 2027—, que es la instrucción expresa del
  capítulo 10.
- **`App\Catalogos\Ubicaciones`**, que entrega al formulario los países y los departamentos **con sus
  ciudades dentro**, más el código de Colombia. Es el servicio que resuelve la regla 5.1.1 en un solo
  sitio para las tres pantallas que la van a pedir.
- **`SelectorUbicacion.vue`**: país de lista; si es Colombia, departamento y ciudad encadenados; si no,
  los dos como texto libre. Limpia lo de abajo al cambiar lo de arriba. Se monta en
  `/sistema-de-diseno` con el catálogo real cargado, no con tres ciudades de mentira.
- **`Festivo::esFestivo()`, `fechasDelAnio()` y `aniosCargados()`**, que es lo que el motor de cálculo
  y el aviso del panel van a consultar. Solo lectura del calendario: las tres reglas de conteo son de
  la fase 2 y no se adelantaron.
- **`tests/Feature/CatalogosTest.php`** (15 casos) y **`SelectorUbicacion.test.js`** (9 casos).
  `composer verificar` en verde: 129 pruebas de Pest, PHPStan sin errores, build y 70 de Vitest.

## Decisiones tomadas

- **Las cinco listas de entidades quedaron vacías a propósito.** Su contenido es la decisión D.5,
  marcada bloqueante y de Angela. Una lista de EPS inventada por el programador es peor que una lista
  vacía: la vacía se nota el primer día, la inventada se usa meses y acaba en los datos de todos los
  clientes. La tabla, el modelo y el seeder ya existen; solo faltan las filas.
- **Los seeders escriben con el query builder, no con Eloquent.** El observador de auditoría de F0-08
  se engancha a los eventos de *todos* los modelos: cargar 1.101 municipios con `create()` dejaría
  1.101 líneas de auditoría de algo que no hizo ningún usuario. Cuando una ciudad se edite desde el
  panel sí pasará por el modelo y sí quedará auditada, que es cuando importa.
- **Los cuatro seeders son repetibles** (`upsert` por código ISO, código DANE, nombre dentro del
  departamento y fecha). Cargar el año siguiente de festivos, o corregir el nombre de un municipio,
  es volver a correrlos. Hay prueba de que no duplican.
- **Una fila por fecha festiva, no una por celebración.** El 30 de junio de 2025 caen San Pedro y el
  Sagrado Corazón: van juntos en una fila. Al motor de cálculo le importa si el día es festivo, no
  cuántas fiestas hay en él.
- **`festivos` guarda también el año**, redundante con la fecha y a propósito: el aviso de «falta
  cargar el año siguiente» y la pantalla del panel consultan por año, y agrupar por columna indexada
  es mejor que envolver la fecha en una función en cada consulta.
- **Las ciudades viajan anidadas dentro de su departamento**, no en una lista plana. Así el
  encadenamiento es un filtro en el navegador y no una petición al servidor por cada departamento que
  el usuario prueba. Son mil cien municipios: caben de sobra en las props.
- **Bogotá D.C. es un departamento más**, con código 11 y una sola ciudad. En el formulario se
  comporta igual que los demás y tratarlo aparte solo agregaría un caso especial.
- **`codigo_dane` de los municipios quedó nulo.** Los códigos de cinco dígitos no se inventan: la
  columna existe y se llena cuando se cargue el archivo oficial del DANE desde el panel (F1B-04).

## Preguntas para Angela

- **BLOQUEANTE, decisión D.5 — el contenido de las cinco listas maestras.** Es lo único que le falta
  a F0-11. Se necesitan, en orden de urgencia: **EPS** (las vigentes hoy), **fondos de pensión**,
  **ARL**, **cajas de compensación** (varían por departamento: ¿se ofrecen todas o se filtran por el
  departamento del empleado?) y **entidades bancarias**. Llegan como una lista de nombres; el sistema
  ya está listo para recibirlas y para que después se administren sin desplegar código.
- Del mismo D.5, para cuando toquen sus tareas: **tipo de riesgo** (¿«I» o «Nivel I — riesgo mínimo»?),
  **género**, **nivel educativo**, **estado civil**, **medio de transporte** y **tiempo de
  desplazamiento**. No bloquean todavía; bloquean en F1-04 y F1-06.

## Decisiones para David

- **Verificar el calendario de festivos antes de vender.** Las tres anualidades se escribieron y se
  revisaron una a una, y hay pruebas de que los diez trasladables caen en lunes y de que los fijos no
  se movieron, pero la instrucción del documento es verificación humana contra el calendario oficial.
  Son cincuenta y tres fechas y quince minutos. **Es la tabla que, si falla, falla para todos los
  clientes a la vez.**
- **Contrastar los 1.101 municipios contra el archivo del DANE.** Un municipio que falte no rompe
  nada, pero deja a un empleado sin poder registrar dónde nació. Ese mismo cotejo trae los códigos
  DANE que hoy están nulos.
- **Vigencia del enlace de invitación: siete días.** Sigue abierta desde F0-07. Está en
  `Invitaciones::DIAS_DE_VIGENCIA`, en un solo sitio. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Sigue abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`.
- Sigue abierta de diez sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo sensible).
  Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()` copiando
  `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a mano, y
  que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-01 · Personas y vinculaciones**, y es la primera tabla de negocio de verdad:
  copia `database/stubs/migracion-tabla-de-negocio.stub` —`tenant_id`, índice con la empresa primero,
  `softDeletes()` y `PoliticaRls::activarEn()` en la misma migración— y **suma su caso a
  `AislamientoTest`**. Los catálogos de esta sesión no lo hacen porque son globales; `personas` sí.
- **El documento es único por empresa en `personas`, no en `vinculaciones`.** Es la regla 5 del
  proyecto y donde se equivoca todo el mundo.
- **`personas` no guarda texto de ciudad: guarda el identificador del catálogo** cuando el país es
  Colombia, y texto libre cuando no lo es. Son dos columnas distintas o una columna más una bandera;
  se decide al escribir la migración, pero mezclando ambos en la misma columna no.
- **El formulario de ubicación ya está resuelto**: `SelectorUbicacion` más
  `Ubicaciones::paraFormulario()`. La residencia se monta con `solo-colombia`, porque el sistema solo
  gestiona contratos laborales colombianos.
- **Cargar el calendario del año siguiente es una tarea anual del proveedor**: se agregan las fechas a
  `database/data/festivos.php`, se verifican y se vuelve a correr `FestivosSeeder`. En F1B-04 hay que
  construir el aviso que lo recuerda; `Festivo::aniosCargados()` ya está para eso.
- Toda pantalla nueva se envuelve en `Armazon`, y la regla de los colores literales sigue intacta.
