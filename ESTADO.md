# Estado del proyecto

**Última sesión:** 2026-08-27 · Tarea REV-12
**Verificación:** verde — Pint, PHPStan sin errores, **1.227** pruebas de Pest, build y **393** de
Vitest.
**Siguiente tarea:** `F5-08` · El formulario de acceso de prueba no sugiere ningún valor de vigencia.

## Qué se hizo

`REV-12`: las dos pestañas de la ficha de empresa del proveedor que seguían con texto de fases que ya
existen. **Alertas** enseñaba «El motor de avisos es la fase 3»; **Uso** decía «las novedades por mes
llegan en la fase 2». Las dos fases están completas desde hace tiempo y nadie volvió a conectar las
pestañas.

Se borraron las dos constantes muertas de `App\Proveedor\FichaDeEmpresa` —`PENDIENTES` y
`USO_INCOMPLETO`—, y con ellas la prop `pendientes` y el `EstadoVacio` de reserva de
`resources/js/Pages/Proveedor/FichaDeEmpresa.vue`. La ficha ya no tiene ninguna pestaña que dependa de
una tarea futura.

**Pestaña «Alertas».** El nuevo `FichaDeEmpresa::alertas()` entra al `ContextoTenant::paraTenant()` de
la empresa —`alertas_enviadas` lleva `PerteneceATenant` y su política RLS, que no tiene excepción para
el `owner`— y trae las 50 más recientes, mapeadas con `ConsolaDeAlertas::fila()`, el mismo método que
usa la consola del capítulo 7. Si hay más de 50, la pantalla remite a `/proveedor/alertas?empresa={id}`,
que es la que tiene los filtros y la paginación. Sin envíos, muestra el estado vacío («Todavía no se le
ha enviado ninguna alerta»), no un aviso de fase pendiente.

**Pestaña «Uso».** Gana dos bloques nuevos, los dos calculados dentro del contexto de la empresa:
`novedadesPorMes` cuenta por `created_at` —cuándo se cargó la novedad, no la fecha del hecho— las cinco
novedades (incapacidades, vacaciones, licencias, movimientos, terminaciones) de los últimos seis meses;
`modulos` da, por cada uno de esos cinco módulos, cuántos registros tiene y cuándo fue el último, con la
marca «en uso» / «sin uso». `Formato::mesYAnio()` es nuevo: «Agosto de 2026» como encabezado de fila.

## Decisiones tomadas

- **«Módulos que efectivamente usa» se leyó como los cinco módulos de novedades**, no como todos los
  del menú. Empleados y Contratos se cargan en la puesta en marcha y siempre tienen datos; la
  conversación de renovación es sobre qué módulos opcionales reciben uso real, que es lo que dice la
  tabla de la sección 4.3 del documento 4 al ponerlo junto a «novedades registradas por mes».
- **«Novedades por mes» se cuenta por `created_at`**, no por la fecha del hecho: la pregunta de la
  pestaña es con qué frecuencia la empresa entra a trabajar, no cuándo ocurrieron las incapacidades.
- **La pestaña «Alertas» de la ficha no reenvía ni filtra**: es un registro de solo lectura acotado a
  las 50 últimas. Reenviar y filtrar ya viven en la consola del capítulo 7, y la ficha enlaza a ella
  filtrada por la empresa en vez de duplicar esa pantalla.

## Preguntas para Angela

- (vacío)

## Decisiones para David

- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway, en
  `BACKLOG.md` y en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Mientras tanto, `MAIL_MAILER=log` y ninguna alerta sale de la máquina de verdad —
  aunque en este momento sí hay Mailtrap configurado en el `.env` local para pruebas.
- **Verificar a mano en un navegador la ficha de empresa del proveedor tras REV-12**: abrir
  `/proveedor/empresas/{id}` de una empresa con alertas enviadas y novedades cargadas, y comprobar que
  las pestañas «Alertas» y «Uso» se ven como el prototipo describe.
- **Verificar a mano la pestaña «Facturación» de REV-11** sigue pendiente: `/configuracion` como
  superadministrador de una empresa con suscripción y de una sin ella.
- **Verificar a mano el flujo completo de REV-10** sigue pendiente: invitar a un administrador, marcar
  y desmarcar módulos, y desactivarlo, todo desde `/configuracion`.
- **Las dos preguntas para Angela siguen abiertas**: B.2 y el fondo de cesantías. Están en
  `docs/06-decisiones-pendientes.md`, secciones B.2 y en la nota de F1-04.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **La siguiente tarea es `F5-08`, de una sola línea**: poner 14 como valor sugerido en el formulario
  de accesos de prueba (F5-03), con el número en `config/panel.php` —el mismo archivo que ya tiene
  `riesgo_de_cancelacion`— y no en un literal del componente Vue. D.12 quedó resuelta en 14 días.
- **Con REV-12, las siete pestañas de la ficha de empresa del proveedor ya tienen contenido real.** No
  queda ninguna constante de «pendiente» en `App\Proveedor\FichaDeEmpresa` ni ningún `EstadoVacio` de
  reserva en su pantalla.
- **`ConsolaDeAlertas::fila()` es ahora un método compartido por dos pantallas** —la consola y la
  ficha—. Si cambia su forma, las dos la leen.
- **Si una tarea futura agrega otro sitio que lea `User->usuario` de una línea histórica**, revisa si
  necesita `withTrashed()` como `Auditoria::usuario()`.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
