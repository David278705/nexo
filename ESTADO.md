# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F5-03 (ambiente de demostración)
**Verificación:** verde — Pint, PHPStan sin errores, **1.038** pruebas de Pest, build y 303 de Vitest.
**Siguiente tarea:** F5-04 · Suscripciones y cobros.

## Qué se hizo

- **F5-03, el ambiente de demostración.** `App\Proveedor\AmbienteDeDemostracion` —una sola empresa
  marcada `es_ambiente_demo` (índice único parcial en `tenants` que impide una segunda), su planta fija
  de ocho personas y `restaurar()`, que vacía con borrado lógico y vuelve a poblar dentro de una
  transacción—, el comando `demostracion:restaurar` programado cada noche a las 3:00 a. m.
  `America/Bogota`, y la pantalla «Demostración» del panel —`DemostracionController`,
  `Pages/Proveedor/Demostracion.vue`— con el estado, el botón «Restaurar ahora» y la lista de accesos
  de prueba activos.
- **Los accesos de prueba con vigencia limitada** (documento 4, capítulo 10 y 12.1). La tabla
  `accesos_de_prueba` —sin `tenant_id` con trait ni RLS, igual que `invitaciones`—, el modelo
  `AccesoDePrueba`, la clase `App\Proveedor\AccesosDePrueba` (generar, listar los activos, revocar) y
  el enlace público `GET /prueba/{token}`, que loguea con la cuenta compartida
  `demo@ambiente-nexo.local` (`Rol::Superadministrador` dentro del tenant demo, así que ve todos los
  módulos). `CerrarAccesoDePruebaExpirado`, middleware nuevo en el grupo `web`, cierra la sesión si el
  acceso vence a la mitad de una visita y no solo se comprueba al entrar.
- **La planta fija tiene ocho situaciones, no datos al azar.** Periodo de prueba, contrato a término
  fijo por vencer, vacaciones ya disfrutadas, una incapacidad reciente, una licencia no remunerada, un
  aumento salarial, un contrato de obra o labor y una salida ya registrada (para los indicadores de
  rotación). Las fechas son relativas a `Carbon::today()` y no fijas: así el contrato a término fijo
  sigue venciendo pronto y la incapacidad sigue siendo reciente sin importar qué día se restaure, sin
  dejar de ser exactamente la misma planta —el «hecho cuando» de la tarea, probado restaurando dos
  veces y comparando los documentos de las ocho personas—.
- **`CatalogosDelSistema` actualizó su fila pendiente.** «Vigencia del usuario de demostración» ya no
  dice «sin decisión escrita»: dice dónde vive el mecanismo (`App\Proveedor\AccesosDePrueba`) y por qué
  sigue sin un número por defecto.

## Decisiones tomadas

- **D.12 no se resolvió inventando un número, se resolvió leyendo quién lo decide.** El documento 6
  deja sin decidir «cuántos días dura un acceso de demostración», pero el documento 4, 12.1, ya dice
  quién lo decide y cuándo: «David genera el acceso». Por eso `dias_de_vigencia` es un campo obligatorio
  del formulario, sin ningún valor puesto de antemano, y no una constante de configuración con un
  número propuesto —el patrón que sí se usó en `config/panel.php` para la salud de uso, y que aquí no
  aplicaba porque esa vigencia sí está atribuida a una persona en el documento y esta también, y el
  documento explícitamente la deja como una decisión por caso—.
- **Hay una sola empresa de demostración, no una por prospecto.** «Accesos de prueba» son sesiones
  temporales hacia el mismo ambiente ficticio compartido, no empresas nuevas: el capítulo 10 habla de
  «el ambiente de demostración» en singular, y una empresa nueva por cada prospecto rompería el sentido
  de «restaurarlo cada noche» —¿cuál de todas?—. El índice único parcial en `tenants.es_ambiente_demo`
  lo hace cumplir en la base de datos, no solo en el código.
- **`restaurar()` no reutiliza el `escribir()` de la carga inicial.** Ese espera filas ya validadas de
  un archivo de Excel, y aquí no hay ningún archivo que inventar: se escribe directamente con los
  modelos, igual que cualquier prueba del sistema. Ya estaba anotado así en la sesión de F5-02.
- **Vaciar el ambiente es borrado lógico, no `truncate`.** La regla 4 del proyecto no hace una excepción
  para los datos ficticios: cada restauración deja el rastro de la anterior en `deleted_at`, así que el
  ambiente acumula filas muertas noche tras noche en vez de perder la historia. Probado explícitamente:
  restaurar dos veces deja 8 personas vivas y 16 en total.
