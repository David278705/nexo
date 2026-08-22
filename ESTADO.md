# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F2-10 · Edición y borrado de novedades
**Verificación:** verde
**Siguiente tarea:** F2-11 · PUNTO DE CONTROL — revisión (ejecutar el skill `revisar`)

## Qué se hizo

- **Las tres novedades del capítulo 6 ya se editan y se eliminan desde su listado**, sin límite de
  antigüedad, que es lo que pide la tabla del documento 1, 6.2. Cada fila estrena dos acciones de
  texto —Editar y Eliminar—, seis rutas nuevas (`PUT` y `DELETE` por módulo) y ninguna de las dos
  toca una sola columna de saldo: no existe.
- **El «hecho cuando» está probado con la frase que ve el usuario.** `ConfirmacionDeBorrado` escribe
  la plantilla literal del documento 3, 6.3 —«Vas a eliminar {descripción}. {Consecuencia}. Esta
  acción queda registrada en la auditoría.»— y para vacaciones la consecuencia es la del ejemplo del
  2.4: «Se eliminarán 5 días de vacaciones y el saldo de Carlos Ramírez pasará de X a Y días». La
  prueba comprueba **las dos cosas a la vez**: que la frase sea esa y que el saldo de después sea de
  verdad el de antes más los cinco días.
- **`SaldoDeVacaciones::sin()` es la pieza nueva del motor**, y hace falta porque la consecuencia se
  enseña **antes** de eliminar: responde «cuánto valdría el saldo si esta novedad no existiera» sin
  escribir nada en la base. Excluye por identificador —una vacación en `diasDisfrutadosHasta()`, una
  licencia a través del nuevo parámetro de `MotorDeCalculo::acumulacionMes()`—; la alternativa era
  borrar dentro de una transacción y revertirla, que durante ese rato es una escritura de verdad.
- **`DetalleDeNovedad` es el gemelo de `EmpleadoDeNovedad` para el otro lado del flujo**: una sola
  prop `novedad`, `Inertia::optional`, que la pantalla pide al pulsar Editar o Eliminar. Lleva las
  tres cosas que hacen falta —la ficha del empleado, los valores del formulario y la frase de la
  confirmación—, así que el listado no paga nada por ofrecer dos acciones que casi nunca se usan, ni
  hay dos props que puedan discrepar sobre qué fila está seleccionada.
- **La interfaz `NovedadEditable`** declara lo único que cambia entre los tres módulos:
  `valoresParaEdicion()`, `confirmacionDeBorrado()` y `vinculacionDeLaNovedad()`. Todo lo demás —la
  prop, el modal, la ruta, el composable— es compartido.
- **`ModalDeNovedad` estrenó modo edición** en vez de tener un gemelo: con la prop `edicion` se salta
  los pasos 2 y 3 del documento 3, 5.5 y abre directamente el modal grande con el empleado ya
  resuelto. Las tres pantallas montan **un solo modal** para registrar y para corregir, así que los
  campos del formulario se escriben una vez.
- **Dos piezas compartidas nuevas**: `ModalDeBorrado` —el único sitio del sistema con un botón rojo,
  que es la regla del documento 3, 3.4— y el composable `useNovedad`, el hermano de `useListado`, con
  las dos acciones enteras. Tres casos de Vitest leen los `.vue` para comprobar que ninguna pantalla
  conserva su copia.
- **Las `Editar*` heredan de las `Registrar*`** y solo quitan la regla del empleado, con el motivo
  escrito: editar es corregir el registro, no moverlo de persona, y exigir empleado activo dejaría
  sin corregir precisamente las novedades más viejas. Hay prueba de que la novedad de alguien que ya
  salió sí se puede corregir.
- **Aislamiento**: los tres módulos suman su caso a `AislamientoTest.php`. La empresa B recibe 404 al
  editar y al eliminar la novedad de la A, y la prop `novedad` tampoco le contesta por una fila ajena
  —que era el camino nuevo por donde podía haberse filtrado la ficha de un empleado ajeno—.
- `composer verificar` en verde: Pint, PHPStan sin errores, **719** pruebas de Pest (16 nuevas), build
  y **273** de Vitest (16 nuevas).

## Decisiones tomadas

