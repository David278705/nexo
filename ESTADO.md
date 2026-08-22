# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F2-05 · Vacaciones
**Verificación:** verde
**Siguiente tarea:** F2-06 · Advertencia de saldo insuficiente. Compara el saldo proyectado a la
fecha de disfrute contra los días solicitados y avisa sin bloquear; el gancho ya está listo:
`SaldoDeVacaciones::faltantePara()` existe desde F2-02.

## Qué se hizo

- **`vacaciones` es la segunda tabla de negocio de la fase 2**, colgada de `vinculacion_id` —regla
  5— y sin columna de días: `Vacacion::dias()` llama a `MotorDeCalculo::diasHabilesVacaciones()` cada
  vez, con el `Tenant` de la vinculación, para que domingos, festivos y el sábado parametrizado se
  respeten igual que en cualquier otro cálculo del motor.
- **El módulo copia las tres piezas compartidas de incapacidades**: `ListadoDeVacaciones` extiende
  `App\Listados\Listado` (REV-01) con un único filtro propio —rango de fechas—; la pantalla
  `Vacaciones/Index` usa `useListado()` (REV-02); y el botón «Registrar vacaciones» abre
  `ModalDeNovedad` (F2-03) con sus dos campos de fecha y los días hábiles calculados en el `slot`.
- **El descuento del saldo es el punto nuevo de esta tarea**, y no escribe nada aparte de la fila:
  `SaldoDeVacaciones::al()` gana un tercer sumando, `diasDisfrutadosHasta(fecha)`, que suma los días
  hábiles de las vacaciones ya registradas con `fecha_inicio <= fecha`. Como `Vacacion` usa borrado
  lógico (`SoftDeletes`), eliminar una fila la saca sola de esa suma la próxima vez que alguien pida
  el saldo: no existe una operación inversa que sumar a mano, que es exactamente la regla 2 del
  proyecto aplicada a un sumando nuevo.
- **El «hecho cuando» de la tarea —registrar y eliminar deja el saldo igual— se probó directamente
  sobre el cálculo**, no sobre una pantalla de borrado: se guarda una `Vacacion`, se compara el saldo
  antes y con ella puesta, se hace `$vacacion->delete()` y se comprueba que el saldo vuelve a ser el
  de antes. **Edición y borrado por pantalla siguen siendo F2-10** para los tres módulos de novedades
  a la vez, igual que se decidió en F2-04: lo que F2-05 necesitaba de «eliminar» es que el motor lo
  soporte sin operación inversa, no un botón en la interfaz.
- **El filtro del listado es solo el rango de fechas.** El documento 1, 11.3 también lista «Empleado»
  y «Cargo» para vacaciones, pero esos filtros —igual que la búsqueda por nombre o documento que le
  falta a incapacidades desde F2-04— son F4-01, la tarea que empareja los filtros de cada módulo con
  el documento 1 completo. No se inventó ningún filtro de más ni de menos que el patrón ya establecido.
- **Vinculación gana `vacaciones(): HasMany`**, igual y al lado de `incapacidades()`, ordenada por
  `fecha_inicio` descendente.
- **Nueve pruebas nuevas en `tests/Feature/VacacionesTest.php`** —registro, el caso de los cinco días
  hábiles del 27 al 31 de julio, el cálculo reactivo del modal, los rechazos de la sección 6.1, el
  permiso del módulo, la exportación y el descuento del saldo—, el caso de aislamiento completo en
  `AislamientoTest.php` —listado, exportación y el intento de registrar sobre el empleado ajeno—, y
  los dos ajustes que avisó la sesión anterior: `EstructuraTest.php` (vacaciones sale del bucle
  genérico de `SeccionPendiente` y tiene su propia aserción) y
  `FlujoDeNovedadTest.php::componenteDe()` (ahora es un `match` con las dos rutas reales).
- `composer verificar` en verde: Pint, PHPStan sin errores, **627** pruebas de Pest, build y **236**
  de Vitest (sin pruebas de Vitest nuevas: la pantalla no tiene lógica propia que no cubra ya
  `ModalDeNovedad.test.js`, igual que pasó con incapacidades).

## Decisiones tomadas

