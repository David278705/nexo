# Estado del proyecto

**Última sesión:** 2026-08-18 · Tarea F1-02
**Verificación:** verde
**Siguiente tarea:** F1-03 · Listado de empleados

## Qué se hizo

- **`periodos_contractuales`**, la tabla del historial de contrato (documento 2, 3.3): `tenant_id`,
  `vinculacion_id`, `orden`, `evento`, `tipo_contrato` y las dos fechas, con borrado lógico y su
  política de RLS en la misma migración. Índice único **parcial** sobre `(tenant_id, vinculacion_id,
  orden)` con `where deleted_at is null`, igual que el del documento en `personas`.
- **`App\Contratacion\EventoContractual`**: los cuatro eventos —inicial, prórroga pactada, prórroga
  automática y cambio a indefinido—, con `esProrroga()`, que deja fuera el cambio a indefinido.
- **`App\Models\PeriodoContractual`** con `PerteneceATenant`, borrado lógico, casteos y los scopes
  `enOrden()` y `prorrogas()`. No calcula nada: solo guarda el tramo.
- **`App\Contratacion\HistorialDeContrato`**, el único sitio donde se hacen las cuentas del contrato:
  `prorrogasAcumuladas()`, `prorrogasPactadas()`, `prorrogasAutomaticas()`, `fechaDeVencimiento()`,
  `inicioDelConteoDelTope()`, `duracionTotalEnDias()`, `fechaDelTopeLegal()`, `superaElTopeLegal()` y
  `proximoOrden()`. Se construye con `HistorialDeContrato::de($vinculacion)` o con `::con($periodos)`
  cuando el listado ya los trae cargados.
- **`ObservadorDeVinculacion`**: toda vinculación abre su periodo inicial al crearse, con la misma
  modalidad y las mismas fechas. Registrado en `AppServiceProvider::boot()`.
- **`Vinculacion::periodos()`, `historial()` y `registrarPeriodo()`**, que asigna el `orden` solo. Es
  por donde pasarán «Renovar» y «Cambiar a término indefinido» en F1-09.
- **Constante legal nueva**: `reglas_laborales.vigencia_reforma_termino_fijo` = `2025-06-25`, con su
  norma y su fecha de verificación. Es una fecha y no un número de días: se compara, no se suma.
- **`PeriodosContractualesTest`** con 22 casos, **el caso de aislamiento de la tabla** en
  `AislamientoTest` y dos ajustes en `ReglasLaboralesTest` por la constante nueva.
- `composer verificar` en verde: 198 pruebas de Pest, PHPStan sin errores, build y 70 de Vitest.

## Decisiones tomadas

- **El periodo inicial lo abre un observador, no el controlador.** Son varios los caminos que crean
  vinculaciones —formulario, carga inicial, pruebas— y cualquiera podría olvidarlo. Un contrato sin
  tramo inicial deja el conteo de prórrogas y el tope de cuatro años sin punto de partida, y eso no
  se nota hasta que la alerta llega tarde. Los tramos siguientes sí los registra quien decide.
- **El cambio a indefinido no cuenta como prórroga.** No extiende un plazo, cambia la modalidad.
  Sumarlo adelantaría el límite de prórrogas cortas del artículo 46.
- **Pactadas y automáticas se cuentan por separado**, con dos métodos distintos, porque el límite
  legal no es el mismo: cuatro contra tres (Concepto MinTrabajo 6157 de 2026).
- **`superaElTopeLegal()` responde, no bloquea.** Quién impide guardar es la validación de F1-09, y
  cuál de los dos casos bloquea y cuál advierte sigue siendo la decisión B.3.
- **No se construyó el conteo de prórrogas cortas.** Es F1-10 y depende de B.1 y B.3. Aquí quedó solo
  su insumo, que es lo que pedía la tarea.
- **`fechaDelTopeLegal()` es la última fecha de finalización que todavía no supera el tope**: cuatro
  años exactos desde el inicio del conteo. Terminar justo ese día no lo supera; el día siguiente sí.
- **`duracionTotalEnDias()` cuenta días corridos, con los dos extremos incluidos**, y arranca en
  `inicioDelConteoDelTope()`, no en la firma. Está anotado en el propio método que cuando F2-02
  construya `diasCorridos()` este método lo llame en lugar de restar fechas por su cuenta.
- **El `orden` no lo digita nadie**: lo asignan `registrarPeriodo()` y la fábrica, ambos a partir del
  máximo que ya tiene la vinculación.
