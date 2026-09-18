# Estado del proyecto

**Última sesión:** 2026-09-18 · Tarea `HD-09`
**Verificación:** verde. 1.271 de 1.271 pruebas de Pest, Pint y PHPStan sin errores, build y 409 de
Vitest — seis pruebas más que `HD-08`, las de las prórrogas de la plantilla de empleados.
**Siguiente tarea:** `HD-10` · Los ejemplos de las plantillas no coinciden con los catálogos del sistema.

## Qué se hizo

`HD-09` pedía que la plantilla de empleados dejara registrar las prórrogas de un contrato a término
fijo una por una: hoy solo guardaba `Prórrogas ya realizadas` —un número— y `Fecha de inicio del
contrato vigente` —una fecha que, además, nunca se convertía en nada: se validaba y se descartaba—,
así que con alguien que entró en 2018 con varias prórrogas no había dónde poner cuándo empezó y
terminó cada una.

- **Doce columnas nuevas reemplazan a las dos viejas.** `Fecha de inicio de la prórroga N` / `Fecha
  de fin de la prórroga N`, para N de 1 a 6 (documento 6, D.7). `columnasDeProrrogas()` las genera y
  `PlantillaDeEmpleados::columnas()` las coloca justo después de `Fecha de finalización del
  contrato`, el mismo sitio lógico que ocupaban las dos columnas que reemplazan.
- **La validación vive en `completar()`, no en `reglas()`.** Es una cadena con estado —cada prórroga
  depende de la anterior— y el patrón ya establecido para eso en esta plantilla es `$fila->error()`
  dentro de `completar()`, igual que la resolución de departamento/ciudad. Tres métodos:
  - `prorrogasEscritas()` recorre las seis y devuelve los números con las dos fechas completas, en
    orden; si hay un par a medias o una prórroga saltada, anota el error y devuelve `null` sin seguir.
  - `prorrogas()` decide si el bloque aplica: si el contrato no es a término fijo, cualquier prórroga
    escrita es un error («son solo para el contrato a término fijo»).
  - `encadenarProrrogas()` comprueba que la primera empiece después de la fecha de ingreso, que cada
    una siguiente empiece el día después de que termina la anterior, y que la última cargada termine
    el mismo día que «Fecha de finalización del contrato» —si no coincidiera, `registrarPeriodo()` la
    sobreescribiría en silencio más adelante, así que se prefiere rechazar el archivo a adivinar cuál
    de las dos fechas es la buena—.
  - Ninguna revalida una celda que ya falló al leerse (`$fila->yaFallo()`): una fecha ilegible o un
    `tipo_contrato` que no se pudo traducir no producen un segundo mensaje encima del primero.
- **`escribir()` construye el historial real**, no solo escribe la vinculación. `registrarProrrogas()`
  corrige primero el periodo inicial —que `ObservadorDeVinculacion` crea con la fecha de finalización
  *vigente* porque no sabe de prórrogas— al día anterior al inicio de la primera prórroga, y encadena
  cada tramo llamando a `Vinculacion::registrarPeriodo()`, el mismo método que usa «Renovar contrato»
  desde la pantalla. Una prórroga cargada y una digitada acaban idénticas en `periodos_contractuales`.
- **El tipo de cada prórroga se guarda como pactada**, sin preguntarlo. La plantilla solo pide las dos
  fechas, y C.1 (corregida por Angela) ya estableció que el tipo no cambia ningún cálculo: el cupo de
  tres prórrogas cortas es de la cadena entera, sin mirar si cada una fue pactada o automática. Guardar
  un tipo fijo es una decisión de ingeniería, no una regla laboral inventada.
- **C.1 y C.2 quedan evaluadas sobre el historial real sin escribir código nuevo para eso.** Con
  tramos de verdad en `periodos_contractuales`, `HistorialDeContrato::contratoInicialEsInferiorAUnAnio()`
  y `prorrogasCortas()` —C.1— y `fechaDelTopeLegal()` —C.2— ya leían el historial correcto; lo que
  estaba mal antes era el historial mismo, no las cuentas.
- **`prorrogas_previas_a_la_carga` (REV-09) se dejó intacto.** No se tocó `RegistroDeEmpleado`,
  `HistorialDeContrato`, `ListadoDeContratos`, `DetalleDelContrato` ni las tres alertas que la leen:
  la columna sigue existiendo, pero ningún camino nuevo la vuelve a escribir —la plantilla ya no
  manda `prorrogas_realizadas`—, así que queda en su valor por defecto (0) para toda carga futura y
  solo sirve de respaldo si alguna vez hay datos guardados con la plantilla anterior. Tocar esos cinco
  archivos para borrarla del todo era más riesgo del que esta tarea pedía resolver.
- **La plantilla real de `plantillas/1 - Empleados actuales (obligatoria).xlsx`** se editó con el
  método seguro de siempre: el XML de `sheet1.xml` (Instrucciones), `sheet2.xml` (Empleados) y
  `comment1.xml` a mano con `ZipArchive`, comparado archivo por archivo contra el original antes de
  dejarlo —ningún otro archivo interno del `.zip` cambió—. Las columnas físicas `Z` y `AA` (antes
  «Fecha de inicio del contrato vigente» y «Prórrogas ya realizadas») se repropósitan en su sitio como
  el primer par, con su validación de fecha y su estilo de columna de fecha; los cinco pares restantes
  se agregaron al final del archivo (`AJ` a `AS`), siguiendo el mismo patrón ya usado por REV-08,
  HD-06 y HD-08: el lector busca cada columna por su título, no por su posición. Las instrucciones
  ganaron diez filas nuevas y el bloque general de «Contratos a término fijo» se reescribió para
  explicar el encadenamiento.
