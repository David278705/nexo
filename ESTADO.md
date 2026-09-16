# Estado del proyecto

**Última sesión:** 2026-09-16 · Tarea `HD-07`
**Verificación:** verde. 1.264 de 1.264 pruebas de Pest, Pint y PHPStan sin errores, build y 409 de
Vitest — tres más que `HD-06`, las que se sumaron a `SelectorUbicacion.test.js`.
**Siguiente tarea:** `HD-08` · La plantilla de empleados pide el lugar de expedición en una sola
columna.

## Qué se hizo

`HD-07` pedía arreglar la guía alrededor de Bogotá/Cundinamarca sin tocar el catálogo (documento 6,
D.7 ya lo dejó decidido): quien busca Cundinamarca porque nació o vive en Bogotá no la encuentra
debajo, porque el DANE la trata como su propio departamento y el catálogo ya lo refleja bien.

- **`SelectorUbicacion.vue`, el componente compartido**, avisa en cuanto el departamento elegido es
  Cundinamarca: un `Aviso` debajo del selector de ciudad explica que Bogotá se busca como
  «Bogotá D.C.». Es un solo sitio porque el componente ya es compartido por las tres ubicaciones del
  asistente de empleado (nacimiento, expedición, residencia) y por la ciudad de `CrearEmpresa` del
  panel del proveedor: el aviso llega a las cuatro sin duplicar nada.
- **El buscador de departamentos ya encontraba «Bogotá D.C.» al escribir «Bogotá»**, sin que hiciera
  falta tocar nada: `Selector` filtra opciones por coincidencia de texto y «Bogotá» es una
  subcadena de «Bogotá D.C.». Quedó con una prueba nueva sobre una lista realista de departamentos
  (más de diez, el umbral que activa el buscador) para que quede protegido y no sea un accidente.
- **La carga masiva explica en vez de solo rechazar.** `PlantillaDeEmpleados` gana `esBogota()`,
  que reconoce «Bogotá» y «Bogotá D.C.» escritos con o sin puntuación —y **nada más**: ninguna otra
  forma ni abreviatura, que sería adivinar lo que `TextoDeCelda` explícitamente no hace—, y
  `motivoDeBogota()`, que arma la explicación y dice la columna exacta donde escribir «Bogotá D.C.».
  Se conecta en los tres puntos donde un departamento o un municipio pueden no resolver: el
  departamento de nacimiento o residencia (`enColombia()`), el municipio dentro de un departamento ya
  resuelto —el caso de David, Cundinamarca con «Bogotá» de ciudad— (`municipioDe()`), y el lugar de
  expedición de una sola celda (`expedicion()`), con y sin el departamento entre paréntesis.
- **La plantilla real de `plantillas/1 - Empleados actuales (obligatoria).xlsx` también se
  actualizó**, con el método seguro que dejó anotado `HD-06`: editar el XML de `xl/worksheets/sheet1.xml`
  a mano con `ZipArchive::addFromString()`, nunca cargar y volver a guardar el libro completo. Las
  instrucciones de «Departamento de nacimiento» (antes vacía), «Departamento de residencia» y «Lugar
  de expedición del documento» ahora advierten lo mismo. Se comparó archivo por archivo contra el
  original antes de dejarlo: solo cambió `sheet1.xml`, los comentarios de `xl/comments/comment1.xml`
  quedaron bit a bit idénticos.

## Decisiones tomadas

- **El catálogo no se tocó**, como manda D.7 con esas palabras. Toda la tarea fue guía, no datos.
- **El reconocimiento de «Bogotá» es literal y no adivina variantes.** Solo «Bogotá» y «Bogotá D.C.»
  (con o sin tildes, mayúsculas o puntuación) disparan el mensaje específico. No se agregó «Distrito
  Capital», «D.C.» a secas ni ninguna otra forma que nadie pidió: es la misma prudencia de
  `TextoDeCelda`, que ya declara que no corrige errores de escritura ni adivina abreviaturas.
