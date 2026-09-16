# Estado del proyecto

**Última sesión:** 2026-09-16 · Tarea `HD-06`
**Verificación:** verde. 1.261 de 1.261 pruebas de Pest, Pint y PHPStan sin errores, build y 405 de
Vitest — tres más que `HD-05`, las que se sumaron a `EmpleadosTest.php`, `RegistroDeEmpleadoTest.php`
y `FichaDeEmpleadoTest.php`.
**Siguiente tarea:** `HD-07` · Bogotá está bajo «Bogotá D.C.» y quien busca Cundinamarca no la
encuentra.

## Qué se hizo

`HD-06` pedía que `personas` guardara celular y correo, con el asistente exigiéndolos con su
formato, la plantilla de carga trayendo sus dos columnas, la ficha enseñándolos y el listado y la
exportación incluyéndolos.

- **Migración nueva** (`2026_09_16_100000_add_celular_y_correo_a_personas_table.php`) que agrega las
  dos columnas **nulas**. No es lo mismo que «opcionales»: hay ocho personas ya registradas en este
  entorno (de sesiones de prueba anteriores) y nadie les inventa un celular o un correo hacia atrás —
  es el mismo criterio que `F1-06` aplicó al perfil sociodemográfico cuando se activa después de
  registrada la persona. De aquí en adelante sí son obligatorias, pero esa obligatoriedad vive en la
  validación, no en la columna.
- **`RegistrarEmpleado::contacto()`**, el método nuevo: `celular` con `regex:/^[0-9]{10}$/` —diez
  dígitos, sin indicativo ni separadores, que es literalmente lo único que pedía el «hecho cuando», sin
  inventar que deba empezar en 3 o llevar otro formato— y `correo` con la regla `email` de Laravel.
  `RegistroDeEmpleado` los escribe en la persona junto con el resto de sus datos.
- **La plantilla de carga masiva no copió las reglas**: `PlantillaDeEmpleados::columnas()` ganó
  `Celular` y `Correo electrónico`, y como `reglas()` arma sus condiciones llamando a
  `RegistrarEmpleado::rules()`, las dos columnas quedan validadas igual que en el formulario sin
  escribir una sola regla nueva en la plantilla.
- **El `.xlsx` real de `plantillas/1 - Empleados actuales (obligatoria).xlsx` también se actualizó**,
  no solo el código: es el archivo que se le entrega al cliente y el que
  `CargaInicialTest` compara contra `columnas()`. Editarlo con PhpSpreadsheet (cargar y volver a
  guardar el libro) **corrompe el archivo**: dos ediciones distintas, una con `duplicateStyle` y otra
  con `setCellValue` a secas, dejaron la tabla de cadenas compartidas con un índice de menos
  (`uniqueCount="104"` pero 103 `<si>` escritos) y **borraron por completo los comentarios de las
  celdas** (`xl/comments/comment1.xml` desaparece al guardar). La causa es que el archivo original no
  usa tabla de cadenas compartidas —cada celda es `t="inlineStr"`— y el escritor de PhpSpreadsheet no
  hace bien la conversión cuando el libro tiene comentarios. La solución fue editar el XML interno del
  `.zip` a mano con `ZipArchive`, agregando las celdas, las columnas y la fila de instrucciones nuevas
  como texto plano, sin pasar el libro completo por el escritor. Quedó verificado célula por célula con
  PhpSpreadsheet después, y los comentarios siguen intactos.
- **Dónde se enseñan**: la ficha los suma al bloque «Residencia» de la pestaña de datos personales,
  junto a la dirección. El listado y la exportación los agregan **al final**, después de cargo, tipo de
  contrato y fecha de ingreso — no detrás del nombre — por dos reglas que ya existían y que no había
  que romper: las tres columnas obligatorias de toda exportación son documento, nombre y cargo en ese
  orden (`ExportacionesYFiltrosTest.php`, documento 3, 6.1), y en móvil la tabla se reduce a las tres
  primeras columnas (documento 3, 6.4), que siguen siendo documento, nombre y cargo.

## Decisiones tomadas

- **Las columnas nacen nulas y no rellenadas hacia atrás.** Ver arriba. Es la misma decisión de
  `F1-06`, aplicada a un caso nuevo.
- **El formato del celular es exactamente «diez dígitos», sin indicativo ni prefijo `3`.** El «hecho
  cuando» de la tarea no pedía más que eso, y agregar una regla de indicativo colombiano habría sido
  inventar un valor legal que nadie pidió.
- **Celular y correo van al final de la fila, no junto al nombre.** Ninguna fuente lo decía; se
  resolvió mirando las dos reglas de interfaz que sí existían (las tres columnas obligatorias de toda
  exportación y las tres columnas de la tarjeta móvil) y colocándolos donde no las rompieran.

## Preguntas para Angela

- (vacío, no cambia respecto a la sesión anterior)

## Decisiones para David

- **`HD-05` sigue bloqueada**, esperando el calendario de festivos de 2016-2025 (o 2015-2024, falta
  fijar el rango) verificado a mano. Ver la sesión anterior para el detalle.
- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway,
  en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Con `MAIL_MAILER=log` en local, ninguna alerta sale de la máquina de verdad.
- **Verificaciones manuales heredadas y todavía sin hacer**: la ficha de empresa del proveedor tras
  REV-12, el formulario de acceso de prueba tras F5-08, la pestaña «Facturación» de REV-11, el flujo
  completo de REV-10, la de `HD-04` (incapacidad que interrumpe vacaciones ya registradas) y ahora
  también `HD-06`: abrir el asistente de registro y comprobar a ojo que el celular y el correo se ven
  bien, y que la plantilla real descargable de `plantillas/` abre sin errores en Excel de verdad
  (aquí solo se verificó con PhpSpreadsheet, no con Excel).
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-07` es la siguiente**: la guía de Bogotá/Cundinamarca en los selectores y en la carga masiva
  (decisión D.7 ya tomada, el catálogo no se toca).
- **`HD-07`, `HD-08` y `HD-09` tocan las tres la plantilla de empleados**, igual que ya pasó con
  `HD-06`. Si vuelve a hacer falta tocar el `.xlsx` real, el método seguro es el que se usó hoy: editar
  el XML del `.zip` a mano con `ZipArchive::addFromString()`, nunca cargar y volver a guardar el libro
  completo con `PhpOffice\PhpSpreadsheet\Writer\Xlsx` — ese archivo en particular tiene comentarios en
  las celdas y usa cadenas en línea (`inlineStr`) en vez de una tabla de cadenas compartidas, y el
  escritor no hace bien esa conversión: pierde los comentarios y deja una tabla de cadenas con un
  índice de menos que corrompe la lectura posterior. Antes de tocarlo, copiarlo aparte y probar el
  cambio ahí primero.
- **`./vendor/bin/pest` a secas se queda sin memoria** en la exportación a Excel, con el
  `memory_limit` de 128M del php.ini de esta máquina. `composer verificar` no: corre con el límite
  que le pone Composer. Para correr la batería entera, `composer verificar` o
  `php -d memory_limit=1G vendor/bin/pest`.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
- Quedó una carpeta vacía sin rastrear, `storage/app/_zipcheck/orig`, de una prueba de esta sesión que
  no se pudo borrar por el modo de permisos de la sesión. No está en git y no afecta nada; se puede
  borrar a mano cuando se quiera.