- **La tarea se cerró con tres módulos y no con cinco, a propósito.** Su fuente es la sección 6.2,
  «Reglas comunes a todas las novedades», y las novedades del capítulo 6 son incapacidades,
  vacaciones y licencias. Movimientos y terminaciones comparten el flujo de registro pero no son
  novedades de ese capítulo, y **deshacerlas no es quitar una fila**: hay que decidir qué pasa con el
  salario ya cambiado y con el empleado ya inactivo. Eso es una regla de negocio que ningún documento
  fija, así que quedó como **`REV-03`, marcada `[!]`**, con las dos preguntas abajo. `ESTADO.md` de
  la sesión anterior ya avisaba de este caso.
- **La cláusula del saldo solo aparece si el saldo cambia.** Unas vacaciones que empiezan el mes que
  viene todavía no se han descontado, y una licencia no reduce nada si la empresa no tiene activo
  `suspensiones_afectan_vacaciones`. Anunciar un cambio que no va a ocurrir es peor que no anunciar
  ninguno; hay prueba de los dos casos. La incapacidad no lleva consecuencia nunca, y eso coincide
  con el ejemplo del propio documento 3, 2.5, que tampoco la lleva.
- **Editar no permite cambiar de empleado.** Para eso se elimina y se registra de nuevo, que además
  deja las dos líneas en la auditoría. Mover una novedad de persona en silencio sería el cambio más
  difícil de auditar del sistema.
- **`Formato::periodo()` escribe «del 14 al 16 de julio» y repite el mes solo cuando cambia.** Es la
  descripción del registro en la confirmación, y el documento la escribe así.
- **El botón Eliminar de la tabla no es rojo.** El destructivo vive dentro de la confirmación, nunca
  suelto en una tabla (documento 3, 3.4). Y `ModalDeBorrado` no emite el `confirmar` mientras no haya
  llegado la frase: eliminar algo sin haber leído qué se pierde es justo lo que ese modal evita.

## Preguntas para Angela

- (vacío, ninguna nueva.)

## Decisiones para David

- **`REV-03`, pregunta 1 — ¿eliminar un movimiento salarial devuelve el salario y el cargo
  anteriores?** El registro principal del empleado ya cambió al guardarlo (documento 1, 8.1). Si se
  devuelve, falta decidir qué pasa cuando hubo otro movimiento encima. Si no se devuelve, el
  histórico y el registro principal quedan contando cosas distintas.
- **`REV-03`, pregunta 2 — ¿eliminar una terminación devuelve al empleado a activo?** Con él vuelven
  todas sus alertas, que es la consecuencia más grande que puede tener una acción en este sistema. El
  capítulo 7 dice qué pasa al registrarla y no dice nada de deshacerla.
- **Todas las heredadas siguen abiertas** —meses comerciales de treinta días, prórroga mínima,
  plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, el diagnóstico en la pestaña de
  novedades de la ficha, la frase del prototipo sobre el saldo negativo, y el resto de la lista larga
  de REV-02—. Ninguna la tocó esta tarea.

## Notas para la próxima sesión

- **La siguiente es F2-11, el punto de control de la fase 2**: se ejecuta el skill `revisar` y lo que
  encuentre se arregla o se convierte en tareas `REV-`. Cierra ocho módulos construidos desde F2-01.
- **`REV-03` está `[!]` al final del backlog** y no se toma hasta que las dos preguntas de arriba
  estén respondidas. `TipoDeNovedad::tituloDeEdicion()` devuelve nulo para esos dos módulos justamente
  para que se note.
- **Auditoría es el último módulo que sigue siendo `SeccionPendiente`.** Al ponerle pantalla real hay
  que tocar las mismas cuatro pruebas compartidas: la lista de exclusión de `routes/web.php`, la de
  `EstructuraTest.php`, `componenteDe()` en `FlujoDeNovedadTest.php` y `PANTALLAS` en
  `useListado.test.js`. Ahora tiene además un motivo nuevo para existir: es donde se ve quién editó o
  eliminó una novedad, que es la contrapartida de la libertad que abrió esta tarea.
- **`Vacaciones/Index.vue` y `Licencias/Index.vue` siguen fuera de la lista `PANTALLAS` de
  `useListado.test.js`** —vienen así desde F2-05—, aunque sí usan el composable. La lista de
  `useNovedad.test.js` sí incluye a las tres.
- Las notas de arquitectura de REV-01/REV-02 y las de F2-03 a F2-09 sobre el flujo compartido de
  novedades siguen vigentes y no se repiten aquí.
