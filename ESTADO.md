# Estado del proyecto

**Última sesión:** 2026-09-20 · Tarea `HD-15`
**Verificación:** verde. 1.306 de 1.306 pruebas de Pest, Pint y PHPStan sin errores, build y 415 de
Vitest — una prueba de Pest y dos de Vitest más que `HD-14`.
**Siguiente tarea:** `HD-16` · La nota del saldo de vacaciones no menciona el disfrute.

## Qué se hizo

`HD-15` pedía que el botón «Nueva licencia» nombrara las tres figuras que registra el módulo.

- **El nombre del módulo es «Licencias, ausencias y suspensiones»**, en el menú
  (`Modulo::Licencias->etiqueta()`) y en el encabezado de la pantalla, que ya lo llevaba. Es el nombre
  que el documento 1 usa en filtros e informes.
- **El modal** se titula «Nueva licencia, ausencia o suspensión» y, al editar, «Editar licencia,
  ausencia o suspensión» (`TipoDeNovedad::Licencia`).
- **El botón** ya no tiene texto propio: muestra el título del modal, así que no se separan.
- **Documento 3** (menú, barra lateral y tabla de pantallas) y **prototipo** llevan los mismos nombres;
  el botón del prototipo decía «+ Nueva novedad».
- **Pruebas:** un Pest en `EstructuraTest.php` (menú y los dos títulos mencionan la raíz de cada figura
  del enum `TipoDeLicencia`, así que una cuarta figura lo pondría en rojo) y dos de Vitest en
  `Licencias/Index.test.js` (botón y encabezado). Se actualizaron tres expectativas que fijaban los
  nombres viejos.

## Decisiones tomadas

- **Se eligió el nombre completo del documento 1 y no uno abreviado.** Cualquier nombre corto para el
  menú («Licencias y suspensiones», «Ausencias») habría dejado una de las tres figuras escondida detrás
  de otra, que es justo lo que la tarea prohíbe.
- **La hoja del Excel de carga no se renombró.** `PlantillaDeLicencias::hoja()` sigue siendo «Licencias y
  ausencias»: Excel limita el nombre de una hoja a 31 caracteres y el nuevo tiene 35, y además es el
  formato de la plantilla real que recibe el cliente.

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
  (mirar la barra lateral y comprobar que el encabezado «Cambios y salidas» cabe sin cortarse), la de
  `HD-14` (abrir `/incapacidades` y comprobar que con la columna EPS la tabla cabe sin desbordar, y
  pulsar «Días» para ver el orden en los dos sentidos) y ahora también `HD-15`: mirar la barra
  lateral y comprobar que «Licencias, ausencias y suspensiones» (35 caracteres en una barra de 236 px)
  se parte en dos líneas sin cortarse, y que el botón «Nueva licencia, ausencia o suspensión» cabe
  junto a «Exportar» en el ancho de escritorio y en móvil.
- **La tarea `HD-10` decía «siete plantillas» y el código solo tiene seis** (`CatalogoDePlantillas::todas()`).
  Si te referías a otra cosa —por ejemplo, contar también
  `Listas_Desplegables_Formulario_Empleados.xlsx`, que no es una plantilla que se cargue sino la
  fuente de los catálogos—, dilo y se ajusta. Por ahora `HD-10` se resolvió sobre las seis reales.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-16` es la siguiente**: la nota del saldo de vacaciones en la ficha
  (`app/Contratacion/FichaDeEmpleado.php`) debe nombrar los cuatro términos de la fórmula, vivir en un
  solo sitio y tener una prueba que la ate a la fórmula de `SaldoDeVacaciones`. Ojo con el «un solo
  sitio»: hay que buscar qué otras pantallas enseñan el saldo antes de decidir dónde vive el texto.
- **Para correr un archivo de Pest suelto que use `usuarioDe()`** hay que incluir `RolesYPermisosTest.php`,
  y `empleadoParaNovedad()` vive en `FlujoDeNovedadTest.php`. Fuera de `paraTenant()` las consultas
  devuelven vacío: una prueba puede pasar en vacío si no afirma la lista exacta.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
