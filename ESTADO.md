# Estado del proyecto

**Última sesión:** 2026-09-16 · Tarea `HD-02`
**Verificación:** verde. **1.241 de 1.241** pruebas de Pest, Pint y PHPStan sin errores, build y 405
de Vitest.
**Siguiente tarea:** `HD-03` · Ninguna novedad comprueba si se traslapa con otra.

## Qué se hizo

`FichaDeEmpleado::resumen()` daba siempre los mismos seis datos, estuviera la persona activa o no, y
la terminación era invisible en la ficha: solo se veía la etiqueta «Inactiva» arriba, sin fecha, sin
motivo, sin rastro de la salida en ninguna de las cuatro pestañas.

- **Tres datos nuevos, solo para quien ya no está activo**: Fecha de salida, Tipo de salida y Motivo,
  agregados al final de los seis de siempre cuando `$vinculacion->estaActiva()` es falso. Quien está
  activa recibe exactamente el mismo arreglo de seis que antes —ni un hueco donde irían los tres
  nuevos—.
- **«Cuánto duró la vinculación» no se repite.** «Antigüedad» ya está entre los seis de siempre y ya
  mide hasta `fechaDeSalida()` desde F2-09: es exactamente esa cuenta, así que agregarla otra vez con
  otro nombre habría sido decir lo mismo dos veces.
- **El tipo y el motivo salen de `terminacion()`**, la relación `HasOne` que ya existía en
  `Vinculacion` desde F2-09. Se agregó a los `loadMissing()` de `FichaDeEmpleado::de()`.
- **`terminacion` puede no existir.** La fábrica de pruebas `Vinculacion::terminada()` ya desactivaba
  sin registrar una terminación —previendo la carga histórica que F5-02 todavía no trae, donde solo
  hay `fecha_fin`—. Con eso probado, el tipo y el motivo se escriben con la raya (`Formato::VACIO`) en
  vez de romper la ficha; la fecha de salida sigue viniendo de `fechaDeSalida()`, que ya sabía caer al
  respaldo de `fecha_fin`.
- **Caso de aislamiento nuevo**, siguiendo el patrón que dejó HD-01: `terminacion` cuelga de
  `vinculacion_id`, que es único en toda la base y no por empresa. La prueba cuelga una segunda
  terminación de la empresa B del mismo empleado de la A, que ya tiene la suya propia, y comprueba que
  la ficha de la A sigue enseñando su propio motivo y no el ajeno.

## Decisiones tomadas

- **Los tres datos van en el mismo arreglo `resumen()`**, no en un bloque aparte de la pantalla. Es
  donde el «hecho cuando» los pide —«junto a los datos de siempre»— y es el único sitio donde ya
  existe el patrón de `dato()` con etiqueta, valor, pendiente y nota; `ResumenDeEmpleado.vue` no
  necesitó ningún cambio porque ya pinta lo que le llegue en `datos` sin asumir que son seis.
- **El motivo se etiqueta «Motivo», no «Motivo de salida»**, para que combine con la columna del mismo
  nombre en el listado de terminaciones (`Terminaciones/Index.vue`).

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
- **Verificación manual de esta tarea**: entrar a la ficha de Valentina Cárdenas, que es la persona con
  la que se encontró el fallo, registrarle una terminación si todavía no la tiene, y comprobar que
  aparecen su fecha de salida, su tipo de salida y su motivo.
- **Verificaciones manuales heredadas y todavía sin hacer**: la ficha de empresa del proveedor tras
  REV-12, el formulario de acceso de prueba tras F5-08, la pestaña «Facturación» de REV-11 y el flujo
  completo de REV-10.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **`HD-03` antes que `HD-04`, y en ese orden están.** La detección de traslapes que construye
  `HD-03` es la que `HD-04` necesita para saber qué días se pisan.
- **`HD-03` toca `RegistrarVacacion` y sus tres gemelas** —incapacidades, licencias— más la edición de
  cada una. La comprobación tiene que vivir en un solo sitio reutilizable, no copiada en cada
  `FormRequest`: es el mismo tipo de deuda que `PeriodoDigitado` y `Listado::paraLaPantalla()` ya
  resolvieron en F2-11.
- **`HD-06`, `HD-08` y `HD-09` tocan las tres la plantilla de empleados.** Están seguidas a
  propósito: hacerlas juntas evita tres migraciones de plantilla y tres rondas de pruebas.
- **`./vendor/bin/pest` a secas se queda sin memoria** en la exportación a Excel, con el
  `memory_limit` de 128M del php.ini de esta máquina. `composer verificar` no: corre con el límite
  que le pone Composer. Para correr la batería entera, `composer verificar` o
  `php -d memory_limit=1G vendor/bin/pest`.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
