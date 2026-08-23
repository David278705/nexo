# Estado del proyecto

**Última sesión:** 2026-08-23 · Tarea F5-01 (lector de plantillas)
**Verificación:** verde — Pint, PHPStan sin errores, **992** pruebas de Pest, build y 286 de Vitest.
**Siguiente tarea:** F5-02 · Ejecución de la carga.

## Qué se hizo

- **F5-01, el lector de plantillas y su modo simulación.** `app/CargaInicial/` con
  `LectorDeArchivo`, `SimulacionDeCarga`, `ResultadoDeSimulacion`, `PlantaDeLaEmpresa`,
  `CatalogoDePlantillas` y las seis clases de `Plantillas/`, más el comando
  `carga:simular {empresa} {plantilla} {archivo}`. Pruebas nuevas en
  `tests/Feature/CargaInicialTest.php`, 28 casos.
- **El «hecho cuando» está probado literalmente.** Un archivo de cuatro filas con tres errores
  deliberados —una fecha ilegible, una EPS que no está en el catálogo y el saldo vacío— devuelve
  tres errores en las filas **3, 4 y 5**, que son las del Excel, y `personas` y `vinculaciones`
  siguen en cero después de correrlo.
- **Las seis plantillas de `plantillas/` se leen de verdad**, no de mentira: hay un caso que abre
  los seis archivos reales y comprueba que ninguna de sus columnas falta. Es lo que ata las
  definiciones del código a los archivos que se le entregan al cliente.
- **Se aprovechó el trabajo que había sin commitear en `app/CargaInicial/`** —`Columna`,
  `TextoDeCelda`, `LecturaDeCelda`, `CatalogosDeCarga`, `FilaTraducida`, `ErrorDeCarga`,
  `TipoDeCelda`, `CeldaIlegible`—, que era el cimiento correcto y estaba bien razonado. Lo que le
  faltaba era todo lo de arriba: el lector, la simulación, las plantillas y las pruebas.

## Decisiones tomadas

- **Las reglas de validación de cada plantilla son las del formulario de su módulo, sin copiar
  ninguna.** La fila ya traducida se le pasa al `FormRequest` correspondiente y de ahí salen
  `rules()`, `messages()` y `attributes()`. Es la decisión de fondo de toda la carga: con reglas
  propias, la plantilla acabaría admitiendo por archivo lo que la pantalla rechaza, y el cliente
  tendría dos verdades. El precio es que la carga hereda también las comprobaciones contra catálogo
  del formulario, que ya sabemos redundantes; se pagó a gusto.
- **Lo que la plantilla añade son condiciones del archivo y no de la pantalla**, cada una con su
  fuente: el saldo de vacaciones obligatorio (documento 2, 6.2, la única ausencia que manda
  bloquear), la fecha de ingreso no futura —la plantilla dice que ahí van «únicamente las personas
  vinculadas hoy», y el formulario sí admite una fecha futura porque registrar a quien empieza el
  lunes es legítimo—, el documento sin repetir dentro del archivo, y la coherencia del último
  movimiento salarial con la planta, que la propia hoja de instrucciones de la plantilla 6 promete.
- **Las columnas se buscan por título y nunca por posición.** Una columna insertada por descuido
  convertiría treinta valores en treinta campos del vecino, cada uno guardado y ninguno marcado como
  error. La columna que falte se señala en la fila 1 y el resto del archivo se lee igual, para que el
  informe enseñe de una vez todo lo que hay que arreglar.
- **La hoja se busca por su nombre y, si no aparece, por su fila de títulos**, exigiendo que
  reconozca **más de la mitad** de las columnas. Con una sola columna en común —las seis plantillas
  empiezan por «Número de documento»— el archivo de vacaciones pasaría por el de incapacidades.
- **Un error de digitación no puede acabar en un error de base de datos.** Cuando un municipio o un
  departamento no se resuelven, los dos campos se vacían antes de validar: dejar ahí el nombre
  escrito haría que `Rule::exists` buscara un número donde hay letras, y eso aborta la transacción de
  PostgreSQL en vez de producir una línea de informe.
- **El lugar de expedición del documento llega como un municipio suelto** —la plantilla no trae ni
  país ni departamento—. Se resuelve contra los municipios de Colombia y, cuando el nombre está
  repetido (hay dos Albania y dos Guadalupe), se pide escribirlo como «Albania (La Guajira)» en vez
  de escoger uno. Escoger el primero dejaría a alguien con el documento expedido en otro
  departamento sin que nadie se enterara nunca.
- **`TipoDeCelda` estrenó `Dinero`**, que se lee como texto de dos decimales. El decimal se queda
  para lo que no es dinero —los días de vacaciones con fracción—; el dinero nunca viaja en coma
  flotante, que es la convención del proyecto.
