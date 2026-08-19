# Estado del proyecto

**Última sesión:** 2026-08-18 · Tarea F0-11
**Verificación:** verde
**Siguiente tarea:** F1-02 · Periodos contractuales

## Qué se hizo

- **Se cerró F0-11, que era la única tarea bloqueada del backlog.** Llegó
  `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx` con las once listas desplegables del
  formulario de empleado, que es la respuesta completa a la decisión D.5.
- **`database/data/entidades.php`**: las cinco listas de entidades copiadas literalmente de la hoja
  «Listas» del archivo —**18 EPS, 7 fondos de pensión, 9 ARL, 34 cajas de compensación y 25
  bancos**—, con el comentario de dónde salió cada una y qué advierte la hoja «Notas y fuentes».
  `EntidadesSeeder` ya estaba escrito esperándolo: no hubo que tocar ni la migración ni el modelo,
  que era exactamente lo que se predijo al construirlas.
- **Cinco casos nuevos en `CatalogosTest`** que reemplazan al que afirmaba que las listas estaban
  vacías: las cuentas exactas de cada lista, una entidad reconocible de cada una —para que cargar una
  lista en la tabla equivocada se note—, la opción «Otra», que desactivar una EPS la saca del
  formulario sin borrarla, y que correr el seeder dos veces deja **los mismos identificadores**, no
  solo el mismo número de filas.
- **`docs/06-decisiones-pendientes.md`: D.5 marcada RESUELTA**, con las once listas y en qué tarea se
  aplica cada una de las seis que todavía no se usan.
- `composer verificar` en verde: 170 pruebas de Pest, PHPStan sin errores, build y 70 de Vitest.

## Decisiones tomadas

- **Los nombres se copiaron literalmente del archivo, sin corregirlos ni normalizarlos.** Son los que
  el cliente reconoce y los que va a leer en las exportaciones. Eso incluye rarezas que se dejaron a
  propósito: «Pensionado» dentro de los fondos de pensión —que no es un fondo sino el estado de quien
  se vincula ya pensionado—, y Nequi, Daviplata y RappiPay dentro de los bancos, que son cuenta de
  destino del sueldo y no bancos.
- **`codigo` quedó nulo en las cinco listas.** El archivo trae nombres, no los códigos de la
  Supersalud ni de la Superfinanciera. La columna sigue ahí para cuando se carguen; inventar un
  código para no dejarla vacía es peor que dejarla vacía.
- **Las cajas de compensación se ofrecen todas, sin filtrar por departamento.** Era una de las
  preguntas abiertas y el archivo entregó una lista plana de 34. Filtrar por departamento habría sido
  una regla inventada, y además dejaría fuera al empleado que vive en un departamento y trabaja en
  otro.
- **El seeder actualiza por nombre y nunca borra.** Una EPS que desaparezca del archivo se queda en la
  tabla: hay empleados apuntando a ella. Retirarla de la oferta es desactivarla, y eso es una acción
  del panel del proveedor (F1B-04), no del seeder.
- **La prueba de orden alfabético se cambió por una de comportamiento.** Comparar el orden de
  PostgreSQL contra el de PHP se rompe con «Régimen» y con «S.O.S.», donde las dos intercalaciones no
  coinciden: la prueba habría fallado por la configuración regional de la base y no por un error real.
- **Solo se cargaron las cinco listas que pedía F0-11.** Las otras seis del archivo —tipo de riesgo,
  género, nivel educativo, estado civil, medio de transporte y tiempo de desplazamiento— quedaron
  anotadas en D.5 y se aplican en F1-04 y F1-06, que son sus tareas. Ya no bloquean nada.

## Preguntas para Angela

- **La columna «Cesantías» del archivo no tiene dónde ir.** Trae Porvenir, Protección, Colfondos,
  Skandia, el Fondo Nacional del Ahorro y «Otro», pero **el fondo de cesantías no aparece en la
  sección 5.1 de la especificación funcional**, así que no hay columna en `vinculaciones` ni tabla que
  lo reciba. La pregunta concreta: **¿se agrega «fondo de cesantías» como campo obligatorio del
  formulario de empleado?** Si la respuesta es sí, es una tarea nueva —tabla, columna, seeder y
  formulario— y hay que decidir si entra antes de F1-04, que es cuando se construye ese formulario.
  No se agregó por cuenta propia: sería inventar un campo del producto.
- Menor, del mismo archivo: nivel educativo quedó con **«Técnico / Tecnólogo» unidos** y **«Posgraduado(a)»
  sin distinguir especialización, maestría y doctorado**. La propia hoja de notas dice que es fácil de
  separar después. Se construye así en F1-06 salvo aviso.

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
- **Vigencia del enlace de invitación: siete días.** Abierta desde F0-07, en
  `Invitaciones::DIAS_DE_VIGENCIA`, en un solo sitio. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`. Hay tres tablas de negocio dependiendo
  de ello.
- Abierta de doce sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide 8.3+**.
  `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-02 · Periodos contractuales**. `periodos_contractuales` cuelga de
  `vinculaciones` con `orden`, `evento`, `tipo_contrato` y las dos fechas; el periodo inicial es la
  fila con `orden = 1`. Es tabla de negocio: copia `database/stubs/migracion-tabla-de-negocio.stub` y
  **suma su caso a `AislamientoTest`**, que ya tiene tres ejemplos de cómo se escribe.
- **El `evento` incluye `prorroga_pactada` y `prorroga_automatica`**, que es la decisión D.1 pendiente.
  El modelo las contempla desde ya; si Angela decide no distinguirlas, el campo queda con un solo
  valor y no hay nada que rehacer. **No adelantes el conteo de prórrogas cortas**: eso es F1-10 y
  depende además de B.1 y B.3.
- **`vinculaciones.tipo_contrato` y `fecha_fin` guardan lo vigente hoy**, que es lo que se ve en los
  listados. El tramo a tramo es de la tabla nueva. Cuando F1-09 construya «Renovar», tiene que mover
  las dos cosas a la vez.
- **Ya no queda ninguna tarea bloqueada en el backlog.** F1-04 y F1-06 tienen todo lo que necesitaban
  de D.5 salvo la pregunta de cesantías, que solo afecta a un campo y no impide construir el resto.
- **Las cinco listas ya tienen filas, así que en F1-04 la validación del servidor puede exigirlas de
  verdad**: EPS, fondo, ARL, caja y banco pasan a ser obligatorias en el formulario, aunque las
  columnas sigan admitiendo nulos por las vinculaciones que traiga la carga inicial.
- El registro de empleado nunca crea una persona a ciegas: primero `Persona::conDocumento()`. El
  formulario de ubicación ya está resuelto con `SelectorUbicacion` más `Ubicaciones::paraFormulario()`,
  y toda pantalla nueva se envuelve en `Armazon`.
