# Estado del proyecto

**Última sesión:** 2026-08-18 · Tarea F0-10
**Verificación:** verde
**Siguiente tarea:** F0-11 · Catálogos y festivos

## Qué se hizo

- **`resources/js/Components/Armazon.vue`**, el armazón del documento 3, sección 4.1: barra lateral a
  la izquierda, encabezado arriba y el contenido en el `slot`. Toda pantalla del sistema se envuelve
  aquí. Lee la prop compartida `usuario` con `usePage()`; no recibe nada por props ni decide nada.
- **`resources/js/Components/BarraLateral.vue`**, el menú con los módulos agrupados como el prototipo
  (Inicio suelto, luego Personal, Novedades, Movimientos y Sistema). Resalta la sección activa,
  incluidas las rutas hijas —`/empleados/7` deja activo Empleados—, y avisa con `navegar` para que el
  menú desplegado se cierre.
- **`resources/js/Components/EncabezadoApp.vue`**, la franja de 54 px de la sección 4.3: empresa y NIT
  a la izquierda, y a la derecha el usuario con avatar de iniciales y su menú de perfil con cierre de
  sesión. El botón de menú lateral solo existe por debajo de 1024px.
- **`Modulo::grupo()`, `Modulo::url()` y `Modulo::nombreDeRuta()`**, para que el nombre del módulo, su
  dirección y su permiso sean el mismo texto. `HandleInertiaRequests` ahora comparte cada módulo como
  `{clave, etiqueta, grupo, url}` y suma el `nit` de la empresa.
- **Las nueve secciones del menú son rutas reales** en `routes/web.php`, registradas recorriendo
  `Modulo::cases()`, cada una con su `middleware('modulo:…')` y su página `SeccionPendiente.vue`. El
  contenido llega en su tarea de fase 1 o 2; la navegación y el 403 son los definitivos.
- **`Inicio.vue` dejó de ser una pantalla suelta**: se monta en el armazón y muestra el saludo y la
  fecha de la sección 5.2. La fecha llega escrita desde el servidor.
- **`tests/Feature/EstructuraTest.php`** (9 casos) y tres archivos de Vitest nuevos —`BarraLateral`,
  `EncabezadoApp`, `Armazon`— con 22 casos. `composer verificar` en verde: 114 pruebas de Pest,
  PHPStan sin errores, build y 61 de Vitest.

## Decisiones tomadas

- **Un módulo sin permiso no llega al navegador, no se esconde en él.** El menú pinta lo que trae la
  prop compartida, que sale de `PoliticaDeModulos`, la misma que responde 403. Menú y servidor no
  pueden discrepar porque no hay dos fuentes.
- **Las secciones vacías existen desde ya, con su middleware.** Un menú cuyas opciones no llevan a
  ninguna parte no se puede probar. Así `EstructuraTest` comprueba el 403 sobre la ruta real
  —`/vacaciones`—, no sobre una ruta de juguete registrada dentro de la prueba.
- **`grupo()` convive con `seccion()` y no la reemplaza.** `seccion()` es lo que se le enseña al
  invitado en el resumen de sus permisos; `grupo()` es el encabezado del menú. Coinciden hoy en
  Novedades y en nada más: Movimientos y Terminaciones caen bajo un grupo «Movimientos» que no es la
  sección de ninguno de los dos.
- **La fecha del saludo se arma en el servidor** con `translatedFormat`. Formateada en el navegador
  saldría en el reloj y el idioma de cada máquina, no en `America/Bogota`.
- **El armazón lee `usePage()` en vez de recibir props.** Si cada pantalla tuviera que pasarle el
  usuario y los módulos, la primera que lo olvide deja el sistema sin menú.
- **En pantalla pequeña se monta una segunda barra lateral, no se mueve la primera.** Es el mismo
  componente; la de escritorio simplemente no existe por debajo de 1024px. Se cierra con la tecla de
  escape, con el fondo, con su botón y al elegir una sección.
- **El armazón no entra en `/sistema-de-diseno`.** Esa página es el catálogo de los once componentes
  y no pide sesión; meterle el menú de un usuario que no existe solo la rompería.

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
- Sigue abierta de nueve sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo sensible).
  Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()` copiando
  `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a mano
  —cambiar de empresa es siempre `paraTenant()`—, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3, que es la fuente. Si prefieres el texto largo del prototipo, se cambia en
  `Modulo::etiqueta()` y en ningún otro sitio.

## Notas para la próxima sesión

- La siguiente es **F0-11 · Catálogos y festivos**: tablas globales —países, departamentos, ciudades,
  EPS, fondos, ARL, cajas, bancos y festivos—. **Ninguna lleva `tenant_id` ni el trait**; están en la
  lista de tablas globales de `.claude/rules/10-multiempresa.md`, así que tampoco suman caso a
  `AislamientoTest`.
- **Toda pantalla nueva se envuelve en `Armazon`.** No hay que pasarle nada: el menú, la empresa y el
  usuario los saca de la prop compartida.
- **Cuando un módulo estrene contenido de verdad**, se le quita del bucle de `routes/web.php` y se le
  da su propio controlador. El bucle es el andamio de las secciones que aún no existen, no el destino.
- **Los tres componentes del armazón no están en `/sistema-de-diseno`.** No son piezas que se elijan
  al construir una pantalla: son el marco donde la pantalla se mete. La prueba de los once componentes
  base sigue exigiendo solo los del capítulo 3.4.
- La regla de los colores literales sigue intacta: los cinco tonos del menú viven en `app.css` como
  `--color-menu-*`.
- Sigue pendiente para F1-01: la migración de `personas` copia
  `database/stubs/migracion-tabla-de-negocio.stub` y suma su `casoDeAislamiento()`.
