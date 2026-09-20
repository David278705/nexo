# Estado del proyecto

**Última sesión:** 2026-09-20 · Tarea `HD-16`
**Verificación:** verde. 1.308 de 1.308 pruebas de Pest, Pint y PHPStan sin errores, build y 415 de
Vitest — dos pruebas de Pest más que `HD-15`, las de Vitest no cambian.
**Siguiente tarea:** ninguna. **El backlog está completo**: no queda ninguna tarea `[ ]`. Quedan las
bloqueadas `[!]` y las decisiones de abajo, que esperan a una persona.

## Qué se hizo

`HD-16` pedía que la nota del saldo de vacaciones nombrara los cuatro términos de la fórmula.

- **La nota** (`FichaDeEmpleado::notaDelSaldo()`) dice ahora: saldo inicial de la carga, más 1,25 días por
  mes cumplido (desde el ingreso o desde la fecha de corte), menos los días de vacaciones ya disfrutados,
  menos la reducción por licencias, ausencias y suspensiones si la empresa tiene ese parámetro activo.
- **El 1,25 sale de `reglas_laborales.dias_vacaciones_por_mes`**, la misma constante del motor, y no de un
  número escrito en el texto.
- **Un solo sitio:** el encabezado de la ficha y el modal de novedades ya compartían
  `saldoDeVacaciones()`, y la pantalla de vacaciones enseña sus propios números sin nota. No había texto
  repetido que quitar; se dejó el método privado y documentado.
- **Pruebas** (`FichaDeEmpleadoTest.php`): una arma saldo inicial, acumulación, un disfrute y una suspensión,
  comprueba 10 + 5 − 5 − 0,625 = 9,375, que quitar el disfrute o la suspensión (`SaldoDeVacaciones::sin()`)
  mueve el saldo exactamente en su parte, y que la nota nombra cada término; otra, que ficha y modal llevan
  la misma nota.

## Decisiones tomadas

- **La reducción por suspensiones se dice condicional.** En el motor solo aplica si la empresa activó
  `suspensiones_afectan_vacaciones` (documento 2, 3.4), y una nota que la presentara como incondicional
  sería falsa para las empresas que lo tienen apagado.
- **La prueba ata la nota a la fórmula por comportamiento**, no leyendo el comentario de la clase: si la
  fórmula pierde o gana un término, los importes esperados se rompen; si la nota calla uno, se rompen las
  frases. No detecta un quinto término nuevo que nadie mencione en ninguno de los dos lados.

## Preguntas para Angela

- (vacío, no cambia respecto a la sesión anterior)

## Decisiones para David

- **`HD-12` mencionaba una «carga masiva de terminaciones» que no existe.** Las seis plantillas de
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
  calendario nativo respeta el rango), la de `HD-12` (registrar una salida para un contrato a término
  fijo vencido y comprobar que el mensaje se lee bien debajo de «Fecha de salida»), la de `HD-13`
  (mirar la barra lateral y comprobar que el encabezado «Cambios y salidas» cabe sin cortarse), la de
  `HD-14` (abrir `/incapacidades` y comprobar que con la columna EPS la tabla cabe sin desbordar, y
  pulsar «Días» para ver el orden en los dos sentidos), la de `HD-15` (mirar la barra lateral y
  comprobar que «Licencias, ausencias y suspensiones», 35 caracteres en una barra de 236 px, se parte en
  dos líneas sin cortarse, y que el botón «Nueva licencia, ausencia o suspensión» cabe junto a
  «Exportar» en escritorio y en móvil) y ahora también `HD-16`: abrir una ficha y comprobar que la
  nota nueva, bastante más larga que la anterior, se lee bien bajo «Saldo de vacaciones» tanto en el
  encabezado como en el modal de novedades, sin desbordar ni empujar los otros datos.
- **La tarea `HD-10` decía «siete plantillas» y el código solo tiene seis** (`CatalogoDePlantillas::todas()`).
  Si te referías a otra cosa —por ejemplo, contar también
  `Listas_Desplegables_Formulario_Empleados.xlsx`, que no es una plantilla que se cargue sino la
  fuente de los catálogos—, dilo y se ajusta. Por ahora `HD-10` se resolvió sobre las seis reales.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.

## Notas para la próxima sesión

- **No hay tarea `[ ]` en el backlog.** Antes de inventar trabajo, conviene un punto de control con
  `/revisar` (la serie `HD-*` tocó ficha, licencias, incapacidades y plantillas) y resolver las
  decisiones de arriba, que son lo que de verdad desbloquea más tareas.
- **Para correr un archivo de Pest suelto que use `usuarioDe()`** hay que incluir `RolesYPermisosTest.php`,
  y `empleadoParaNovedad()` vive en `FlujoDeNovedadTest.php`. Fuera de `paraTenant()` las consultas
  devuelven vacío —también `FichaDeEmpleado::de()` falla, porque su persona no se resuelve—: una prueba
  puede pasar en vacío si no afirma la lista exacta.
- **El entorno local no sobrevive al cierre de la sesión.** PostgreSQL sí, que es servicio de brew;
  `php artisan serve --port=8014` hay que relanzarlo. Para el bucle largo conviene `composer dev`.
