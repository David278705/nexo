# Estado del proyecto

**Última sesión:** 2026-09-20 · Tarea `HD-14`
**Verificación:** verde. 1.305 de 1.305 pruebas de Pest, Pint y PHPStan sin errores, build y 413 de
Vitest — cinco pruebas de Pest y tres de Vitest más que `HD-13`.
**Siguiente tarea:** `HD-15` · El botón «Nueva licencia» no nombra las tres cosas que registra.

## Qué se hizo

`HD-14` pedía que el listado de incapacidades enseñara la EPS y dejara ordenar por días.

- **La EPS** es una columna más de la tabla, después de «Prórroga» y antes de «Acciones». La fila
  (`ListadoDeIncapacidades::fila()`) y la exportación ya la traían; se corrigió el comentario que decía
  que la tabla no la enseñaba.
- **«Días» ordena** en los dos sentidos. `columnasDeOrden()` admite `dias`, resuelto en la consulta con
  `ORDER BY (fecha_fin - fecha_inicio)`, así que el orden vale para todas las páginas y la exportación
  sale en el mismo orden.
- **El prototipo** lleva la columna EPS en la tabla de incapacidades.
- **Pruebas:** cinco de Pest en `IncapacidadesTest.php` (EPS última en la fila y en la exportación, orden
  ascendente y descendente, orden a través de dos páginas, llave de orden contra `dias()`) y tres de
  Vitest en `Incapacidades/Index.test.js`, archivo nuevo.

## Decisiones tomadas

- **La resta de fechas del `ORDER BY` es una llave de orden, no un conteo.** Los días que se enseñan
  siguen saliendo de `Incapacidad::dias()`, o sea del motor; una prueba comprueba que ambos ordenen igual.
- **«Última columna» se leyó como última de datos.** «Acciones» sigue a la derecha porque son botones,
  no un dato.

## Preguntas para Angela

- (vacío, no cambia respecto a la sesión anterior)

## Decisiones para David

- **`HD-12` mencionaba una «carga masiva de terminaciones» que no existe.** Las seis plantillas de
  `CatalogoDePlantillas` son empleados, perfil, incapacidades, vacaciones, licencias y movimientos;
  ningún documento pide una de terminaciones. La regla del vacío ya vale para el registro y la
  corrección por pantalla. ¿Quieres una plantilla de terminaciones (y entonces es una tarea nueva, con
  su propia fuente y sus criterios) o el punto se da por descartado?
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
  verdad), la de `HD-07` (Cundinamarca/Bogotá en el asistente), la de `HD-08` (departamento y ciudad
  de expedición en la plantilla real), la de `HD-09` (los doce campos de prórroga en la plantilla
  real), la de `HD-10` (abrir en Excel de verdad las plantillas de empleados e incapacidades), la de
  `HD-11` (abrir el modal de «Cambiar a término indefinido» en el navegador y comprobar que el
  calendario nativo respeta el rango), la de `HD-12` (registrar una salida para un contrato a término
  fijo vencido y comprobar que el mensaje se lee bien debajo de «Fecha de salida»), la de `HD-13`
  (mirar la barra lateral y comprobar que el encabezado «Cambios y salidas» cabe sin cortarse) y ahora
  también `HD-14`: abrir `/incapacidades` en el navegador y comprobar que con la columna EPS la tabla
  cabe sin desbordar el ancho, y pulsar «Días» para ver el orden en los dos sentidos.
- **La tarea `HD-10` decía «siete plantillas» y el código solo tiene seis** (`CatalogoDePlantillas::todas()`).
  Si te referías a otra cosa —por ejemplo, contar también
  `Listas_Desplegables_Formulario_Empleados.xlsx`, que no es una plantilla que se cargue sino la
  fuente de los catálogos—, dilo y se ajusta. Por ahora `HD-10` se resolvió sobre las seis reales.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-15` es la siguiente**: el botón «Nueva licencia» no nombra las tres figuras que registra
  (licencia no remunerada, ausencia y suspensión). Ojo con el alcance: el enunciado pide también que el
  nombre del módulo en el menú y en los encabezados sea coherente; hay que ver qué usa hoy
  `Modulo::Licencias->etiqueta()` y no romper `EstructuraTest.php`, que ya vigila que los grupos no
  repitan etiquetas.
- **Para correr un archivo de Pest suelto que use `usuarioDe()`** hay que incluir `RolesYPermisosTest.php`,
  y `empleadoParaNovedad()` vive en `FlujoDeNovedadTest.php`. Fuera de `paraTenant()` las consultas
  devuelven vacío: una prueba puede pasar en vacío si no afirma la lista exacta.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
