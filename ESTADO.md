# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F4-04 (panel de inicio, indicadores)
**Verificación:** verde — Pint, PHPStan sin errores, **962** pruebas de Pest, build y 286 de Vitest.
**Siguiente tarea:** F4-05 · PUNTO DE CONTROL — revisión (ejecuta el skill `revisar`).

## Qué se hizo

- **F4-04, los seis bloques de indicadores del panel de inicio** (documento 1, 12.1): Vacaciones,
  Planta, Rotación, Ausentismo, Composición y Movimientos, en el orden del documento, debajo del
  bloque de acción de F4-03.
- `App\Inicio\Indicadores\`: seis clases, una por bloque, más `BloqueDeIndicadores` como orquestador.
  Cada bloque devuelve la misma forma —`titulo`, `tarjetas`, `distribuciones` y a veces `avisos`—, así
  que `Pages/Inicio.vue` los pinta con un solo bucle. Llegan en la prop diferida `indicadores`.
- `CacheDeSaldos` ganó `conAcumulacionExcesiva()`: un conteo agregado sobre la caché de saldos, para
  que el bloque de Vacaciones no tenga que recorrer la planta persona por persona.
- `resources/js/Components/DistribucionLista.vue`, componente nuevo: lista de conteos por categoría
  con su barra proporcional, compartida por las seis distribuciones del panel.
- `Formato` ganó dos ayudantes de presentación: `porcentaje()` y `mes()`.
- `tests/Feature/PanelDeIndicadoresTest.php`, la batería nueva: un caso por indicador, los permisos
  por módulo, el aislamiento entre empresas y la medición de los dos segundos con doscientos empleados
  y la caché ya refrescada.

## Decisiones tomadas

- **Los índices de rotación y de ausentismo usan la fórmula estándar de cada indicador**, porque
  ningún documento trae una fórmula y no es un valor legal: es aritmética del propio indicador, con el
  mismo criterio de implementación con que F3-05 fijó la cadencia de los meses impares. Rotación:
  salidas del mes sobre el promedio de activos del mismo mes, por cien. Ausentismo: días perdidos por
  incapacidad sobre activos por días transcurridos del mes, por cien —ni el numerador ni el
  denominador miran más allá de hoy, para no inflar el índice de los primeros días del mes con una
  incapacidad certificada por adelantado—.
- **«Días disfrutados en el periodo» (Vacaciones) se leyó como el mes en curso hasta hoy**, que es lo
  que enseña el prototipo («Días disfrutados en agosto»). Es una decisión de implementación, no una
  regla legal.
- **«Principales motivos de salida» no recorta a un top.** No existe un criterio para decidir cuántos
  son «los principales» —la misma razón por la que D.2 descartó un umbral para la obra o labor
  prolongada—, así que se muestran todos los motivos del mes, de mayor a menor, sin cortar la lista.
- **Composición queda con dos de sus cuatro indicadores bloqueados por D.4**, que sigue **BLOQUEANTE**
  en el documento 6: faltan los cortes de los rangos de edad y de antigüedad. Cargo, género y la
  antigüedad **promedio** —un número, no un reparto— sí se calculan enteros; el bloque trae un aviso
  que dice de qué depende lo que falta, igual que hizo F1B-03 con las pestañas del panel del proveedor
  que dependían de una decisión sin cerrar.
- **Vacaciones lee de la caché de saldos y no del motor**, para las dos cifras que exigirían recorrer
  la planta persona por persona («días acumulados» y «personas con acumulación excesiva»). Es la misma
  razón de ser de la caché (F4-02): ninguna decisión sobre una persona concreta depende de que el
  número sea exacto al segundo, así que la aproximación de la caché —nunca la fuente de verdad— es
  aceptable aquí, a diferencia de la tarjeta de acción de `AtencionDelInicio`, que sí calcula en vivo
  porque decide a quién enlaza el listado.
- **Movimientos no agrega la proporción que el documento 12.2 descartó.** El reparto «2 aumentos · 1
  promoción» es una nota que descompone la misma cifra total, no un indicador nuevo.

## Preguntas para Angela

- **Heredadas, las cinco de sesiones anteriores**: los tres nombres del filtro «Estado del contrato»,
  el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».

## Decisiones para David

- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6. El resto de F4-04 quedó
  completo sin ella.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Hasta que se haga, ninguna alerta sale de la máquina.
- **Todas las demás heredadas siguen abiertas.** Y detrás, la lista larga de REV-02 (ya cerrada como
  tarea, pero con deuda menor pendiente en otros frentes): la regla de la fecha entre paréntesis fuera
  de los correos, meses comerciales de treinta días, prórroga mínima, plantilla de carga, fondo de
  cesantías, la prueba intermitente de `CatalogosTest`, el texto del empleado inactivo, PHP 8.2 contra
  el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F4-05, punto de control de revisión.** Ejecuta el skill `revisar` sobre todo
  lo construido en la fase 4 —F4-01 a F4-04— y deja lo encontrado arreglado o convertido en tareas
  `REV-`.
- **F4-04 no dejó ningún indicador del capítulo 12.1 sin construir salvo los dos de D.4.** Cuando esa
  decisión llegue, falta añadir dos distribuciones más al bloque de Composición —mismo patrón que
  cargo y género, con `DistribucionLista.vue` ya listo para reutilizar— y quitar el aviso.
- **`REV-03` y `REV-04` siguen al final del backlog**, sin cambios.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
