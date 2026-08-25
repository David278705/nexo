# Estado del proyecto

**Última sesión:** 2026-08-25 · Tarea REV-09
**Verificación:** verde — Pint, PHPStan sin errores, **1.195** pruebas de Pest, build y **388** de
Vitest.
**Siguiente tarea:** `REV-10` · Usuarios y permisos, la pantalla del cliente que nunca se construyó.

## Qué se hizo

`REV-09`: la plantilla de carga masiva de empleados pedía «Prórrogas ya realizadas» y la validaba,
pero nunca la usaba — un contrato a término fijo cargado con prórrogas anteriores en el mundo real
arrancaba en el sistema como si tuviera cero, y `HistorialDeContrato::prorrogasCortas()` —el cupo
único de tres del artículo 46, corregido en la sesión anterior— contaba mal.

La solución, ya decidida en el propio backlog antes de esta sesión: una columna nueva
`prorrogas_previas_a_la_carga` en `vinculaciones` (entero, por defecto 0), rellenada solo cuando la
carga la trae. **No se inventan tramos**: la plantilla solo trae un conteo, no la fecha ni el tipo de
cada prórroga, y reconstruir la cadena habría violado el «no se inventan registros» del documento 1.
`HistorialDeContrato::prorrogasCortas()` suma esta columna al conteo que ya hacía sobre
`periodos_contractuales`, y de ahí sale corregido todo lo que dependía de ese número:
`prorrogasCortasRestantes()`, la validación de F1-10 (bloquea o advierte según
`config/validaciones.php`) y la alerta `UltimaRenovacionCorta`. Los cinco sitios que construían el
historial con `HistorialDeContrato::con()` —el listado y el detalle de contratos, y las tres reglas de
alerta del término fijo— tenían el mismo hueco por el mismo motivo: `con()` no pedía el número.
Todos quedaron corregidos para pasarlo.

El historial que se ve en pantalla (ficha del empleado y detalle del contrato) sigue mostrando
únicamente los tramos que de verdad se registraron; debajo aparece una línea aparte,
`HistorialDeContrato::notaDeCargaInicial()`, con el texto literal del backlog —«Y 2 prórrogas
anteriores a la carga inicial, sin detalle.»— solo cuando el número es mayor que cero.

Seis pruebas nuevas: dos en `PeriodosContractualesTest.php` (la suma al cupo sin crear tramos, y que
un contrato registrado a mano no tiene ninguna previa), dos en `CargaInicialTest.php` (extremo a
extremo desde el archivo hasta la vinculación, y que el registro manual no se afecta) y dos en
`RenovacionTest.php` (el aviso ámbar de «gasta el último cupo» en la primera prórroga corta después de
la carga, y el bloqueo en la que de verdad se pasa del cupo real).

## Decisiones tomadas

- **Precisión sobre el «Hecho cuando» de la tarea, no una reinterpretación de la regla de negocio.**
  El texto del backlog decía que la advertencia de prórroga mínima debía salir «al intentar registrar
  solo su primera prórroga corta después de la carga». Con el cupo en tres y dos prórrogas previas, al
  contrato le queda **un** cupo, no cero: esa primera prórroga lo agota y avisa en ámbar («gasta el
  último cupo, y la siguiente deberá ser de mínimo un año»), que es exactamente el mismo aviso que ya
  recibía un contrato con dos tramos cortos reales antes de esta tarea. La regla de prórroga mínima
  —la que bloquea o advierte según la configuración— dispara en la prórroga *siguiente*, la que de
  verdad excede el cupo, igual que ya lo hacía un contrato con tres tramos cortos reales frente a un
  cuarto (documento 1, sección 5.5: «ya acumula tres... y la nueva también es inferior a un año»). No
  toqué ese mecanismo —es de F1-10/F3-04, ya probado y en producción de facto— porque cambiarlo para
  encajar con la redacción literal del backlog habría roto el disparo ya correcto de los contratos con
  tramos reales. Las dos pruebas de `RenovacionTest.php` cubren ambos momentos, así que el efecto de
  la tarea queda demostrado igual: antes de esta sesión, un contrato cargado con dos previas hubiera
  aceptado tres prórrogas más sin avisar nada; ahora solo acepta una.
- **`prorrogas_previas_a_la_carga` tiene su valor por defecto también en `$attributes` del modelo**, no
  solo en la columna. Sin eso, una vinculación recién creada en la misma petición (sin recargarse)
  respondía `null` en vez de `0`, y `HistorialDeContrato` necesita el número ya en la primera lectura
  —lo detectó la prueba de carga inicial, no una inspección manual—.

## Preguntas para Angela

- (vacío)

## Decisiones para David

- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway, en
  `BACKLOG.md` y en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Mientras tanto, `MAIL_MAILER=log` y ninguna alerta sale de la máquina de verdad —
  aunque en este momento sí hay Mailtrap configurado en el `.env` local para pruebas.
- **Verificar a mano en un navegador el flujo completo de F6-06** sigue pendiente — ninguna sesión de
  código puede hacer esa comprobación por ti.
- **Las dos preguntas para Angela siguen abiertas**: B.2 y el fondo de cesantías. Están en
  `docs/06-decisiones-pendientes.md`, secciones B.2 y en la nota de F1-04.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **La siguiente tarea es `REV-10`**: la pantalla de Usuarios y permisos del cliente, que nunca se
  construyó, con la deuda de `SoftDeletes` en `User`, `Acceso` e `Invitacion` incluida en su alcance.
  Es una de las dos tareas más grandes del backlog junto con `REV-11` (Facturación).
- **Si una tarea futura toca `HistorialDeContrato::con()`, revisa que le pase
  `prorrogas_previas_a_la_carga`** de la vinculación. Ya son seis los sitios que lo hacen
  (`ListadoDeContratos`, `DetalleDelContrato`, `ContratosVencidosSinGestionar`, `CercaniaAlTope`,
  `ReglaDePreavisoDelFijo` y `HistorialDeContrato::de()`), pero un sitio nuevo que arme el historial a
  partir de tramos ya cargados y no pase este número volvería a tener el hueco de REV-09 para ese
  camino en particular.
- **`REV-12` es más chica**: conectar las pestañas «Alertas» y «Uso» de la ficha de empresa del
  proveedor a datos que ya existen.
- **`F5-08` es de una sola línea**: poner 14 como valor sugerido en el formulario de accesos de
  prueba.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
