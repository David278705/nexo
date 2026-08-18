# Estado del proyecto

**Última sesión:** 2026-08-18 · Tarea F1-01
**Verificación:** verde
**Siguiente tarea:** F1-02 · Periodos contractuales
*(F0-11 sigue `[!]`: solo le falta el contenido de las cinco listas de entidades, que depende de la
decisión D.5. Se retoma sola cuando Angela responda.)*

## Qué se hizo

- **Las tres primeras tablas de negocio del sistema**, cada una en su migración y las tres con el
  patrón completo del stub: `tenant_id` con su clave foránea, `tenant_id` primero en todo índice
  compuesto, `softDeletes()` y `PoliticaRls::activarEn()` **en la misma migración que crea la tabla**.
  Una prueba comprueba las tres cosas tabla por tabla, incluida la política, leyéndola de
  `pg_policies`.
- **`personas`**: el ser humano. Documento, nombres, nacimiento, expedición del documento, género y
  residencia. **El documento es único por empresa aquí**, con un índice `unique` parcial
  —`where deleted_at is null`— sobre `(tenant_id, numero_documento)`.
- **`vinculaciones`**: la relación laboral. Cargo, tipo de contrato, fechas, salario, bonificación,
  las cinco claves foráneas de seguridad social y banco, y el estado. **Sin columna de saldo de
  vacaciones**, y hay una prueba que se pondría en rojo si alguien la agregara.
- **`perfil_sociodemografico`**: el bloque opcional de 5.2, colgado de la **persona** y con todas sus
  columnas nulas a propósito.
- **Cuatro enumerados en `app/Contratacion/`**: `TipoDocumento` (los cinco de 5.1, con la tarjeta de
  identidad incluida), `TipoContrato` (los tres, con `exigeFechaFin()` y `admiteRenovacion()`),
  `EstadoVinculacion` y `TipoVivienda`. Todos se guardan como texto legible, no como número.
- **Tres modelos** —`Persona`, `Vinculacion`, `PerfilSociodemografico`— con `PerteneceATenant`,
  borrado lógico, sus relaciones y sus casteos. `Persona::nombreCompleto()` y el scope
  `conDocumento()`, que es el primer paso de todo registro de empleado.
- **Cinco fábricas nuevas** —`Persona`, `Vinculacion`, `PerfilSociodemografico`, `Ciudad` y
  `Departamento`— con los estados que hacen falta: `nacidaEnElExterior()`, `sinSegundosNombres()`,
  `aTerminoFijo()`, `deObraOLabor()`, `terminada()` y `vacio()`.
- **`tests/Feature/PersonasYVinculacionesTest.php`** (22 casos) y **tres casos nuevos en
  `AislamientoTest`** —personas, vinculaciones y perfil— que son quince pruebas más de aislamiento.
  `composer verificar` en verde: 166 pruebas de Pest, PHPStan sin errores, build y 70 de Vitest.

## Decisiones tomadas

- **El índice único del documento es parcial: solo alcanza a las personas vivas.** En este sistema no
  se borra de verdad, así que sin el `where deleted_at is null` una persona eliminada por error
  reservaría su documento para siempre y la empresa no podría volver a registrarla nunca. Lo mismo en
  el índice de una fila de perfil por persona. Hay prueba de los dos comportamientos.
- **El departamento no se guarda: se llega a él por la ciudad.** La ciudad del catálogo ya sabe de qué
  departamento es; repetirlo sería tener dos verdades que se pueden contradecir. Cuando el país no es
  Colombia sí hay dos columnas de texto libre `*_exterior`, que es la otra mitad de la regla 5.1.1.
  **Nunca un identificador y un nombre en la misma columna**: eso obliga meses después a adivinar cuál
  de las dos cosas hay dentro.
- **Las tres ubicaciones de una persona tienen la misma forma**: nacimiento, expedición del documento
  y residencia. La residencia no lleva par de columnas del exterior porque siempre es en Colombia.
- **El estado de la vinculación no está en `fillable`.** Es la regla 5.3: no lo digita nadie. Nace
  activo por el valor por defecto de la columna y solo lo cambia `desactivar()`, que llamará el módulo
  de terminaciones. Una prueba comprueba que un `fill()` con `estado` no lo cambia.
