# Estado del proyecto

**Última sesión:** 2026-09-20 · Tarea `HD-12`
**Verificación:** verde. 1.297 de 1.297 pruebas de Pest, Pint y PHPStan sin errores, build y 410 de
Vitest — quince pruebas de Pest más que `HD-11`, Vitest sin cambios.
**Siguiente tarea:** `HD-13` · El grupo «Movimientos» del menú contiene «Movimientos» y «Terminaciones».

## Qué se hizo

`HD-12` pedía bloquear la terminación de un contrato a término fijo que venció sin que nadie
registrara qué pasó después (D.7 de `docs/06-decisiones-pendientes.md`, decidida por David el
2026-09-16). El código lo dejó escrito la sesión del 19 de septiembre sin llegar a confirmarlo; esta
sesión lo revisó, corrió la verificación completa en verde y cerró la tarea.

- **`App\Terminaciones\ContratoSinAclarar`** responde una sola pregunta: ¿la fecha de salida deja un
  vacío después del último vencimiento registrado? Solo aplica al término fijo con fecha de
  vencimiento en el último tramo de `HistorialDeContrato`; indefinido y obra o labor no tienen fecha
  contra la que medir y no se ven afectados.
- **El vacío corre del día siguiente al vencimiento hasta la fecha de salida, ambos incluidos.** En el
  caso de David (prórroga hasta el 30 de junio, salida el 10 de septiembre): «los días del 1 de julio
  de 2026 al 10 de septiembre de 2026». Si es un solo día, el mensaje lo dice en singular. Salir el
  mismo día del vencimiento o antes sigue funcionando igual.
- **`RegistrarTerminacion` lo comprueba** después de las reglas propias del campo y lo devuelve como
  error de `fecha_salida`, que el modal ya pintaba debajo de ese campo. Si el empleado o la fecha ya
  tienen otro error, no se añade este.
- **`EditarTerminacion` lo hereda** sobrescribiendo solo `vinculacionDeLaSalida()`: corregir una
  salida hacia una fecha posterior al vencimiento se bloquea igual que registrarla.
- **El mensaje nombra las dos salidas** —registrar la prórroga o el cambio a término indefinido, en
  Contratos— y la razón (sin saber qué contrato regía no se sabe qué indemnización aplicaría).
- **Quince pruebas nuevas** en `TerminacionDeContratoSinAclararTest.php`: el caso exacto, el singular,
  que se desbloquea al registrar la prórroga que cubre o al pasar a indefinido, que una prórroga corta
  sigue bloqueando con las fechas nuevas, y lo que la regla no toca.

## Decisiones tomadas

- **El mensaje no es un enlace a Contratos**, es texto: quien registra terminaciones puede no tener el
  módulo de contratos, y un enlace que lleva a un 403 es peor que decir dónde ir.
- **El error va en `fecha_salida`**, el dato que hay que cambiar o que obliga a aclarar primero el
  contrato, y no en un error general del formulario.
- **No se construyó la carga masiva de terminaciones** que menciona el «Hecho cuando»: no existe (ver
  la pregunta para David abajo). Crearla habría sido inventar alcance.

## Preguntas para Angela

- (vacío, no cambia respecto a la sesión anterior)

## Decisiones para David

- **`HD-12` menciona una «carga masiva de terminaciones» que no existe.** Las seis plantillas de
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
  calendario nativo respeta el rango) y ahora también `HD-12`: en el navegador, registrar una salida
  para un contrato a término fijo vencido y comprobar que el mensaje se lee bien debajo del campo
  «Fecha de salida».
- **La tarea `HD-10` decía «siete plantillas» y el código solo tiene seis** (`CatalogoDePlantillas::todas()`).
  Si te referías a otra cosa —por ejemplo, contar también
  `Listas_Desplegables_Formulario_Empleados.xlsx`, que no es una plantilla que se cargue sino la
  fuente de los catálogos—, dilo y se ajusta. Por ahora `HD-10` se resolvió sobre las seis reales.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-13` es la siguiente**: `Modulo::grupo()` agrupa `Movimientos` y `Terminaciones` bajo un
  encabezado llamado, también, «Movimientos». Leer el enunciado completo en `BACKLOG.md`.
- **`ContratoSinAclarar::para($vinculacion, $fechaDeSalida)`** es reutilizable si otra pantalla
  necesita saber si un contrato a término fijo quedó en el aire.
- **La bitácora de `HD-12` lleva fecha 2026-09-19** (`bitacora/2026-09-19-HD-12.md`) porque el código
  se escribió ese día; el cierre fue el 20. No hace falta renombrarla.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
