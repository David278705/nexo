# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F2-09 · Terminaciones
**Verificación:** verde
**Siguiente tarea:** F2-10 · Edición y borrado de novedades. Todas editables y eliminables sin límite
de antigüedad, con confirmación que explica la consecuencia calculada.

## Qué se hizo

- **La deuda que F1-05 dejó apuntada quedó pagada.** `Vinculacion::fechaDeSalida()` ya no sale de
  `fecha_fin` sino de la terminación registrada, y de ella dependen tres cosas que hasta hoy no
  funcionaban en un contrato indefinido: el énfasis de los 30 días de la advertencia de reingreso, el
  mes en que el saldo de vacaciones deja de acumular y la antigüedad de la ficha. `fecha_fin` queda
  como respaldo **a propósito y no por descuido**: la carga inicial (F5-02) traerá personal histórico
  que se fue antes de que la empresa usara el sistema, y para ese la fecha pactada es la única que
  hay. Las dos pruebas que lo demuestran son nuevas: una mide los 30 días sobre un indefinido y otra
  comprueba que el saldo se congela el día de la salida.
- **`Vinculacion::registrarTerminacion()` es la segunda escritura doble del proyecto**, y copia el
  patrón de `registrarMovimiento()` en vez de inventar otro: la terminación y el paso a inactivo van
  en una transacción. Un fallo en medio dejaría una salida registrada sobre alguien a quien el
  sistema le seguiría mandando alertas, que es exactamente lo que la sección 7.1 pide evitar.
- **La categoría de rotación no tiene columna.** La sección 7.4 dice que el usuario no la digita, la
  deduce el sistema, así que la responde `TipoDeSalida::categoriaDeRotacion()` cada vez. Guardarla
  habría congelado la clasificación del día del registro, y el propio documento discute mover el
  mutuo acuerdo de grupo: el día que se moviera, las filas viejas mentirían.
- **Los 35 motivos y su dependencia viven en `TipoDeSalida` y en ningún otro sitio.** De ahí salen
  las opciones del formulario, el motivo automático de los dos tipos sin submotivos, la lista blanca
  del filtro del listado y la validación del servidor. La lista dependiente se recarga en el
  navegador con datos que ya vinieron —son frases fijas, no dependen de la empresa—, pero **quién
  decide cuál vale es el servidor**: hay prueba de que «Estudios» no se puede guardar como despido
  con justa causa.
- **La confirmación de la sección 5.6 se escribe en el servidor**, `AvisoDeTerminacion`, y llega por
  la recarga parcial `avisoDeTerminacion`, el mismo patrón de `situacionActual` en movimientos. Así
  la prueba lee la misma frase que ve el usuario, con el nombre del empleado de verdad.
- **El módulo completo con el patrón compartido**: `ListadoDeTerminaciones` sobre `Listado` (REV-01)
  con los tres filtros del documento 1, 11.3 —rango de fecha de salida, tipo y motivo—,
  `TerminacionController`, `RegistrarTerminacion`, `TerminacionesExport` con las tres columnas
  obligatorias más la rotación, y la pantalla `Terminaciones/Index` sobre `useListado` (REV-02)
  enchufada a `ModalDeNovedad` (F2-03).
- **`Selector` estrenó `deshabilitado`**, que es lo que pide el documento 3, 5.6 para el motivo
  autocompletado. Es el primer componente compartido que se toca desde F2-03.
- **Se extrajo la sexta copia antes de escribirla.** Los cuatro listados de novedades repetían
  `dePersonaDeLaVinculacion()` con la sola diferencia del nombre de la tabla; ahora está una vez en
  el trait `OrdenaPorEmpleado` como `porNombreDeLaPersonaDeLaNovedad()`, y los cinco listados lo
  llaman. Ninguna prueba se tocó para eso.
- **Las cuatro pruebas compartidas de siempre se actualizaron**: el caso de aislamiento por sus rutas
  reales en `AislamientoTest.php` —con el caso propio: que la empresa B no pueda terminar al empleado
  de la A ni dejarlo inactivo—, `EstructuraTest.php`, `componenteDe()` en `FlujoDeNovedadTest.php` y
  la lista `PANTALLAS` de `useListado.test.js`.