- **`saldo_inicial_vacaciones` y `fecha_corte_saldo` sí existen, y no rompen la regla 2.** No son un
  saldo que se sume y se reste: son el punto de partida que trae la carga inicial, tal como lo define
  la sección 3.4 del documento 2. El saldo se sigue calculando entero.
- **Lo que depende de D.5 quedó nulo, no inventado.** `genero`, `tipo_riesgo`, las cinco claves
  foráneas de EPS, fondos, ARL, cajas y bancos, y las cuatro listas del perfil. Son campos
  obligatorios del formulario, y esa obligatoriedad se aplicará en la validación del servidor en F1-04
  y F1-06, cuando las listas tengan filas que elegir. La forma de las tablas no depende de la
  respuesta; su contenido sí.
- **`tipo_vivienda` y `estrato` sí tienen enumerado y rango** porque sus opciones están decididas en el
  propio documento 1, no en D.5.
- **El nombre de la tabla del perfil va en singular**, `perfil_sociodemografico`, contra la convención
  de plurales: es una fila por persona y así lo nombra el backlog.
- **La fábrica de departamentos busca el primer código DANE libre en la base de datos** en lugar de
  pedírselo a `faker->unique()`: solo hay cien códigos de dos dígitos y la memoria del generador dura
  toda la ejecución de las pruebas, mientras que la base se vacía entre una y otra.

## Preguntas para Angela

- **BLOQUEANTE, decisión D.5 — el contenido de las cinco listas maestras.** Es lo único que le falta a
  F0-11. Se necesitan, en orden de urgencia: **EPS** (las vigentes hoy), **fondos de pensión**,
  **ARL**, **cajas de compensación** (varían por departamento: ¿se ofrecen todas o se filtran por el
  departamento del empleado?) y **entidades bancarias**. Llegan como una lista de nombres; el sistema
  ya está listo para recibirlas y para que después se administren sin desplegar código.
- Del mismo D.5, y ya con las columnas esperándolas: **tipo de riesgo** (¿«I» o «Nivel I — riesgo
  mínimo»?), **género**, **nivel educativo**, **estado civil**, **medio de transporte** y **tiempo de
  desplazamiento**. **Bloquean en F1-04 y en F1-06**, que son dos y cuatro tareas más adelante.

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
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`. Ahora hay tres tablas de negocio
  dependiendo de ello, no una de juguete.
- Sigue abierta de once sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo sensible).
  Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()` copiando
  `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a mano, y
  que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-02 · Periodos contractuales**, y se apoya entera en lo de esta sesión:
  `periodos_contractuales` cuelga de `vinculaciones` con `orden`, `evento`, `tipo_contrato` y las dos
  fechas. Es tabla de negocio: copia el stub y **suma su caso a `AislamientoTest`**, que ya tiene tres
  ejemplos nuevos de cómo se escribe.
- **El `evento` de la fila incluye `prorroga_pactada` y `prorroga_automatica`**, que es la decisión D.1
  pendiente. El modelo las contempla desde ya; si Angela decide no distinguirlas, el campo queda con un
  solo valor y no hay nada que rehacer. **No hay que adelantar el conteo de prórrogas cortas**: eso es
  F1-10 y depende además de B.1 y B.3.
- **`vinculaciones.tipo_contrato` y `fecha_fin` guardan lo vigente hoy**, que es lo que se ve en los
  listados. El tramo a tramo es de la tabla nueva. Cuando F1-09 construya «Renovar», tiene que mover
  las dos cosas a la vez: la fila nueva del historial y la fecha vigente de la vinculación.
- **El registro de empleado nunca crea una persona a ciegas**: primero `Persona::conDocumento()`; si
  existe, se reutiliza y se le crea una vinculación nueva —ahí entra la advertencia de F1-05—; si no
  existe, se crean las dos. El scope ya está escrito.
- Toda pantalla nueva se envuelve en `Armazon`, la regla de los colores literales sigue intacta, y el
  formulario de ubicación ya está resuelto con `SelectorUbicacion` más `Ubicaciones::paraFormulario()`.
