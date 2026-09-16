# Estado del proyecto

**Última sesión:** 2026-09-16 · Tarea `HD-01`
**Verificación:** verde. **1.236 de 1.236** pruebas de Pest, Pint y PHPStan sin errores, build y 404
de Vitest. El «rojo conocido» de la tilde de «Ramírez» ya no existe: se arregló en el commit
`4ccc2f8` de la sesión anterior y la bitácora de aquella sesión no lo recogió. La sección que lo
describía se fue de aquí porque ya no describe nada.
**Siguiente tarea:** `HD-02` · La ficha de un empleado inactivo no dice nada de su salida.

## Qué se hizo

`FichaDeEmpleado::novedades()` era `return []` con un comentario que decía «vacío hasta que existan
sus módulos», y los tres existen desde la fase 2. Ahora junta las tres relaciones que ya estaban en
`Vinculacion` —`incapacidades()`, `vacaciones()` y `licencias()`— en una sola tabla, de la más
reciente a la más antigua.

- **Ninguna cuenta se hace en la ficha.** Los días los da cada modelo, que llama al motor: hábiles en
  vacaciones, corridos en incapacidades y licencias.
- **Dos reglas de conteo en una sola columna se explican, no se disimulan.** Debajo de la tabla hay
  una nota que dice cuál es cuál. Sin ella, la única lectura posible de la columna sería sumarla, y
  sumar días hábiles con días corridos es exactamente lo que la regla 3 quiere evitar.
- **Columna nueva «Detalle»**, con el origen y el diagnóstico de la incapacidad. Las licencias no la
  usan: lo que las distingue es su tipo, y ese va en la primera columna —«Suspensión», «Ausencia»—,
  porque llamar «Licencia» a una suspensión sería decir otra cosa.
- **El diagnóstico dejó de viajar a quien no puede verlo.** Es el dato sensible del documento 1, 6.3.
  En el listado del módulo el permiso se cumplía solo, porque a esa pantalla no se llega sin
  `modulo:incapacidades`; a la ficha se entra con el módulo de **empleados**, que es otro permiso
  distinto. `detalleDeIncapacidad()` pregunta, y sin el módulo escribe solo el origen.
- **El caso de aislamiento es del camino nuevo, no de los tres módulos.** Lo que esta tarea abre es
  leer novedades **por la vinculación** en vez de por su propio listado, y `vinculacion_id` es único
  en toda la base y no por empresa. La prueba crea a propósito las tres novedades de la empresa B
  colgadas del empleado de la A y comprueba que ninguna aparece en su ficha.

## Decisiones tomadas

- **Orden descendente, de la más reciente a la más antigua.** El documento 3, 5.3 dice «en orden
  cronológico», que es una instrucción sobre juntar las tres clases por fecha y no sobre el sentido.
  Se eligió el mismo sentido del histórico de movimientos, que es la pestaña de al lado: en una ficha
  se entra a ver qué pasó últimamente. Es una decisión de presentación y se puede invertir en una
  línea si David la prefiere al revés.
- **El diagnóstico se recorta por módulo, no la novedad entera.** Quien no tiene el módulo de
  incapacidades sigue viendo que hubo una incapacidad, con sus fechas, sus días y su origen: eso es
  información de personal que el módulo de empleados sí le da. Lo único que no ve es el código.
- **La clave de fila es compuesta —`incapacidad-9`, `vacacion-4`, `licencia-2`—** porque los `id` de
  las tres tablas chocan entre sí y `TablaDatos` usa esa clave para pintar la lista.

## Preguntas para Angela

- **La norma que sustenta que la incapacidad interrumpe el disfrute de vacaciones.** Hace falta para
  citarla en `config/reglas_laborales.php` con su fecha de verificación. **No bloquea `HD-04`**: el
  comportamiento ya está decidido y la tarea se puede construir; lo que no se puede es inventar la
  norma.
- **B.2 sigue abierta**: periodo de prueba en cambio de modalidad contractual.
- **El fondo de cesantías sigue abierto**: si hace falta pedirlo, y dónde.

## Decisiones para David

- **`F6-07` sigue esperándote**: los cuatro pasos de la restauración de prueba en Railway,
  en `docs/07-plan-de-incidentes.md` sección 4.
- **`F3-06b` sigue esperándote**: proveedor transaccional, `.env`, DNS (SPF/DKIM/DMARC) y correr
  `alertas:prueba`. Con `MAIL_MAILER=log` en local, ninguna alerta sale de la máquina de verdad.
- **Verificación manual de esta tarea**: entrar a la ficha de Juliana María Gómez Ríos, que es la
  persona con la que se encontró el fallo, y comprobar que aparecen sus tres novedades.
- **Verificaciones manuales heredadas y todavía sin hacer**: la ficha de empresa del proveedor tras
  REV-12, el formulario de acceso de prueba tras F5-08, la pestaña «Facturación» de REV-11 y el flujo
  completo de REV-10.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-02` toca el mismo archivo que esta tarea** —`FichaDeEmpleado`— y es su continuación natural:
  la salida tampoco se ve en ninguna de las cuatro pestañas. `Vinculacion::terminacion()` y
  `fechaDeSalida()` ya existen desde F2-09, así que es un `resumen()` que cambia según el estado, no
  una consulta nueva.
- **`HD-03` antes que `HD-04`, y en ese orden están.** La detección de traslapes que construye
  `HD-03` es la que `HD-04` necesita para saber qué días se pisan.
- **`HD-06`, `HD-08` y `HD-09` tocan las tres la plantilla de empleados.** Están seguidas a
  propósito: hacerlas juntas evita tres migraciones de plantilla y tres rondas de pruebas.
- **`./vendor/bin/pest` a secas se queda sin memoria** en la exportación a Excel, con el
  `memory_limit` de 128M del php.ini de esta máquina. `composer verificar` no: corre con el límite
  que le pone Composer. Para correr la batería entera, `composer verificar` o
  `php -d memory_limit=1G vendor/bin/pest`.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
