# Estado del proyecto

**Última sesión:** 2026-09-16 · Tarea `HD-05`
**Verificación:** verde. **1.258 de 1.258** pruebas de Pest, Pint y PHPStan sin errores, build y 405
de Vitest — el mismo número que dejó `HD-04`, porque esta sesión no logró aportar nada que se
pudiera dejar puesto.
**Siguiente tarea:** `HD-06` · No se guarda el celular ni el correo del empleado.

## Qué se hizo

`HD-05` pedía que el catálogo de festivos cubriera diez años más, además de los tres que ya tiene
(2025-2027), para que una vacación histórica de, por ejemplo, 2023 cuente sus días hábiles contra
el calendario real de ese año en vez de fallar o contar de más.

- **El primer bloqueo es de datos, no de código.** `database/data/festivos.php` dice, en su propio
  comentario, que el calendario **«no se calcula: se escribe y se verifica a mano»**, y
  `.claude/rules/20-calculo-y-reglas-legales.md` lo repite: «Nunca calculados con fórmulas». La razón
  que dan las dos fuentes es la misma que prohíbe inventar reglas de negocio: un festivo mal recordado
  no se nota hasta que un cálculo de vacaciones sale mal para todos los clientes a la vez, y Colombia
  traslada varios festivos con la ley Emiliani con excepciones que rompen cualquier fórmula o
  recuerdo. No tengo forma de verificar a mano diez años de fechas festivas colombianas, así que
  escribirlas de memoria sería exactamente lo que la tarea de origen (`CLAUDE.md`, «no adivines
  valores legales») prohíbe.
- **Se intentó igual la parte de código que no depende del dato**, la cuarta condición del «hecho
  cuando»: que una fecha de un año sin calendario cargado falle con mensaje en vez de contar de más en
  silencio. Se cambió `MotorDeCalculo::festivosEntre()` para lanzar una excepción cuando
  `Festivo::fechasDelAnio($anio)` viene vacío. **Rompió 37 pruebas existentes**: muchas registran solo
  los festivos puntuales que su caso necesita (`Festivo::registrar(...)` suelto), no el calendario
  completo del año, así que para ellas «vacío» significa «no lo necesito, no que falte cargar» — la
  misma lista vacía significa dos cosas distintas según quién pregunte, y no hay forma de
  distinguirlas sin un dato nuevo (un catálogo de «años cargados» separado del catálogo de festivos, o
  cambiar la convención de decenas de pruebas). Es un cambio bastante más grande de lo que parecía y
  no cabía en los dos intentos de esta sesión, así que se revirtió entero. El repositorio quedó
  exactamente como lo dejó `HD-04`.

## Decisiones tomadas

- **Ninguna.** No se guardó ningún cambio de esta sesión: ni la excepción del motor, ni la prueba que
  la acompañaba. `git status` está limpio.

## Preguntas para Angela

- (vacío, no cambia respecto a la sesión anterior)

## Decisiones para David

- **`HD-05` necesita el calendario de festivos de los diez años que le faltan al catálogo, escrito y
  verificado a mano, como ya se hace con 2025-2027 en `database/data/festivos.php`.** No es una
  pregunta de negocio: es exactamente la tarea anual del proveedor que el propio archivo describe,
  aplicada hacia atrás en vez de hacia adelante. Falta decidir también el rango exacto — «los últimos
  diez años» ¿contados desde 2026 hacia atrás (2016-2025) o desde 2025, el primer año ya cargado, hacia
  atrás (2015-2024)? — porque el backlog no lo fija y los dos leen distinto «además de los tres que ya
  tiene». Con esas fechas en mano, la parte de código —que ya se diseñó y se probó, y solo se revirtió
  por los datos que faltan— es una sesión corta.
- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway,
  en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Con `MAIL_MAILER=log` en local, ninguna alerta sale de la máquina de verdad.
- **Verificaciones manuales heredadas y todavía sin hacer**: la ficha de empresa del proveedor tras
  REV-12, el formulario de acceso de prueba tras F5-08, la pestaña «Facturación» de REV-11, el flujo
  completo de REV-10 y la de `HD-04` (incapacidad que interrumpe vacaciones ya registradas).
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-05` quedó `[!]` en el backlog** con el motivo anotado ahí mismo. No se retoma hasta que David
  traiga el calendario de festivos verificado; cuando llegue, el cambio de código ya está pensado
  (lanzar en `MotorDeCalculo::festivosEntre()` cuando el año no tiene ninguna fila), pero hay que
  resolver antes cómo distinguir «año sin cargar» de «prueba que solo registró un festivo suelto» sin
  tocar 37 pruebas una por una — probablemente separando «festivos del año» de «si el año está
  cargado» en dos preguntas distintas al modelo `Festivo`.
- **`HD-06` es la siguiente**: falta celular y correo en `personas`, en el asistente, la plantilla de
  carga, la ficha y el listado de empleados.
- **`HD-06`, `HD-08` y `HD-09` tocan las tres la plantilla de empleados.** Están seguidas a
  propósito: hacerlas juntas evita tres migraciones de plantilla y tres rondas de pruebas.
- **`./vendor/bin/pest` a secas se queda sin memoria** en la exportación a Excel, con el
  `memory_limit` de 128M del php.ini de esta máquina. `composer verificar` no: corre con el límite
  que le pone Composer. Para correr la batería entera, `composer verificar` o
  `php -d memory_limit=1G vendor/bin/pest`.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
