# Estado del proyecto

**Última sesión:** 2026-08-21 · Tarea F2-04 · Incapacidades
**Verificación:** verde
**Siguiente tarea:** F2-05 · Vacaciones. Es el segundo módulo de novedades y el primero que descuenta
del saldo: reutiliza `diasHabilesVacaciones()` y `SaldoDeVacaciones::al()`, que el motor ya tiene listos.

## Qué se hizo

- **`incapacidades` es la primera tabla de negocio de la fase 2**, colgada de `vinculacion_id` —no de
  `persona_id`, que es la regla 5— y sin columna de días: `Incapacidad::dias()` llama a
  `MotorDeCalculo::diasCorridos()` cada vez, así que nunca puede desincronizarse de `fecha_inicio` y
  `fecha_fin` cuando F2-10 permita corregirlas.
- **El módulo estrena las tres piezas compartidas a la vez**, tal como avisó la sesión anterior:
  `ListadoDeIncapacidades` extiende `App\Listados\Listado` (REV-01) declarando solo sus tres filtros
  —rango de fechas, origen, EPS— y sus columnas ordenables; la pantalla `Incapacidades/Index` usa
  `useListado()` (REV-02) sin volver a escribir `pedir()`, `ordenar()` ni la frase de exportar; y el
  botón «Nueva incapacidad» abre `ModalDeNovedad` (F2-03) con sus campos propios en el `slot`.
- **El código de diagnóstico no se recorta en ningún punto de la pantalla.** Se leyó la sección 6.3 como
  que el único permiso que exige es el del módulo —«acceso restringido a los usuarios con permiso sobre
  el módulo de novedades» se tradujo a `Modulo::Incapacidades`, que es el módulo real de permisos; no
  existe un módulo «Novedades» aparte, la agrupación del menú es solo visual (`Modulo::seccion()`)—, y
  quien llega al listado o a la exportación ya pasó el middleware `modulo:incapacidades`. La única fuga
  posible era la auditoría, que es **otro** módulo con **otro** permiso: `codigo_diagnostico` entró a la
  lista `OCULTAS` de `ObservadorDeAuditoria`, con su prueba en `AuditoriaTest.php`.
- **Los días de la incapacidad que se está digitando se calculan en el servidor**, igual que la
  proyección del modal de renovación de contrato: `IncapacidadController::index()` comparte una prop
  opcional `diasDeIncapacidad`, ya escrita como «3 días», y el modal la vuelve a pedir con una recarga
  parcial cada vez que cambia una de las dos fechas. En el navegador no se resta ninguna fecha.
- **`RegistrarIncapacidad` valida que el empleado exista y esté activo en la empresa actual** con una
  regla propia sobre `Vinculacion::activas()`, que pasa por el trait `PerteneceATenant`: el identificador
  que manda el formulario no puede alcanzar la vinculación de otra empresa aunque se fuerce a mano, y hay
  prueba de ese caso concreto.
- **Nueve pruebas nuevas en `tests/Feature/IncapacidadesTest.php`**, el caso de aislamiento completo en
  `AislamientoTest.php` —listado, exportación y el intento de registrar sobre el empleado ajeno—, y la
  prueba de la auditoría en `AuditoriaTest.php`. Se ajustaron tres pruebas existentes que asumían que
  `/incapacidades` seguía siendo `SeccionPendiente`: `EstructuraTest.php` (ahora la excluye del bucle
  genérico y tiene su propia aserción, como empleados) y `FlujoDeNovedadTest.php` (la cabecera
  `X-Inertia-Partial-Component` de sus recargas parciales ahora se calcula según la URL, con
  `componenteDe()`, en vez de asumir siempre `SeccionPendiente`).
- `composer verificar` en verde: Pint, PHPStan sin errores, **612** pruebas de Pest, build y **236** de
  Vitest.

## Decisiones tomadas

- **«El módulo de novedades» de la sección 6.3 es `Modulo::Incapacidades`, no un módulo aparte.** El
  enumerado `Modulo` ya lo decía en su comentario: las tres subsecciones de novedades son permisos
  independientes porque el capítulo 3 permite que alguien se encargue solo de vacaciones. No se inventó
  ninguna regla nueva de permisos para el diagnóstico.
- **Edición y borrado quedan fuera de esta tarea.** El «Hecho cuando» de F2-04 solo pide el registro y
  que los días y el permiso se vean bien; F2-10 —«Edición y borrado de novedades»— ya es una tarea
  aparte del backlog para los tres módulos de novedades a la vez, y construirla aquí habría adelantado
  esa tarea a medias en vez de completarla cuando le toque.
- **La tabla no guarda los días.** Es la misma razón de la regla 2 del proyecto aplicada a otro campo:
  guardar `dias` obligaría a recalcularlo a mano el día en que F2-10 permita corregir las fechas. El
  motor ya tenía `diasCorridos()` desde F2-02; aquí solo se usa.
