# Estado del proyecto

**Última sesión:** 2026-08-19 · Tarea F1-04
**Verificación:** verde
**Siguiente tarea:** F1-05 · Advertencia de reingreso

## Qué se hizo

- **`resources/js/Pages/Empleados/Nuevo.vue`**, el asistente de cuatro pasos de las secciones 5.1 a
  5.3 del documento 1: identificación, residencia y seguridad social, información contractual y
  resumen. Los veintiséis campos de la especificación, ninguno de más y ninguno de menos.
- **`PasosDelAsistente.vue`**, componente compartido nuevo: pinta el avance, deja retroceder a un
  paso ya recorrido y no deja saltar hacia adelante. Vive en `Components/` porque el panel del
  proveedor construye otro asistente igual en F1B-02.
- **`App\Http\Requests\RegistrarEmpleado`**, toda la validación del formulario en el servidor,
  incluida la regla 5.1.1 —la ciudad tiene que ser del departamento elegido— y la fecha de
  finalización, que exige el término fijo y prohíbe el resto.
- **`App\Contratacion\RegistroDeEmpleado`**, la escritura, en una transacción: busca la persona por
  su documento antes de crearla y abre la vinculación. **En todo el archivo no aparece la palabra
  «activo»**, y esa ausencia es la prueba de que el estado no lo digita nadie.
- **`Genero` y `TipoRiesgo`**, los dos enumerados que la decisión D.5 dejó resueltos, y
  **`App\Catalogos\Entidades`**, el único sitio donde se arman las cinco listas de entidades para un
  formulario, como `Ubicaciones` lo es para la geografía.
- **`EmpleadoController::nuevo()` y `::guardar()`**, con la ruta `POST /empleados`. El controlador no
  decide nada: traduce props y elige a dónde va el usuario después.
- **`Empleados/Index` muestra la confirmación** de lo que se acaba de guardar, leída de la sesión.
- **`RegistroDeEmpleadoTest`** con 28 casos, más siete de Vitest para el asistente y cuatro para
  `PasosDelAsistente`.
- `composer verificar` en verde a la primera: 253 pruebas de Pest, PHPStan sin errores, build y 97 de
  Vitest.

## Decisiones tomadas

- **El asistente no guarda nada hasta el último paso.** Por eso se puede retroceder sin coste y por
  eso no hay borradores a medias en la base de datos. El paso 4 es un resumen de repaso, no un
  formulario más.
- **Lo que valida Vue no es lo que valida el servidor.** El asistente solo impide avanzar con un
  obligatorio vacío, que es más amable que descubrirlo al final; las reglas de verdad son las de
  `RegistrarEmpleado`, y cuando una falla el asistente vuelve solo al paso donde está el error.
- **Volver a registrar un documento que ya existe actualiza los datos personales de esa persona.**
  Una persona tiene una sola versión de su nombre y su dirección; la vinculación nueva es la que se
  crea aparte. Sin esto, el reingreso de F1-05 dejaría la dirección de hace cinco años.
- **El número de documento se valida como texto, no como número.** El pasaporte trae letras y un
  `numeric` convertiría «000123» en 123, que es otro documento. Hay prueba de ello.
- **Las listas viajan enteras con la pantalla**, no paso a paso. Son unos cientos de filas y traerlas
  de una vez es lo que hace instantáneo el encadenamiento de departamento y ciudad.
- **La fecha de finalización desaparece cuando el contrato no es a término fijo**, no se
  deshabilita: un valor escrito antes de cambiar de modalidad se quedaría dentro.
- **El fondo de cesantías no se construyó.** No está en la sección 5.1 de la especificación y no se
  inventa un campo obligatorio. Sigue siendo pregunta para Angela, abajo.
- **Las pruebas no corren `CatalogosSeeder`**: crean las filas mínimas de catálogo que el formulario
  exige. Traer los 1.101 municipios en cada caso costaría más que todo lo que se prueba.

## Preguntas para Angela

- **La columna «Cesantías» del archivo de listas sigue sin dónde ir.** Trae Porvenir, Protección,
  Colfondos, Skandia, el Fondo Nacional del Ahorro y «Otro», pero **el fondo de cesantías no aparece
  en la sección 5.1 de la especificación funcional**. La pregunta concreta: **¿se agrega «fondo de
  cesantías» como campo obligatorio del formulario de empleado?** Si la respuesta es sí, es una tarea
  nueva —tabla, columna, seeder y un campo más en el paso 2 del asistente, que ya está construido.