- `composer verificar` en verde: Pint, PHPStan sin errores, **703** pruebas de Pest (30 nuevas), build
  y **257** de Vitest.

## Decisiones tomadas

- **El motivo se guarda como su frase, no como un código.** Son 35 textos que ya están escritos en el
  documento y en el prototipo, y el sistema no los interpreta: los enseña y los agrupa. La convención
  del proyecto pide enumerados legibles y no hay nada más legible que la frase misma; inventar 35
  identificadores habría añadido una traducción que nadie lee. El tipo de salida sí es un enumerado
  con valor corto, porque de él dependen reglas.
- **La rotación involuntaria se pinta ámbar y no roja como el prototipo.** La regla de interfaz
  reserva el rojo para cuando algo está mal de verdad, y una salida decidida por la empresa es un
  hecho que merece atención, no un error. Los tres tonos siguen siendo distintos y el texto va
  siempre escrito, así que el color no es el único portador de la información. Queda anotado en
  `CategoriaDeRotacion::tono()` para que no parezca un descuido.
- **No se valida que la fecha de salida sea posterior al ingreso ni que no sea futura.** Ningún
  documento lo fija y no es una regla legal: una renuncia se avisa con antelación y una salida se
  registra a veces días después. Queda escrito en `RegistrarTerminacion` con el resto de lo que
  deliberadamente no se comprueba.
- **La doble terminación no necesita índice único.** La impide la misma guardia que las demás
  novedades: `RegistrarTerminacion` exige un empleado activo, y el primer registro lo desactiva. Hay
  prueba de que la segunda petición no entra.
- **La lista de motivos viaja entera en la carga de la pantalla**, no por recarga parcial. Son frases
  fijas que no dependen de ningún dato de la empresa, y pedirlas al servidor cada vez que se cambia
  el tipo sería una vuelta de red por algo que ya se sabe.

## Preguntas para Angela

- (vacío, ninguna nueva.)

## Decisiones para David

- **Todas las heredadas siguen abiertas** —meses comerciales de treinta días, prórroga mínima,
  plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, el diagnóstico en la pestaña de
  novedades de la ficha, la frase del prototipo sobre el saldo negativo, y el resto de la lista larga
  de REV-02—. Ninguna la tocó esta tarea.

## Notas para la próxima sesión

- **La siguiente es F2-10, edición y borrado de novedades**, y son cuatro módulos, no tres:
  incapacidades, vacaciones, licencias y movimientos. **Las terminaciones son el caso difícil y hay
  que decidir qué se hace con ellas**: borrar una terminación tendría que devolver al empleado a
  activo, y eso es una acción con más consecuencias que las otras cuatro —revive las alertas de esa
  persona—. La tarea no lo nombra; conviene resolverlo explícitamente en vez de dejarlo caer.
- **Deshacer un movimiento sigue siendo el otro caso propio** que ya quedó anotado: el registro
  principal del empleado quedó con el valor nuevo, así que la confirmación tiene que decir a qué
  salario y a qué cargo vuelve.
- **Auditoría es el último módulo que sigue siendo `SeccionPendiente`.** Al ponerle pantalla real hay
  que tocar las mismas cuatro pruebas compartidas: la lista de exclusión de `routes/web.php`, la de
  `EstructuraTest.php`, `componenteDe()` en `FlujoDeNovedadTest.php` y `PANTALLAS` en
  `useListado.test.js`.
- **`TipoDeSalida::categoriaDeRotacion()` es lo que va a consumir el indicador de rotación del
  capítulo 12** (F4-04), incluida la rotación temprana —salidas de personas con menos de seis meses—,
  que se calcula con `fecha_ingreso` y la fecha de salida ya registrada.
- Las notas de arquitectura de REV-01/REV-02 sobre el armazón de listado y las de F2-03 a F2-08 sobre
  el flujo compartido de novedades siguen vigentes y no se repiten aquí.