- **La fila del listado y de la exportación es una sola función**, `ListadoDeIncapacidades::fila()`,
  igual que en empleados y contratos: la pantalla y el archivo no pueden decir cosas distintas de la
  misma incapacidad.

## Preguntas para Angela

- (vacío, ninguna nueva. Las heredadas de sesiones anteriores siguen en la sección de decisiones para
  David, más abajo, sin cambios por esta tarea.)

## Decisiones para David

- **Todas las heredadas de la sesión anterior siguen abiertas** —meses comerciales de treinta días,
  prórroga mínima, plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el
  texto del empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, y el resto de la lista larga
  de la sesión de REV-02—. Ninguna la tocó esta tarea; se repiten aquí solo las que cambian o se
  agregan.
- **`FichaDeEmpleado::novedades()` sigue devolviendo un arreglo vacío.** El comentario del archivo decía
  «vacío hasta que existan sus módulos», y F2-04 es el primero en existir, pero el «Hecho cuando» de la
  tarea no pedía llenar esa pestaña —solo el listado propio, los días y el permiso del diagnóstico—.
  Llenarla es barata ahora que `Vinculacion::incapacidades()` ya existe como relación, pero **hay que
  decidir antes si esa pestaña muestra el código de diagnóstico o no**: la ficha del empleado se abre con
  el permiso del módulo Empleados, que es distinto del de Incapacidades, así que mostrarlo ahí sin más
  repetiría exactamente la fuga que se tapó en la auditoría. Lo más simple es no mostrarlo en esa pestaña
  y decirlo con una nota, pero es una decisión de producto, no una de código.
- **El listado de incapacidades no tiene búsqueda por nombre o documento**, a diferencia de empleados. El
  prototipo (`prototipos/sistema.html`, sección de incapacidades) tampoco la trae: solo fechas, origen y
  EPS. Si se quiere agregar, es un filtro más en `ListadoDeIncapacidades`, del mismo tipo que ya usa
  `ListadoDeEmpleados`.
- **La fila de la tabla no lleva enlace a ninguna ficha** (`clicable="false"` en `TablaDatos`): no existe
  todavía una pantalla de detalle ni de edición para una incapacidad. Cuando F2-10 la construya, hay que
  quitar esa bandera y añadir el `@abrir`.

## Notas para la próxima sesión

- **La siguiente es F2-05, Vacaciones**, el segundo módulo de novedades y el primero que descuenta del
  saldo. El motor ya tiene `MotorDeCalculo::diasHabilesVacaciones()` y `SaldoDeVacaciones::al()`
  preparados desde F2-02; lo que falta es el módulo alrededor.
- **El patrón para agregar un módulo de novedad ya tiene un ejemplo completo de principio a fin**:
  `app/Incapacidades/` (namespace del dominio), `app/Models/Incapacidad.php`, la migración con
  `PoliticaRls::activarEn()`, `App\Http\Requests\RegistrarIncapacidad` con su regla de «empleado activo»,
  `IncapacidadController` con `index()`/`exportar()`/`guardar()`, `IncapacidadesExport`, y
  `Pages/Incapacidades/Index.vue` con `ModalDeNovedad` en el `slot`. Vacaciones puede copiar la forma y
  cambiar el contenido: sus tres piezas propias son el cálculo de días hábiles en vez de corridos, el
  descuento del saldo y la advertencia de saldo insuficiente (F2-06, que es la tarea siguiente a esa).
- **Cómo se pide al servidor un cálculo mientras se digita un formulario, sin contar nada en Vue.** El
  patrón es el mismo que ya usaba el modal de renovación de contrato: una prop opcional
  (`Inertia::optional()`) en el controlador de la pantalla donde vive el modal, calculada a partir de los
  parámetros de la consulta, y un `watch()` en Vue que hace `router.reload({ only: [...] })` cada vez que
  cambia un campo relevante. `IncapacidadController::diasDeIncapacidad()` y el `watch` de
  `Incapacidades/Index.vue` son el ejemplo más corto.
- **Cuando una pantalla real reemplaza a `SeccionPendiente`, hay que revisar dos pruebas compartidas**:
  `EstructuraTest.php` («navega a la sección de cada módulo que el usuario tiene», que hay que sacar del
  bucle genérico y darle su propia aserción) y, si el módulo participa del flujo de novedades,
  `FlujoDeNovedadTest.php::componenteDe()`, que decide qué componente espera la cabecera
  `X-Inertia-Partial-Component` de cada recarga parcial según la URL.
- **`Vinculacion::incapacidades()` ya existe** como relación `hasMany`, ordenada por `fecha_inicio`
  descendente, lista para cuando se decida llenar `FichaDeEmpleado::novedades()`.
- Todas las notas de arquitectura de la sesión de REV-02 sobre el armazón de listado —cómo se agrega un
  listado en el servidor y en la interfaz, la lista blanca de columnasDeOrden, los helpers de prueba
  repartidos por archivo— siguen vigentes y no se repiten aquí.