- **«Eliminar» del «hecho cuando» de F2-05 se leyó como una propiedad del cálculo, no como una
  pantalla.** La tarea pide demostrar que borrar una vacación deja el saldo igual; el borrado lógico
  ya existente (regla 4 del proyecto) más la resta hecha por consulta (regla 2) alcanzan para eso sin
  construir ninguna ruta ni botón de borrado, que es justo lo que F2-10 tiene asignado como tarea
  aparte para los tres módulos de novedades a la vez. Construirlo aquí habría adelantado F2-10 a
  medias, que es el mismo argumento que usó F2-04 para dejar fuera la edición y el borrado.
- **El filtro de «Empleado» y «Cargo» del documento 1, 11.3 no se construyó**, por la misma razón que
  incapacidades no tiene búsqueda por nombre: F4-01 es la tarea que completa los filtros de cada
  módulo contra el documento 1, y adelantarlo aquí a medias repetiría el problema que REV-01 y REV-02
  ya resolvieron una vez.
- **La fila del listado y de la exportación es una sola función**, `ListadoDeVacaciones::fila()`,
  igual que en incapacidades, empleados y contratos.

## Preguntas para Angela

- (vacío, ninguna nueva.)

## Decisiones para David

- **Todas las heredadas de sesiones anteriores siguen abiertas** —meses comerciales de treinta días,
  prórroga mínima, plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`,
  el texto del empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, si la pestaña de
  novedades de la ficha del empleado muestra o no el diagnóstico de una incapacidad, y el resto de la
  lista larga de REV-02—. Ninguna la tocó esta tarea.
- **La fila de la tabla de vacaciones no lleva enlace a ninguna ficha** (`clicable="false"` en
  `TablaDatos`), igual que incapacidades: no existe todavía una pantalla de detalle ni de edición.
  Cuando F2-10 la construya, hay que quitar esa bandera en los dos módulos y añadir el `@abrir`.
- **`FichaDeEmpleado::novedades()` sigue devolviendo un arreglo vacío**, igual que dejó F2-04: llenarla
  con incapacidades y vacaciones juntas es barata ahora que las dos relaciones existen, pero sigue
  pendiente la misma decisión de producto sobre si esa pestaña muestra el diagnóstico de una
  incapacidad —el permiso de la ficha es el de Empleados, distinto del de Incapacidades—.

## Notas para la próxima sesión

- **La siguiente es F2-06, Advertencia de saldo insuficiente.** El cálculo que necesita ya existe
  completo: `SaldoDeVacaciones::faltantePara(dias, fecha)` compara contra el saldo proyectado a la
  fecha de disfrute, no al de hoy, y ya está probado en `MotorDeCalculoTest.php` desde F2-02. Lo que
  falta es enseñarlo en el modal de vacaciones —la «franja permanente con el saldo actual y el saldo
  proyectado» que describe `docs/03-diseno-e-interfaz.md` 5.5— y la advertencia ámbar con el mensaje
  exacto cuando los días solicitados superan lo proyectado. No bloquea.
- **El patrón para agregar un módulo de novedad que descuenta del saldo ya tiene un ejemplo
  completo**: `app/Vacaciones/`, `app/Models/Vacacion.php`, la migración con `PoliticaRls::activarEn()`
  sobre una tabla sin columna de días, `App\Http\Requests\RegistrarVacacion`, `VacacionController` con
  `index()`/`exportar()`/`guardar()`, `VacacionesExport`, `Pages/Vacaciones/Index.vue`, y el sumando
  nuevo en `SaldoDeVacaciones::diasDisfrutadosHasta()`. F2-07 (licencias, ausencias y suspensiones)
  puede copiar la forma y cambiar el conteo a días corridos y el efecto a la reducción proporcional de
  la acumulación, que es el otro sumando que `MotorDeCalculo::acumulacionMes()` ya deja anotado y en
  cero a la espera de esa tabla.
- **Cuando una pantalla real reemplaza a `SeccionPendiente`, hay que revisar las mismas dos pruebas
  compartidas de siempre**: `EstructuraTest.php` y `FlujoDeNovedadTest.php::componenteDe()`. Las dos
  quedaron actualizadas esta sesión con vacaciones; a F2-07 le toca hacer lo mismo con licencias.
- **`Vinculacion::vacaciones()` ya existe** como relación `hasMany`, ordenada por `fecha_inicio`
  descendente, al lado de `incapacidades()`, lista para cuando se decida llenar
  `FichaDeEmpleado::novedades()`.
- Todas las notas de arquitectura de la sesión de REV-02 sobre el armazón de listado, y las de F2-04
  sobre el flujo compartido de novedades y el patrón de cálculo reactivo en el modal, siguen vigentes
  y no se repiten aquí.