- Menor, del mismo archivo: nivel educativo quedó con **«Técnico / Tecnólogo» unidos** y
  **«Posgraduado(a)» sin distinguir especialización, maestría y doctorado**. La propia hoja de notas
  dice que es fácil de separar después. Se construye así en F1-06 salvo aviso.
- **D.1 sigue abierta: ¿se registra el tipo de prórroga?** No ha bloqueado nada todavía, pero la
  respuesta cambia el formulario de «Renovar» de F1-09: o pregunta cuál de las dos es, o no pregunta.
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años.

## Decisiones para David

- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Lo advierte la propia hoja «Notas y fuentes» del archivo: hay nueve EPS intervenidas por la
  Supersalud —siguen operando, por eso se cargaron— y el sector está en movimiento. Las fuentes son
  Supersalud, Fasecolda, la Superintendencia del Subsidio Familiar y la Superintendencia Financiera.
  **Es una lista global: si está mal, está mal para todos los clientes a la vez.** Desde hoy además
  se eligen en el formulario de registro, así que el error entraría en los datos de los clientes.
- **Verificar el calendario de festivos antes de vender.** Cincuenta y tres fechas, quince minutos,
  mismo riesgo global que las EPS.
- **Contrastar los 1.101 municipios contra el archivo del DANE.** Ese mismo cotejo trae los códigos
  DANE que hoy están nulos.
- **B.3, ¿bloquear o advertir?**, deja de ser teórica en F1-09. La sugerencia del documento 6 es
  bloquear el tope de cuatro años y advertir con fuerza en la prórroga corta.
- **Vigencia del enlace de invitación: siete días.** Abierta desde F0-07, en
  `Invitaciones::DIAS_DE_VIGENCIA`, en un solo sitio. **Confírmalo o cámbialo.**
- **El rol de conexión de PostgreSQL.** Abierta desde F0-04: `.env` se conecta como `postgres`,
  superusuario con `BYPASSRLS`, así que **en local la capa 2 no se aplica nunca**. En producción hace
  falta un rol corriente con `SELECT, INSERT, UPDATE, DELETE`. Ya son cuatro las tablas de negocio que
  dependen de ello.
- Abierta de quince sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide 8.3+**.
  `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-05 · Advertencia de reingreso**: al digitar en el paso 1 un documento que ya
  tuvo vinculación en esa empresa, el aviso con el **texto literal de la sección 5.6 del documento
  1**, con énfasis especial si el reingreso ocurre dentro de los 30 días siguientes a la salida.
- **El sitio donde entra es el paso 1 de `Empleados/Nuevo.vue`**, bajo el campo de número de
  documento, y su comprobación tiene que hacerse en el servidor: `Persona::conDocumento()` ya existe
  y `RegistroDeEmpleado` ya reutiliza a la persona encontrada. Lo que falta es preguntarle al
  servidor al salir del campo. **Nada de `axios` ni `fetch`**: la vía de este proyecto es
  `router.reload({ only: [...] })` con el documento como parámetro, o una ruta que devuelva props.
- **La fecha de salida todavía no existe.** El módulo de terminaciones es F2-09 y por ahora una
  vinculación inactiva no guarda cuándo terminó. Para la regla de los 30 días habrá que decidir de
  dónde sale esa fecha: lo más probable es `fecha_fin` cuando la haya, y si no, marcar la tarea `[!]`
  antes que inventarlo.
- **El asistente ya sabe volver al paso del error**: `guardar()` busca en qué paso vive el primer
  campo que el servidor rechazó. Si F1-05 agrega campos, agréguelos también a la lista de ese paso.
- **F1-06 intercala el perfil sociodemográfico antes del resumen**, y solo si la empresa lo tiene
  habilitado. El asistente está escrito para eso: los pasos son un `computed`, no una lista fija.
- **La columna de saldo de vacaciones entra en el listado cuando exista F2-02**, no antes.
- **No queda ninguna tarea bloqueada en el backlog.**
