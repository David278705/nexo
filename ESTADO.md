# Estado del proyecto

**Última sesión:** 2026-09-19 · Tarea `HD-11`
**Verificación:** verde. 1.282 de 1.282 pruebas de Pest, Pint y PHPStan sin errores, build y 410 de
Vitest — ocho pruebas de Pest más que `HD-10` y una de Vitest más.
**Siguiente tarea:** `HD-12` · Se puede terminar un contrato cuyo estado nadie ha aclarado.

## Qué se hizo

`HD-11` pedía que el cambio a término indefinido dejara de registrarse siempre con la fecha de hoy:
David encontró contratos vencidos sin gestionar que en realidad ya se habían vuelto indefinidos días
antes, y hasta ahora no había forma de decirlo.

- **`CambioAIndefinido` gana dos límites explícitos**, `limiteInferior()` —el inicio del periodo
  vigente— y `limiteSuperior()` —hoy, salvo que el contrato todavía no haya empezado, en cuyo caso
  sube hasta el mismo límite inferior—. Es el mismo ajuste de coherencia que ya existía en F1-11,
  ahora nombrado y reutilizado en vez de repetido.
- **`fechaDelCambio()` deja de llamar a `Carbon::today()` a ciegas**: recibe la fecha ya validada por
  el formulario y solo por defecto —sin argumento— propone `limiteSuperior()`.
- **`CambiarATerminoIndefinido` valida `fecha`** con `after_or_equal`/`before_or_equal` contra esos
  dos mismos límites. En el contrato que aún no ha empezado los dos límites coinciden, así que ese
  caso queda cubierto por la misma regla, sin una excepción aparte.
- **La quinta implicación del modal dejó de decir «con la fecha de hoy»**: ahora dice «con fecha del»
  seguido de la fecha que de verdad se va a guardar, para que nunca prometa una cosa y guarde otra.
- **`ModalDeCambioAIndefinido.vue` estrenó un `CampoFecha`**, propuesto en `cambio.fecha` y con
  recarga parcial (`only: ['cambioAIndefinido']`) al estilo de `ModalDeRenovacion`: cada vez que se
  digita otra fecha, las cinco implicaciones se vuelven a pedir al servidor.
- **`CampoFecha` y `CampoTexto` ganaron los props opcionales `minimo`/`maximo`**, que se enlazan al
  `min`/`max` nativo de `input[type=date]`. Es cortesía del navegador y no reemplaza la validación:
  el servidor sigue rechazando lo que esté fuera de rango aunque alguien edite el HTML.
- **Ocho pruebas nuevas en `CambioAIndefinidoTest.php`**: la propuesta y el rango, la fecha pasada
  aceptada, el texto sin «hoy» literal, los dos rechazos (antes del periodo vigente, después de hoy),
  el caso silencioso de una fecha fuera de rango en pantalla (vuelve a la propuesta por defecto sin
  reventar), el contrato futuro (solo admite la fecha del periodo vigente) y que el historial quede
  con la fecha elegida y no con la de hoy.

## Decisiones tomadas

- **El límite superior de la fecha, y no una regla aparte, es lo que resuelve el contrato que aún no
  ha empezado.** En vez de escribir una excepción para ese caso, `limiteSuperior()` sube hasta
  `limiteInferior()` cuando este cae después de hoy, así que `after_or_equal` y `before_or_equal`
  terminan pidiendo la misma fecha sin que la regla lo sepa. Es el mismo comportamiento de F1-11, solo
  que ahora nombrado.
- **Una fecha digitada en pantalla que cae fuera de rango se ignora en silencio y vuelve a la
  propuesta por defecto**, igual que ya hacía `RenovacionDeContrato::resolver()` con una prórroga a
  medio digitar: no es un error todavía, es una petición GET de la pantalla mientras se escribe. El
  error de verdad solo aparece si se envía el formulario con esa fecha, y ahí sí lo bloquea
  `CambiarATerminoIndefinido`.
- **`minimo`/`maximo` en `CampoFecha` son nuevos y opcionales**, sin valor por defecto que afecte a
  ningún otro de los más de veinte usos existentes del componente.

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
  de expedición en la plantilla real), la de `HD-09` (los doce campos de prórroga en la plantilla
  real), la de `HD-10` (abrir en Excel de verdad las plantillas de empleados e incapacidades) y ahora
  también `HD-11`: abrir el modal de «Cambiar a término indefinido» en el navegador de verdad y
  comprobar que el calendario nativo respeta el rango y que el texto se lee bien con una fecha pasada.
- **La tarea decía «siete plantillas» y el código solo tiene seis** (`CatalogoDePlantillas::todas()`).
  Si te referías a otra cosa —por ejemplo, contar también
  `Listas_Desplegables_Formulario_Empleados.xlsx`, que no es una plantilla que se cargue sino la
  fuente de los catálogos—, dilo y se ajusta. Por ahora `HD-10` se resolvió sobre las seis reales.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-12` es la siguiente**: se puede terminar un contrato a término fijo del 1 de enero al 31 de
  marzo de 2026 que ya venció sin que nadie lo haya gestionado. Leer el enunciado completo en
  `BACKLOG.md` antes de empezar.
- **El patrón «recarga parcial al digitar» ya tiene dos ejemplos gemelos**: `ModalDeRenovacion.vue`
  con `only: ['renovacion']` y ahora `ModalDeCambioAIndefinido.vue` con
  `only: ['cambioAIndefinido']`. Si una tercera acción del contrato necesita lo mismo, el patrón ya
  está probado dos veces y no hay que inventarlo de nuevo: `useForm` con los campos digitados,
  `watch` sobre ellos que dispara `router.get(url, campos, { only: [...], preserveState: true,
  preserveScroll: true, replace: true })`, y un segundo `watch` sobre `abierto` que limpia errores al
  cerrar.
- **`CampoFecha` y `CampoTexto` ya aceptan `minimo`/`maximo`** para el `min`/`max` nativo de
  `input[type=date]`. Si otra pantalla necesita acotar un calendario, ya no hay que tocar el
  componente compartido, solo pasar los dos props.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
