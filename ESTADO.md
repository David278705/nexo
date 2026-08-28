# Estado del proyecto

**Última sesión:** 2026-08-27 · Tarea REV-10
**Verificación:** verde — Pint, PHPStan sin errores, **1.217** pruebas de Pest, build y **390** de
Vitest.
**Siguiente tarea:** `REV-11` · Facturación, la cuarta pestaña que tampoco se construyó.

## Qué se hizo

`REV-10`: la pestaña «Usuarios y permisos» de Configuración, que ninguna de las seis fases ni las
REV anteriores había construido. F1-12 solo hizo «Parámetros»; «Datos de la empresa», «Usuarios y
permisos» y «Facturación» se quedaron con el `EstadoVacio` genérico. Esta tarea construyó «Usuarios y
permisos» y pagó primero la deuda que la traía consigo: `User`, `Acceso` e `Invitacion` no tenían
`SoftDeletes`, y «un usuario se desactiva, no se borra» (documento 3, 5.7) lo exige.

Tres migraciones nuevas les dan `deleted_at` a las tres tablas. `App\Configuracion\UsuariosDeLaEmpresa`
es la clase nueva, con la misma forma que `ParametrosDeLaEmpresa`: el listado de usuarios de la
empresa activa (un usuario desactivado deja de aparecer, aunque sus líneas de auditoría se conserven),
`crear()` —usuario, módulos e invitación en una sola transacción, reutilizando `Invitaciones` tal como
ya lo hace `CreacionDeEmpresa` del lado del proveedor—, `actualizarModulos()` —reemplaza el conjunto
completo— y `desactivar()`, que es `SoftDeletes::delete()` sin más: `ObservadorDeAuditoria` escribe
sola la línea de «eliminado» porque `User` no estaba en su lista de exclusión, y `SesionController` deja
de encontrar al usuario al iniciar sesión porque el global scope de `SoftDeletes` lo saca de la
consulta por correo, sin que hiciera falta ningún candado aparte.

`ConfiguracionController::parametros()` combina ahora las props de `ParametrosDeLaEmpresa` y
`UsuariosDeLaEmpresa` en un solo `Inertia::render`, y tres rutas nuevas —crear, editar módulos,
desactivar— cuelgan del mismo middleware `modulo:configuracion`, que ya solo lo pasa el
superadministrador. **El superadministrador no se toca desde esta pantalla**: es único por empresa y
el documento no describe una forma de crear un segundo ni de desactivar al único que existe, así que
`ActualizarModulosDeUsuario` y `DesactivarUsuario` responden 403 si el usuario de la dirección no es un
administrador de la propia empresa —`User` no lleva `PerteneceATenant`, así que ese candado lo pone la
petición a mano, como ya hacía `EmpresaController::comprobarQueEsDeLaEmpresa` del lado del proveedor—.

## Decisiones tomadas

- **«Desactivar conserva la auditoría» necesitó dos ajustes que el «Hecho cuando» no nombraba pero que
  se siguen sin inventar nada, solo de la propia consecuencia de agregar `SoftDeletes`.**
  `Auditoria::usuario()` pasó a `belongsTo(...)->withTrashed()`: sin eso,
  `ListadoDeAuditoria::fila()` —que hace `$linea->usuario->name`— se habría quedado mirando `null` en
  cuanto la primera empresa desactivara a alguien, y la pantalla de auditoría (REV-04) habría empezado
  a mostrar huecos donde antes había un nombre. `ListadoDeAuditoria::usuarios()` —el filtro de esa
  misma pantalla— también gana `withTrashed()`, para poder seguir filtrando por el nombre de un
  administrador ya desactivado.
- **`ParametrosDeLaEmpresa::modulosDe()` pasó de privada a pública.** Las dos pantallas —Parámetros,
  con «correos que reciben las alertas», y Usuarios y permisos, con el listado— responden la misma
  pregunta («qué módulos tiene este usuario») con la misma frase, y escribirla dos veces se habría
  separado a la primera corrección.
- **El superadministrador no aparece con acciones en el listado.** El documento no ofrece una forma de
  crear un segundo superadministrador ni de desactivar al único que existe («una sola por cliente»,
  documento 1, capítulo 3), así que la fila lo enseña con «Acceso total, no editable» y ninguna de las
  dos acciones lo acepta como destino, ni siquiera con la petición forzada a mano.
- **`ModalDeBorrado.vue` ganó la prop opcional `etiquetaBoton`** (por defecto «Eliminar», así que los
  cinco usos existentes no cambiaron) para poder decir «Desactivar» sin duplicar el componente: la
  plantilla del documento 3, 6.3 —«Vas a… {consecuencia}. Esta acción queda registrada en la
  auditoría.»— es la misma para las dos acciones.

## Preguntas para Angela

- (vacío)

## Decisiones para David

- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway, en
  `BACKLOG.md` y en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Mientras tanto, `MAIL_MAILER=log` y ninguna alerta sale de la máquina de verdad —
  aunque en este momento sí hay Mailtrap configurado en el `.env` local para pruebas.
- **Verificar a mano en un navegador el flujo completo de REV-10** sigue pendiente — ninguna sesión de
  código puede hacer esa comprobación por ti: invitar a un administrador, marcar y desmarcar módulos, y
  desactivarlo, todo desde `/configuracion`.
- **Las dos preguntas para Angela siguen abiertas**: B.2 y el fondo de cesantías. Están en
  `docs/06-decisiones-pendientes.md`, secciones B.2 y en la nota de F1-04.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **La siguiente tarea es `REV-11`**: la pestaña «Facturación» del lado del cliente, con la misma
  información que `Proveedor\PanelDeSuscripciones` ya calcula del lado del proveedor, de solo lectura.
- **`REV-12` sigue después**: conectar las pestañas «Alertas» y «Uso» de la ficha de empresa del
  proveedor a datos que ya existen.
- **`F5-08` sigue siendo de una sola línea**: poner 14 como valor sugerido en el formulario de accesos
  de prueba.
- **Si una tarea futura agrega otro sitio que lea `User->usuario` de una línea histórica** (auditoría,
  bitácora, lo que sea), revisa si necesita `withTrashed()` como `Auditoria::usuario()`: un usuario
  desactivado sigue siendo el autor de lo que hizo mientras tuvo acceso.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