- **La fábrica de periodos crea una prórroga pactada por defecto, no el tramo inicial.** El inicial ya
  lo abre la vinculación; una fábrica que lo repitiera chocaría contra el índice único.

## Preguntas para Angela

- **La columna «Cesantías» del archivo de listas no tiene dónde ir.** Trae Porvenir, Protección,
  Colfondos, Skandia, el Fondo Nacional del Ahorro y «Otro», pero **el fondo de cesantías no aparece
  en la sección 5.1 de la especificación funcional**, así que no hay columna en `vinculaciones` ni
  tabla que lo reciba. La pregunta concreta: **¿se agrega «fondo de cesantías» como campo obligatorio
  del formulario de empleado?** Si la respuesta es sí, es una tarea nueva —tabla, columna, seeder y
  formulario— y hay que decidir si entra antes de F1-04, que es cuando se construye ese formulario.
- Menor, del mismo archivo: nivel educativo quedó con **«Técnico / Tecnólogo» unidos** y
  **«Posgraduado(a)» sin distinguir especialización, maestría y doctorado**. La propia hoja de notas
  dice que es fácil de separar después. Se construye así en F1-06 salvo aviso.
- **D.1 sigue abierta: ¿se registra el tipo de prórroga?** No bloqueó nada —el enumerado ya distingue
  pactada de automática y, si se decide no distinguirlas, el campo queda con un solo valor—, pero la
  respuesta cambia el formulario de «Renovar» de F1-09: o pregunta cuál de las dos es, o no pregunta.

## Decisiones para David

- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Lo advierte la propia hoja «Notas y fuentes» del archivo: hay nueve EPS intervenidas por la
  Supersalud —siguen operando, por eso se cargaron— y el sector está en movimiento. Las fuentes son
  Supersalud, Fasecolda, la Superintendencia del Subsidio Familiar y la Superintendencia Financiera.
  **Es una lista global: si está mal, está mal para todos los clientes a la vez.**
- **Verificar el calendario de festivos antes de vender.** Las tres anualidades se revisaron una a una
  y hay pruebas del traslado al lunes, pero la instrucción del documento es verificación humana contra
  el calendario oficial. Cincuenta y tres fechas, quince minutos, mismo riesgo global que las EPS.
- **Contrastar los 1.101 municipios contra el archivo del DANE.** Un municipio que falte deja a un
  empleado sin poder registrar dónde nació. Ese mismo cotejo trae los códigos DANE que hoy están nulos.
- **B.3, ¿bloquear o advertir?**, deja de ser teórica en F1-09: `superaElTopeLegal()` ya existe y solo
  falta decidir qué hace la pantalla con su respuesta. La sugerencia del documento 6 es bloquear el
  tope de cuatro años y advertir con fuerza en la prórroga corta.
- **Vigencia del enlace de invitación: siete días.** Abierta desde F0-07, en
  `Invitaciones::DIAS_DE_VIGENCIA`, en un solo sitio. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`. Ya son cuatro las tablas de negocio que
  dependen de ello.
- Abierta de trece sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide 8.3+**.
  `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-03 · Listado de empleados**, y es la primera pantalla de datos reales del
  sistema: usa el skill `crear-modulo`, abre `prototipos/sistema.html` antes de escribir nada y sigue
  la sección 5.3 del documento 3. Filtros de estado, tipo de contrato, cargo, rango de ingreso y
  búsqueda, **todos resueltos en el servidor**, y exportación que trae exactamente lo filtrado.
- **El listado lee de `vinculaciones` con su persona, no de `personas`.** Un empleado del listado es
  una vinculación: dos vinculaciones de la misma persona son dos filas, que es lo que hace que la
  rotación cuente bien.
- **Para la columna de vencimiento y el filtro por contrato, `vinculaciones.tipo_contrato` y
  `fecha_fin` ya traen lo vigente**; no hace falta tocar `periodos_contractuales` desde el listado. Si
  alguna pantalla necesita el historial, se carga con `->with('periodos')` y se arma con
  `HistorialDeContrato::con()`, que no vuelve a consultar.
- **Toda vinculación creada desde ahora abre su periodo inicial sola.** Si una prueba cuenta filas de
  `periodos_contractuales`, ese tramo está ahí; no es un dato de sobra.
- El registro de empleado nunca crea una persona a ciegas: primero `Persona::conDocumento()`. El
  formulario de ubicación ya está resuelto con `SelectorUbicacion` más `Ubicaciones::paraFormulario()`,
  y toda pantalla nueva se envuelve en `Armazon`.
- **No queda ninguna tarea bloqueada en el backlog.**
