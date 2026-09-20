# Estado del proyecto

**Última sesión:** 2026-09-20 · Tarea `HD-13`
**Verificación:** verde. 1.300 de 1.300 pruebas de Pest, Pint y PHPStan sin errores, build y 410 de
Vitest — tres pruebas de Pest más que `HD-12`, Vitest sin cambios.
**Siguiente tarea:** `HD-14` · El listado de incapacidades no enseña la EPS ni deja ordenar por días.

## Qué se hizo

`HD-13` pedía que el grupo del menú que reúne aumentos y terminaciones dejara de llamarse
«Movimientos», igual que uno de sus hijos.

- **`Modulo::grupo()`** devuelve «Cambios y salidas» para `Movimientos` y `Terminaciones`. Es el único
  sitio donde está escrito el nombre; el componente `BarraLateral.vue` solo pinta lo que llega.
- **La etiqueta del módulo y la pestaña de la ficha siguen siendo «Movimientos»**, que ahí es exacto.
- **El prototipo** (`prototipos/sistema.html`) lleva el mismo encabezado, y `docs/03-diseno-e-interfaz.md`
  §4.2 ahora nombra los cuatro grupos del menú (Personal, Novedades, Cambios y salidas, Sistema).
- **Tres pruebas nuevas** en `EstructuraTest.php`: el nombre del grupo para los dos módulos, que ningún
  grupo repite la etiqueta de un módulo, y que la prop compartida con la barra lateral lo trae. Los
  fixtures de Vitest de Movimientos y Terminaciones usan el nombre nuevo.

## Decisiones tomadas

- **«Cambios y salidas», la propuesta del enunciado**, sin alternativas: dice en dos palabras lo que son
  un aumento de salario y una terminación y no choca con ninguna etiqueta.
- **Se documentó el menú agrupado en el capítulo 4.2**, porque los grupos no estaban en ningún
  documento y la tarea pedía dejar prototipo y pruebas «de acuerdo».
- **Alertas figura dentro de «Sistema» en el documento** porque así lo agrupa el código, aunque el
  prototipo no la muestra en el menú.

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
  fijo vencido y comprobar que el mensaje se lee bien debajo de «Fecha de salida») y ahora también
  `HD-13`: mirar la barra lateral en el navegador y comprobar que el encabezado «Cambios y salidas»
  cabe sin cortarse.
- **La tarea `HD-10` decía «siete plantillas» y el código solo tiene seis** (`CatalogoDePlantillas::todas()`).
  Si te referías a otra cosa —por ejemplo, contar también
  `Listas_Desplegables_Formulario_Empleados.xlsx`, que no es una plantilla que se cargue sino la
  fuente de los catálogos—, dilo y se ajusta. Por ahora `HD-10` se resolvió sobre las seis reales.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-14` es la siguiente**: el listado de incapacidades no muestra la EPS ni deja ordenar por días,
  y las dos cosas están así a propósito. Leer el enunciado completo en `BACKLOG.md`; el comentario que
  explica el porqué de cada decisión está en el código y hay que leerlo antes de cambiarlo.
- **Para correr `EstructuraTest.php` suelto hay que incluir `RolesYPermisosTest.php`**: `usuarioDe()`
  vive ahí y Pest solo carga las funciones de los archivos que corre.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