- **Pruebas**: siete casos nuevos en `tests/Feature/CargaInicialTest.php` — el caso completo de David
  (ingreso en 2018, tres prórrogas, dos de un año exacto y una corta) verificando los cuatro tramos,
  la fecha de fin vigente y que `prorrogasAcumuladas()`, `contratoInicialEsInferiorAUnAnio()` y
  `prorrogasCortas()` leen el historial reconstruido; y cinco de validación (prórroga saltada, par
  incompleto, contrato no fijo, primera prórroga antes del ingreso, hueco entre prórrogas, y
  desajuste con la fecha de finalización del contrato).

## Decisiones tomadas

- **El tipo de cada prórroga cargada se guarda como «pactada».** La plantilla no lo pregunta —D.7 solo
  pide pares de fecha— y C.1 ya resolvió que el tipo no cambia ningún cálculo del sistema, así que fijar
  un valor no es inventar una regla laboral, es una decisión de qué constante usar donde el archivo no
  trae el dato.
- **El fin del periodo inicial se deriva, no se pregunta aparte.** Con al menos una prórroga cargada,
  el fin del contrato original es el día anterior al inicio de la primera: es la misma regla de
  contigüidad que ya usa `Vinculacion::renovar()` para calcular dónde empieza el tramo siguiente, así
  que no hacía falta una columna nueva para ese dato.
- **`prorrogas_previas_a_la_carga` no se eliminó.** Quitarla de raíz tocaba `RegistroDeEmpleado`,
  `HistorialDeContrato`, dos pantallas y tres alertas por un beneficio puramente estético —ya no la
  escribe nadie—, y el riesgo de romper algo de esas cinco piezas no lo justificaba dentro de esta
  tarea. Si en algún momento se confirma que no hay datos reales que dependan de ella, retirarla es
  candidato a una tarea `REV-` propia.

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
  verdad), la de `HD-07` (Cundinamarca/Bogotá en el asistente), la de `HD-08` (departamento y ciudad
  de expedición en la plantilla real) y ahora también `HD-09`: abrir la plantilla real de
  `plantillas/` en Excel de verdad y comprobar a ojo que los doce campos de prórroga se ven bien, se
  pueden llenar y que la fila de ejemplo no quedó rara (aquí solo se verificó con PhpSpreadsheet y
  comparando el `.zip` byte a byte contra el original, no con Excel de verdad).
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-10` es la siguiente**: los ejemplos de las plantillas (`SURA` en vez de `Sura EPS`,
  `suspension` sin tilde) no coinciden con los catálogos del sistema, y las instrucciones no traen las
  listas de valores admitidos. Toca las siete plantillas y `app/CargaInicial/Columna.php` —comparación
  sin distinguir mayúsculas ni tildes—.
- **El método seguro para tocar un `.xlsx` real** sigue siendo el mismo: copiarlo aparte primero,
  decodificar las entidades numéricas (`&#243;`) a UTF-8 literal antes de comparar cadenas —esta
  sesión mezclaba las dos formas dentro del mismo archivo y el primer intento de reemplazo falló por
  eso—, editar el XML del `.zip` a mano con `ZipArchive::addFromString()` —nunca cargar y volver a
  guardar el libro completo con `PhpOffice\PhpSpreadsheet\Writer\Xlsx`, que corrompe la tabla de
  cadenas y borra los comentarios en este archivo en particular—, verificar con PhpSpreadsheet **y**
  comparando cada entrada del `.zip` contra el original antes de dejarlo. Cuando una columna nueva no
  necesita reemplazar a una vieja, agregarla al final (`AJ` en adelante aquí) sigue siendo más seguro
  que insertarla en medio: el lector busca por título, no por posición.
- **`./vendor/bin/pest` a secas se queda sin memoria** en la exportación a Excel, con el
  `memory_limit` de 128M del php.ini de esta máquina. `composer verificar` no: corre con el límite
  que le pone Composer. Para correr la batería entera, `composer verificar` o
  `php -d memory_limit=1G vendor/bin/pest`.
- **Un archivo de pruebas de Feature corrido solo, fuera de `composer verificar`, puede fallar por
  una función global no cargada** (por ejemplo `ownerEnElPanel()`, definida en `CrearEmpresaTest.php`
  y usada también en `CargaInicialTest.php`): Pest solo la resuelve cuando descubre el árbol completo
  de pruebas. No es una prueba rota; es correr un archivo aislado. `composer verificar` la corre
  siempre completa y ahí sí queda verde.
- **Dentro de `app(ContextoTenant::class)->paraTenant(...)`, todo lo que dependa del global scope de
  `PerteneceATenant` tiene que resolverse dentro del mismo closure.** Una relación ya cargada con
  `with()` se puede leer después sin problema, pero una consulta nueva —incluida la que arma
  `HistorialDeContrato::de()` al pedir `->historial()`— hecha fuera del closure no encuentra nada: el
  contexto de empresa ya se restableció. El primer intento de la prueba del historial de HD-09 falló
  exactamente por esto.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
