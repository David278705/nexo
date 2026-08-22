# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F2-06 · Advertencia de saldo insuficiente
**Verificación:** verde
**Siguiente tarea:** F2-07 · Licencias, ausencias y suspensiones. Módulo con el selector de tres
tipos, días corridos y el efecto proporcional sobre la acumulación de vacaciones.

## Qué se hizo

- **`App\Vacaciones\AdvertenciaDeSaldo`** es la clase nueva de esta tarea, con el mismo patrón que
  `RenovacionDeContrato`: recibe la vinculación, los días solicitados y la fecha de disfrute, y
  devuelve `saldoActual`, `saldoProyectado` y `advertencia` ya escritos, sin que el navegador cuente
  nada. **No cuenta ni un día**: llama a `SaldoDeVacaciones::al()` y `::faltantePara()`, las dos
  desde F2-02, y solo arma el texto —«Al 28 de agosto tendrá 12,75 días disponibles. Estás
  registrando 14 días: faltan 1,25 días.», literal del documento 3, 5.5—.
- **`VacacionController` gana una segunda prop `Inertia::optional`**, `saldoDeVacaciones`, hermana de
  `diasDeVacaciones` y con el mismo parseo de fechas —se extrajo a `periodoDigitado()` para no
  repetirlo dos veces en el mismo archivo—. Resuelve la vinculación desde `vinculacion_id` en la
  consulta; sin ella no hay nada que calcular y la prop llega nula.
- **La franja permanente del documento 3, 5.5** —saldo actual y saldo proyectado a la fecha de
  disfrute, con el aviso ámbar debajo si no alcanza— se pintó en `Pages/Vacaciones/Index.vue`, bajo
  los tres campos del periodo. La recarga parcial que ya pedía `diasDeVacaciones` ahora también pide
  `saldoDeVacaciones` y se dispara con un tercer disparador: elegir al empleado, no solo cambiar una
  fecha, porque el saldo de hoy ya se puede enseñar antes de que existan fechas.
- **No bloquea, y no se tocó nada para que no bloqueara**: ni `RegistrarVacacion` ni
  `VacacionController::guardar()` cambiaron. Es la regla explícita del documento 1, 6.4, y aquí se
  cumplió por omisión: no había ninguna validación de saldo que quitar.
- **`Formato::diaYMes()` es la única pieza nueva de presentación**: «28 de agosto», sin año, porque la
  fecha de disfrute de unas vacaciones nunca está a años de distancia y el ejemplo del documento no
  lleva año.
- **Pruebas nuevas en `VacacionesTest.php`**: el caso exacto de los 14 días para el 28 de agosto
  —comprobando el texto letra por letra—, que no avisa nada cuando el saldo proyectado alcanza, que
  la franja llega con solo el saldo de hoy antes de tener fechas, y que el periodo digitado por HTTP
  llega a la misma franja que se puede calcular de forma independiente (sin volver a contar los días
  a mano en la prueba).
- **`Pages/Vacaciones/Index.test.js` es nuevo**, la primera prueba de Vitest de esta pantalla: hasta
  F2-05 no hacía falta porque toda su lógica ya la cubría `ModalDeNovedad.test.js`. Ahora sí tiene
  lógica propia —qué pide, cuándo, y qué pinta con la respuesta— y quedó cubierta con el mismo
  patrón de interacción por DOM que ya usa `ModalDeNovedad.test.js`.
- `composer verificar` en verde: Pint, PHPStan sin errores, **631** pruebas de Pest, build y **242**
  de Vitest (seis nuevas, las de `Vacaciones/Index.test.js`).

## Decisiones tomadas

- **La franja se muestra en cuanto se resuelve el empleado, no en cuanto hay fechas.** El documento
  dice «franja permanente con el saldo actual», y el saldo de hoy no depende de ninguna fecha del
  formulario: esperar a que se digiten las dos fechas habría escondido un dato que ya se puede
  enseñar. El saldo proyectado sí espera al periodo completo, porque sin fecha de disfrute no hay a
  qué fecha proyectar.
- **La franja del prototipo trae una frase adicional** —«Puedes continuar. Si la empresa aprueba
  vacaciones anticipadas, el saldo quedará en −1,25 días.»— **que no se copió.** No está en el texto
  literal de los documentos 1 ni 3, que son la fuente de verdad funcional; el prototipo es una guía
  visual y no un texto a transcribir letra por letra cuando no coincide con la especificación. Si
  Angela la quiere, es un ajuste de una línea en `AdvertenciaDeSaldo::mensaje()`.
- **La recarga parcial se disparó también al elegir el empleado**, además de al cambiar una fecha,
  porque es el punto en el que `form.vinculacion_id` deja de ser nulo y hay algo que pedir por primera
  vez. Antes de esta tarea el único disparador eran las dos fechas.

## Preguntas para Angela

- (vacío, ninguna nueva.)

## Decisiones para David

- **Todas las heredadas de sesiones anteriores siguen abiertas** —meses comerciales de treinta días,
  prórroga mínima, plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`,
  el texto del empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, si la pestaña de
  novedades de la ficha del empleado muestra o no el diagnóstico de una incapacidad, la frase del
  prototipo sobre el saldo negativo que no se copió, y el resto de la lista larga de REV-02—. Ninguna
  la tocó esta tarea salvo la del saldo negativo, que queda anotada arriba.

## Notas para la próxima sesión

- **La siguiente es F2-07, Licencias, ausencias y suspensiones.** El documento 1, 6.5 pide un solo
  tipo de novedad con un selector de tres opciones —licencia no remunerada, ausencia, suspensión—,
  contadas todas en días corridos (`MotorDeCalculo::diasCorridos()`, ya existente) y con un efecto
  proporcional sobre `acumulacionMes()` que el motor ya deja anotado y en cero a la espera de esta
  tabla, según dejó escrito F2-05.
- **El patrón para copiar sigue siendo el de `app/Vacaciones/`**, con dos diferencias: el conteo es
  `diasCorridos`, no `diasHabilesVacaciones`, y el efecto no es un descuento de saldo sino una
  reducción proporcional de la acumulación del mes o meses que la novedad atraviese —el ejemplo del
  documento es la licencia del 16 de marzo al 15 de abril, que reduce a la mitad la acumulación de
  los dos meses—.
- **Cuando una pantalla real reemplace a `SeccionPendiente`, hay que revisar las mismas dos pruebas
  compartidas de siempre**: `EstructuraTest.php` y `FlujoDeNovedadTest.php::componenteDe()`. F2-07 le
  toca hacer lo mismo con licencias.
- **`AdvertenciaDeSaldo` y `Formato::diaYMes()` quedan disponibles** por si algún aviso futuro necesita
  fechas sin año o el patrón de «franja con proyección más advertencia ámbar sin bloqueo» que ya usó
  esta tarea y antes `RenovacionDeContrato`.
- Todas las notas de arquitectura de REV-02 sobre el armazón de listado, y las de F2-04/F2-05 sobre el
  flujo compartido de novedades y el patrón de cálculo reactivo en el modal, siguen vigentes y no se
  repiten aquí.
