# Estado del proyecto

**Última sesión:** 2026-08-22 · Tarea F2-11 · PUNTO DE CONTROL — revisión de la fase 2
**Verificación:** verde
**Siguiente tarea:** F3-01 · Infraestructura de alertas

## Qué se hizo

- **La revisión completa de la fase 2**, con el informe en `bitacora/revision-2026-08-22.md`. Los
  cuatro primeros bloques —aislamiento, motor, interfaz y seguridad— **sin hallazgos**; el quinto,
  deuda, con tres duplicaciones arregladas y una tarea nueva.
- **Aislamiento:** las diez tablas de negocio llevan trait, política RLS y su caso propio.
  `AislamientoTest` tiene 41 casos y siete bloques por rutas reales. Ningún `where('tenant_id', ...)`
  nuevo: el único de todo el código sigue siendo el de `Auditoria`, justificado desde F1-13.
- **Motor:** los diez casos obligatorios pasan —hay 18 en total— y **no queda ningún conteo laboral
  fuera de `app/Dominio/Calculo/`**. Las tres reglas conservan sus tres nombres distintos y no existe
  ninguna columna de saldo. Ni un número legal suelto.
- **Interfaz y seguridad:** ni un color literal, un solo `<table>`, las ocho pantallas de listado con
  su estado vacío propio, ninguna confirmación que pregunte «¿está seguro?», toda ruta bajo `auth` y
  `modulo:`, ni un borrado físico y la auditoría escrita en un solo sitio.
- **Arreglado: el bloque de paginación estaba copiado ocho veces.** REV-01 extrajo el armazón de la
  consulta, pero cada controlador seguía armando a mano las quince líneas de la prop con la página.
  Ahora es `Listado::paraLaPantalla($clave, $fila)`, que devuelve las cuatro props enteras. Los ocho
  controladores perdieron 120 líneas y ganaron 32.
- **Arreglado: la lectura de las dos fechas digitadas estaba copiada tres veces.** Es
  `App\Novedades\PeriodoDigitado`, con `de()` y `diasCorridos()`, y su prueba unitaria de seis casos.
- **Arreglado: la guardia `PANTALLAS` de `useListado.test.js` cubría seis de las ocho pantallas.**
  `Vacaciones/Index.vue` y `Licencias/Index.vue` faltaban desde F2-05. Están las ocho.
- `composer verificar` en verde: Pint, PHPStan sin errores, **727** pruebas de Pest (8 nuevas), build
  y **275** de Vitest (2 nuevas). Ninguna prueba existente se tocó.

## Decisiones tomadas

- **`fila()` sigue sin heredarse.** `paraLaPantalla()` la recibe como argumento en vez de declararla
  método abstracto, que era la alternativa. Cada listado tiene sus columnas y no hay nada que
  compartir ahí; convertirla en abstracta obligaría a una firma común que ningún listado quiere. Es
  la misma decisión de REV-01, sostenida.
- **La copia del panel de empresas ya se había separado.** `EmpresaController` escribía `total` antes
  que `pagina`. Es inofensivo en JSON, y por eso vale como aviso: ocho copias no se mantienen
  alineadas solas ni cuando la diferencia no rompe nada.
- **`PeriodoDigitado` se probó una vez, en `tests/Unit/`, y no tres veces en cada módulo.** Lo que
  importa de esa clase es que los tres módulos decidan **igual** qué periodo ya se puede contar; eso
  no se demuestra probándolo por separado en cada uno.
- **Las dos guardias contra la copia se escribieron como prueba, no como comentario.** Un caso lee
  los ocho controladores buscando `currentPage()` y otro lee las ocho pantallas buscando
  `URLSearchParams`. La regla de «no vuelvas a copiar esto» solo se sostiene si algo se pone rojo.
- **No se tocó nada de comportamiento.** La revisión no escribe funcionalidad nueva: los tres
  arreglos son extracciones sin cambio de salida, y las 719 pruebas anteriores pasan sin tocarlas.

## Preguntas para Angela

- (vacío, ninguna nueva.)

## Decisiones para David

- **La regla de «la fecha entre paréntesis» sigue sin acotar, y ahora tiene fecha límite: F3-01.**
  `.claude/rules/30-interfaz.md` la enuncia para toda la interfaz; su fuente —documento 1, 9.1— la
  enuncia solo para los mensajes de las alertas. Se recomienda acotarla a los correos y dejar para
  pantalla la versión débil que el código ya cumple y el prototipo dibuja. En los correos la regla es
  literal y viene con ejemplos; decidirlo antes de escribir la primera plantilla cuesta una línea,
  después cuesta reescribirlas.
- **`REV-03`, las dos preguntas siguen abiertas** —si eliminar un movimiento devuelve el salario y el
  cargo anteriores, y si eliminar una terminación devuelve al empleado a activo—. Sin respuesta no se
  toma.
- **Todas las heredadas siguen abiertas**: meses comerciales de treinta días, prórroga mínima,
  plantilla de carga, fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md`, la frase del prototipo sobre el saldo
  negativo, y el resto de la lista larga de REV-02.

## Notas para la próxima sesión

- **La siguiente es F3-01, la infraestructura de alertas**, y con ella empieza lo que el producto
  vende. Se hace con el skill `agregar-alerta`.
- **F3-01 estrena jobs y comandos programados, que hoy no existen.** Es el primer sitio donde el
  contexto de empresa en segundo plano se puede perder: hay que restablecerlo al empezar y limpiarlo
  al terminar **aunque el job falle**, y recorrer las empresas una por una, nunca con una consulta
  global. Es lo primero que hay que revisar en el siguiente punto de control.
- **`REV-04` es nueva y está al final del backlog**: la pantalla de auditoría, que no tenía tarea en
  ninguna fase. No es urgente para la fase 3, pero F5-07 la da por hecha.
- **Auditoría es el último `SeccionPendiente` del entorno del cliente.** Al darle pantalla hay que
  tocar las mismas cuatro listas compartidas: la exclusión de `routes/web.php`, la de
  `EstructuraTest.php`, `componenteDe()` en `FlujoDeNovedadTest.php` y `PANTALLAS` en
  `useListado.test.js` —esta última ya tiene las ocho pantallas de listado, así que sumar la novena
  es el gesto que cierra la lista—.
- **`User`, `Acceso` e `Invitacion` siguen sin `SoftDeletes`.** Importa el día que exista «Usuarios y
  permisos», no antes.
- Las notas de arquitectura de REV-01/REV-02 y las de la fase 2 sobre el flujo compartido de novedades
  siguen vigentes y no se repiten aquí.
