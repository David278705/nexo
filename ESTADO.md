# Estado del proyecto

**Última sesión:** 2026-08-18 · Tarea F0-09
**Verificación:** verde
**Siguiente tarea:** F0-10 · Estructura de la aplicación

## Qué se hizo

- **`resources/css/app.css` es ahora el sistema de diseño completo** y el único archivo del frontend
  donde se escribe un color: los nueve de la tabla 3.1, sus variantes tonales (fondo, borde y texto
  para información, éxito, advertencia y error), los seis tamaños tipográficos como `--text-*`
  (`text-titulo`, `text-seccion`, `text-cuerpo`, `text-tabla`, `text-apoyo`, `text-cifra`), los radios,
  las medidas de control (38 px) y de fila (44 px), los tres anchos (`contenido` 1280, `modal-corto`
  560, `modal-ancho` 800) y la sombra de lo que flota. Más una regla base de `:focus-visible`.
- **Los once componentes de la sección 3.4**, en `resources/js/Components/`: `TablaDatos`,
  `BarraFiltros`, `Modal`, `CampoTexto`, `CampoFecha`, `Selector`, `Etiqueta`, `Aviso`, `EstadoVacio`,
  `TarjetaIndicador` y `Notificacion`. `Boton` sumó sus cuatro variantes (primario, secundario, texto,
  destructivo). `CampoTexto`, `Boton` y `Aviso` se revisaron, no se rehicieron.
- **`resources/js/Pages/SistemaDeDiseno.vue`**, la página de muestra con los once componentes, la
  paleta y la escala tipográfica. Su ruta `/sistema-de-diseno` solo se registra fuera de producción.
- **`tests/Feature/SistemaDeDisenoTest.php`** (5 casos): los once componentes existen, los once están
  en la muestra, la muestra se sirve, la paleta y los seis tamaños están en `app.css`, y **ningún
  archivo de `resources/js` escribe un color literal** —ni hexadecimal, ni `rgb()`, ni una clase de la
  paleta de fábrica de Tailwind—.
- **Cuatro archivos de Vitest** (`TablaDatos`, `Selector`, `Modal`, `Notificacion`, `Formularios`) con
  28 casos nuevos. `composer verificar` en verde: 105 pruebas de Pest, PHPStan sin errores, build y
  39 de Vitest.

## Decisiones tomadas

- **Cada tono tiene fondo, borde y texto propios, no uno solo.** El `#B45309` de la tabla 3.1 sobre su
  propio fondo claro no llega a 4,5:1, que es el mínimo que exige la sección 3.5. Los valores de las
  tres variantes salen de `prototipos/sistema.html`, que ya los tenía resueltos.
- **`--color-texto-invertido` en lugar de `text-white`.** Es blanco, sí, pero escribirlo como blanco
  deja un color literal en el componente y abre la puerta al siguiente. Con la variable, la prueba
  puede prohibir el literal sin excepciones que haya que recordar.
- **La página de muestra nombra la clase, no el hexadecimal.** Si la muestra dijera `#123A5C` habría
  dos verdades sobre el mismo color y una acabaría vieja. Dice `bg-marca`, que además es lo que el
  programador va a escribir.
- **La barra de progreso de Inertia lee la variable en tiempo de ejecución** con
  `getComputedStyle(...).getPropertyValue('--color-accion')`. Estaba en `#4f46e5`, un índigo que no es
  de la paleta y que se veía en cada navegación.
- **`CampoFecha` envuelve a `CampoTexto` con `tipo="date"`.** El control nativo trae calendario y deja
  escribir la fecha, que es lo que pide 3.4, y así etiqueta, ayuda, error y medidas viven en un sitio.
- **La búsqueda del `Selector` aparece sola a partir de once opciones**, sin que nadie la pida: filtra
  un `<select>` nativo en vez de reinventar un desplegable, que es donde se pierde la accesibilidad.
- **La fila de `TablaDatos` es clicable con ratón y con teclado** (`tabindex="0"`, Enter y espacio).
  «La fila completa es clicable» sin teclado deja la tabla fuera del recorrido de la sección 3.5.
- **La ruta de la muestra no está bajo `auth`.** No lleva datos de nadie y no existe en producción;
  pedir sesión solo estorbaría a quien la usa, que es quien está programando.

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
- Sigue abierta de ocho sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo sensible).
  Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()` copiando
  `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a mano
  —cambiar de empresa es siempre `paraTenant()`—, y que el rol de conexión no puede ser superusuario.

## Notas para la próxima sesión

- La siguiente es **F0-10 · Estructura de la aplicación**: barra lateral, encabezado y comportamiento
  en pantallas pequeñas. Los módulos que puede ver el usuario **ya vienen compartidos como prop desde
  F0-06** (hay prueba de ello en `RolesYPermisosTest`); el menú los pinta, no los vuelve a calcular.
- **Cuando F0-10 haga el armazón, `Inicio.vue` deja de ser una pantalla suelta** y pasa a usarlo. Hoy
  centra un título en la pantalla porque no había dónde colgarlo.
- **La prueba de colores literales es la que sostiene el sistema de diseño.** Si una pantalla nueva
  necesita un color que no está, se agrega la variable a `app.css` y se usa por nombre; no se escribe
  el hexadecimal ni se agrega una excepción a la prueba.
- **`/sistema-de-diseno` es el sitio donde se mira un componente antes de usarlo.** Todo componente
  compartido que nazca de aquí en adelante se agrega a esa página, y la prueba de los once lo exige
  para los que ya están.
- La paginación de vendor de Laravel mete clases `text-gray-*` en el CSS compilado. Viene de las
  vistas Blade del framework, no del código propio, y por eso la prueba solo recorre `resources/js`.
- Sigue pendiente para F1-01: la migración de `personas` copia
  `database/stubs/migracion-tabla-de-negocio.stub` y suma su `casoDeAislamiento()`.