- **Los datos de la planta son fijos, no `Faker`.** Un dato aleatorio sin semilla dejaría el ambiente
  distinto cada noche, que es lo contrario de «siempre luce igual de poblado». Los nombres, los cargos y
  los documentos son los mismos en cada restauración; solo las fechas son relativas a hoy.
- **El acceso de prueba no es de un solo uso.** A diferencia de una invitación, dura varios días y el
  mismo enlace sirve todas las veces que haga falta mientras no venza ni se revoque: lo único que
  registra cada entrada es `ultimo_uso_en`.
- **La cuenta compartida es `Superadministrador`, no un `Administrador` con módulos repartidos.** Un
  prospecto que explora solo tiene que ver todo el sistema, igual que vería el capítulo 10 y no una
  vista recortada por un permiso pensado para un empleado real.

## Preguntas para Angela

- **Las tres de F5-01 siguen abiertas**: «Sura» contra «Sura EPS» y «ARL Sura» en la fila de ejemplo
  de la plantilla 1; el código de diagnóstico que la plantilla 3 marca opcional y el sistema exige; y
  el rango «Menos de 30 min» de la plantilla 2, que el sistema parte en dos.
- **Heredadas, las cinco de sesiones anteriores**: los tres nombres del filtro «Estado del contrato»,
  el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».
- **D.12 sigue formalmente abierta, aunque F5-03 ya no la bloquea.** Sigue sin existir un número
  sugerido de referencia para cuando alguien pregunte «¿cuántos días le pongo normalmente a un acceso
  de prueba?»; hoy el formulario no ofrece ninguno y quien lo genera decide caso por caso.

## Decisiones para David

- **Un contrato a término fijo cargado con prórrogas anteriores arranca contando cero prórrogas.** Las
  dos columnas de la plantilla 1 —«Fecha de inicio del contrato vigente» y «Prórrogas ya realizadas»—
  se leen y se comprueban, pero no se convierten en tramos de `periodos_contractuales`: con un número
  y una sola fecha habría que inventarse las fechas de los tramos intermedios, y 13.1 lo prohíbe con
  esas palabras. Lo que esto afecta es el cupo de tres prórrogas cortas y el tope de cuatro años en
  quien ya venía renovando. La salida limpia es agregarle a la plantilla las fechas de cada tramo, no
  deducirlas.
- **La plantilla 1 sigue sin país de expedición del documento**, así que la carga solo admite
  municipios de Colombia en esa columna. Quien tenga el documento expedido en el exterior habrá que
  completarlo por pantalla.
- **`REV-06` sigue tuya: acotar la regla de «la fecha entre paréntesis».**
- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Hasta que eso se haga, ningún correo sale de la máquina.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F5-04, suscripciones y cobros** (documento 4, capítulo 6): las tablas
  `planes`, `suscripciones` y `pagos`, con estado, mora y suspensión con constancia. Recuerda que la
  tabla `planes` con precios depende de la decisión A.1, que sigue declarada pendiente en el documento
  6 —el plan pactado de cada empresa hoy vive en las cuatro columnas `plan_*` de `tenants`, sin
  catálogo detrás—. «Días de mora antes de suspender» es otro umbral que `CatalogosDelSistema` ya
  declara «sin decidir, llega con F5-04»: la escalera del capítulo 6 propone 5, 15 y 30 días, pero es
  una propuesta y no una decisión escrita.
- **`SeccionDelPanel::pendientes()` perdió una sección más.** Quedan Suscripciones, Alertas, Salud y
  Equipo; de ahí sale la siguiente que se construya.
- **El ambiente de demostración no tiene todavía perfil sociodemográfico ni cargas de la carga
  inicial.** `perfil_sociodemografico_habilitado` quedó en `false` para el tenant demo: si una futura
  sesión quiere mostrar ese bloque en la demo, hay que activarlo y sumarlo a `poblar()`.
- **La cuenta compartida de demostración (`demo@ambiente-nexo.local`) nace con una contraseña aleatoria
  que nadie conoce.** Nadie necesita conocerla: se entra siempre por `/prueba/{token}`, nunca por el
  formulario de acceso normal. Si alguna vez hiciera falta entrar con correo y contraseña, habría que
  decidir esa contraseña aparte.
- **`User`, `Acceso` e `Invitacion` siguen sin `SoftDeletes`**, abierto desde F1-13.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