- **El aviso del selector vive en `SelectorUbicacion`, no en `Selector`.** `Selector` es genérico —lo
  usan filtros, EPS, bancos— y no sabe qué opción es Cundinamarca. `SelectorUbicacion` sí conoce la
  lista de departamentos por su `texto`, así que ahí es donde el aviso tiene sentido y se comparte
  automáticamente con las cuatro pantallas que ya lo usan.

## Preguntas para Angela

- (vacío, no cambia respecto a la sesión anterior)

## Decisiones para David

- **`HD-05` sigue bloqueada**, esperando el calendario de festivos de 2016-2025 (o 2015-2024, falta
  fijar el rango) verificado a mano. Ver la sesión de `HD-06` para el detalle.
- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway,
  en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Con `MAIL_MAILER=log` en local, ninguna alerta sale de la máquina de verdad.
- **Verificaciones manuales heredadas y todavía sin hacer**: la ficha de empresa del proveedor tras
  REV-12, el formulario de acceso de prueba tras F5-08, la pestaña «Facturación» de REV-11, el flujo
  completo de REV-10, la de `HD-04` (incapacidad que interrumpe vacaciones ya registradas), la de
  `HD-06` (celular y correo en el asistente, y que la plantilla real abra sin errores en Excel de
  verdad) y ahora también `HD-07`: abrir el asistente de registro, elegir Cundinamarca como
  departamento y comprobar a ojo que el aviso de Bogotá se ve bien, y que la plantilla real
  descargable de `plantillas/` sigue abriendo sin errores en Excel de verdad (aquí solo se verificó
  con PhpSpreadsheet y comparando el `.zip` byte a byte contra el original, no con Excel).
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-08` es la siguiente**: separar el lugar de expedición del documento en «Departamento de
  expedición» y «Ciudad de expedición», igual que ya están nacimiento y residencia, y dejar que
  quien expidió el documento fuera de Colombia escriba cada uno en su columna sin paréntesis. Nota
  de la propia tarea: el orden de columnas ya quedó bien por `REV-08`, solo hay que comprobarlo.
- **`HD-08`, `HD-09` y `HD-10` tocan las tres la plantilla de empleados**, igual que ya pasó con
  `HD-06` y `HD-07`. El método seguro para tocar el `.xlsx` real sigue siendo el mismo: copiarlo
  aparte primero, editar el XML del `.zip` a mano con `ZipArchive::addFromString()` —nunca cargar y
  volver a guardar el libro completo con `PhpOffice\PhpSpreadsheet\Writer\Xlsx`, que corrompe la
  tabla de cadenas y borra los comentarios en este archivo en particular—, y verificar con
  PhpSpreadsheet **y** comparando cada entrada del `.zip` contra el original antes de dejarlo. Si
  `HD-08` separa el lugar de expedición en dos columnas, la función `expedicion()` de
  `PlantillaDeEmpleados` deja de tener sentido tal como está —hoy vive de que es una sola celda— y
  `motivoDeBogota()` de esta sesión probablemente se simplifica: con departamento y ciudad separados
  ahí también, el caso de expedición se vuelve igual al de nacimiento y residencia.
- **`./vendor/bin/pest` a secas se queda sin memoria** en la exportación a Excel, con el
  `memory_limit` de 128M del php.ini de esta máquina. `composer verificar` no: corre con el límite
  que le pone Composer. Para correr la batería entera, `composer verificar` o
  `php -d memory_limit=1G vendor/bin/pest`.
- **Un archivo de pruebas de Feature corrido solo, fuera de `composer verificar`, puede fallar por
  una función global no cargada** (por ejemplo `ownerEnElPanel()`, definida en `CrearEmpresaTest.php`
  y usada también en `CargaInicialTest.php`): Pest solo la resuelve cuando descubre el árbol completo
  de pruebas. No es una prueba rota; es correr un archivo aislado. `composer verificar` la corre
  siempre completa y ahí sí queda verde.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
