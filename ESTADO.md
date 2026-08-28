# Estado del proyecto

**Última sesión:** 2026-08-27 · Tarea REV-11
**Verificación:** verde — Pint, PHPStan sin errores, **1.223** pruebas de Pest, build y **391** de
Vitest.
**Siguiente tarea:** `REV-12` · Conectar «Alertas» y «Uso» de la ficha de empresa del proveedor.

## Qué se hizo

`REV-11`: la pestaña «Facturación» de Configuración, la cuarta y última que le faltaba a la sección
5.7 —«Plan contratado, número de empleados y usuarios incluidos, historial de pagos y medio de pago.
Solo el superadministrador.»—. F5-04 ya había calculado y probado exactamente esta información del
lado del proveedor (`PanelDeSuscripciones`, `FichaDeEmpresa::suscripcion()`); esta tarea no inventó
ningún modelo de datos ni ninguna regla de mora, solo leyó lo mismo acotado a la empresa en contexto.

`App\Configuracion\FacturacionDeLaEmpresa` sigue la misma forma que `ParametrosDeLaEmpresa` y
`UsuariosDeLaEmpresa`: una clase con `de($empresa)->paraLaPantalla()`, sin ninguna escritura. Si la
empresa no tiene `suscripcion()` —hoy posible porque `planes` nace vacía por la decisión A.1—, devuelve
un estado vacío que lo dice; si la tiene, devuelve el nombre y estado del plan, sus cupos de empleados
y usuarios, el medio de pago, el valor mensual, el próximo cobro y el historial de pagos ordenado del
más reciente al más antiguo, con el mismo enumerado `EstadoDeFactura` que ya usa el proveedor.
**Ninguna acción vive aquí**: registrar un pago, cambiar de plan, suspender y reactivar siguen siendo
decisiones del proveedor sobre su cliente (documento 4, 4.3), no de esta pantalla de solo lectura.

`ConfiguracionController::parametros()` combina ahora las tres clases —`ParametrosDeLaEmpresa`,
`UsuariosDeLaEmpresa` y `FacturacionDeLaEmpresa`— en un solo `Inertia::render`. **«Solo el
superadministrador» no necesitó ningún candado nuevo**: el módulo `Configuracion` ya es exclusivo del
superadministrador desde F1-12 (`Modulo::esSoloDelSuperadministrador()`, aplicado por
`PoliticaDeModulos` a toda la ruta `/configuracion`), así que un administrador ya recibía 403 antes de
esta tarea; la prueba nueva solo lo deja explícito para esta pestaña en particular.

`Parametros.vue` reemplazó el `EstadoVacio` genérico de «Facturación» por contenido real: el nombre del
plan con su `Etiqueta` de estado, la lista de cupos y medio de pago con `ListaDeDatos`, y la tabla del
historial de pagos con `TablaDatos` —mismas columnas y el mismo patrón de `Etiqueta` por tono que ya
usa `Proveedor/FichaDeEmpresa.vue`—. Una empresa sin suscripción ve el estado vacío sin ningún botón de
acción, porque abrir una suscripción no es una acción del cliente.

## Decisiones tomadas

- **No se mostró el conteo de empleados/usuarios *en uso* contra el cupo**, solo los cupos incluidos
  en el plan. La sección 5.7 pide «número de empleados y usuarios incluidos», que es el cupo del plan
  (`Plan::empleados_incluidos`/`usuarios_incluidos`), no un contador de uso real; inventar esa
  comparación habría sido una funcionalidad no pedida por el documento.
- **No se mostró el detalle de suspensión ni de reactivación** (motivo, fecha) que sí enseña la ficha
  del proveedor: la sección 5.7 no lo pide para el cliente, y el estado general («Activa»/«Suspendida»)
  con su tono ya comunica la situación sin exponer el motivo interno que escribe el proveedor.

## Preguntas para Angela

- (vacío)

## Decisiones para David

- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway, en
  `BACKLOG.md` y en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Mientras tanto, `MAIL_MAILER=log` y ninguna alerta sale de la máquina de verdad —
  aunque en este momento sí hay Mailtrap configurado en el `.env` local para pruebas.
- **Verificar a mano en un navegador la pestaña «Facturación» de REV-11** sigue pendiente: abrir
  `/configuracion` como superadministrador de una empresa con suscripción y de una sin ella, y
  comprobar que se ve como el prototipo describe.
- **Verificar a mano en un navegador el flujo completo de REV-10** sigue pendiente — ninguna sesión de
  código puede hacer esa comprobación por ti: invitar a un administrador, marcar y desmarcar módulos, y
  desactivarlo, todo desde `/configuracion`.
- **Las dos preguntas para Angela siguen abiertas**: B.2 y el fondo de cesantías. Están en
  `docs/06-decisiones-pendientes.md`, secciones B.2 y en la nota de F1-04.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **La siguiente tarea es `REV-12`**: conectar las pestañas «Alertas» y «Uso» de la ficha de empresa
  del proveedor a datos que ya existen —`alertas_enviadas` y las novedades de la fase 2—, quitando los
  textos de `App\Proveedor\FichaDeEmpresa::PENDIENTES` y `USO_INCOMPLETO` que quedaron de cuando esas
  fases todavía no existían.
- **`F5-08` sigue siendo de una sola línea**: poner 14 como valor sugerido en el formulario de accesos
  de prueba.
- **Con REV-11, las cuatro pestañas de Configuración del cliente ya tienen contenido real.** No queda
  ningún `EstadoVacio` genérico en `Parametros.vue`.
- **Si una tarea futura agrega otro sitio que lea `User->usuario` de una línea histórica** (auditoría,
  bitácora, lo que sea), revisa si necesita `withTrashed()` como `Auditoria::usuario()`: un usuario
  desactivado sigue siendo el autor de lo que hizo mientras tuvo acceso.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