- **La pantalla de carga inicial no se construyó aquí.** El documento 2, 6.1 la describe entera
  —subir, simular, confirmar— y confirmar es F5-02: partirla en dos dejaría media pantalla sin el
  botón que le da sentido. El comando `carga:simular` hace hoy los pasos 2 y 3 y no escribe nada, ni
  siquiera un registro de que se ejecutó.

## Preguntas para Angela

- **La fila de ejemplo de la plantilla 1 escribe «Sura» donde el catálogo dice «Sura EPS», y «Sura»
  donde dice «ARL Sura».** El sistema las rechaza a propósito: adivinar que «Sura» es «Sura EPS» es
  exactamente lo que la carga no puede hacer. Como la fila 2 es un ejemplo que el cliente debe borrar
  no rompe ninguna carga, pero enseña un nombre que no existe. ¿Se corrige el ejemplo del archivo?
- **La plantilla 3 marca «Código de diagnóstico» como opcional y el sistema lo exige.** La columna
  `incapacidades.codigo_diagnostico` no admite nulos y `RegistrarIncapacidad` lo pide obligatorio,
  así que hoy manda el formulario y la carga lo exige. ¿Se corrige la plantilla, o el código de
  diagnóstico pasa a ser opcional en el módulo también?
- **La plantilla 2 ofrece «Menos de 30 min» y el sistema no tiene ese rango**: lo parte en «Menos de
  15 minutos» y «Entre 15 y 30 minutos», que son los que vinieron en el archivo de listas de D.5. La
  carga lo rechaza enumerando los cinco rangos reales; traducirlo obligaría a escoger uno por el
  cliente. ¿Se cambian los cuatro rangos de la plantilla por los cinco del sistema?
- **Heredadas, las cinco de sesiones anteriores**: los tres nombres del filtro «Estado del contrato»,
  el texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto
  del contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».

## Decisiones para David

- **La plantilla 1 no trae país de expedición del documento**, así que hoy la carga solo admite
  municipios de Colombia en esa columna, mientras que el formulario admite cualquier país. A quien se
  le expidió el documento en el exterior habrá que completarlo por pantalla. Si eso no vale, la
  salida limpia es agregarle a la plantilla la columna de país, no adivinarlo.
- **`REV-06` sigue tuya: acotar la regla de «la fecha entre paréntesis».** Es la tercera revisión que
  la levanta y la recomendación está escrita en la tarea.
- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Todo el motor de alertas está construido y probado;
  hasta que eso se haga, ningún correo sale de la máquina.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días,
  la prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el
  texto del empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo
  sobre el saldo negativo. La «plantilla de carga» sale de esa lista: las seis existen, se leen y
  están probadas.

## Notas para la próxima sesión

- **La siguiente tarea es F5-02, la ejecución de la carga**, y buena parte de lo que necesita ya
  está puesto. `ResultadoDeSimulacion::valores()` entrega las filas limpias ya traducidas a campos
  del sistema, con la misma forma que reciben `RegistroDeEmpleado` y los cinco formularios de
  novedad. El orden de dependencia del documento 2, 6.3 está escrito en `CatalogoDePlantillas::todas()`
  y no hace falta una segunda lista.
- **Lo que F5-02 tiene que decidir y F5-01 dejó a propósito sin decidir:** qué hacer con las dos
  columnas del contrato vigente —«Fecha de inicio del contrato vigente» y «Prórrogas ya realizadas»—,
  que en el sistema viven en `periodos_contractuales` y no en la vinculación. Hoy se leen y se
  comprueban, y nadie las convierte en tramos del historial.
- **El saldo de la carga no necesita periodos falsos y no hay que inventárselos.**
  `saldo_inicial_vacaciones` y `fecha_corte_saldo` existen en `vinculaciones` desde F2-02 justamente
  para eso. La plantilla 4 se puede entregar vacía y el sistema sigue acumulando bien desde el saldo.
- **La ficha del empleado tiene un dato que la plantilla no trae: `fecha_corte_saldo`.** La plantilla
  1 pide los «días de vacaciones disponibles **hoy**», así que el corte es la fecha de la carga. Vale
  la pena que quede escrito en el registro de la carga y no deducido después.
- **`User`, `Acceso` e `Invitacion` siguen sin `SoftDeletes`**, abierto desde F1-13.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** Su contenido está en el commit de
  F3-06. **No hay que aplicarlo**: traería la versión vieja de `TipoDeAlerta`.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` estaban modificados al empezar esta sesión y se dejaron
  sin commitear**: son cambios del bucle de desarrollo, ajenos a F5-01.
