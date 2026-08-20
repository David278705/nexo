# Estado del proyecto

**Última sesión:** 2026-08-19 · Tarea F1-06
**Verificación:** verde
**Siguiente tarea:** F1-07 · Ficha del empleado

## Qué se hizo

- **`App\Contratacion\BloqueSociodemografico`**, la sección 5.2 del documento 1 entera en un solo
  sitio: si la empresa lo tiene habilitado, las seis listas para el formulario, las reglas de
  validación y la escritura. El controlador, el `FormRequest` y `RegistroDeEmpleado` le preguntan a
  él; ninguno de los tres decide nada del bloque.
- **Los cuatro enumerados que faltaban**: `EstadoCivil`, `NivelEducativo`, `MedioTransporte` y
  `TiempoDesplazamiento`, con las opciones **literales del archivo de listas de Angela**
  (`plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`), que es lo que cerró la decisión D.5.
  No se inventó ninguna opción. `TipoVivienda` y el estrato ya existían desde F1-01.
- **El trait `OpcionesDeLista`** con `paraElegir()` y `valores()`. Iban seis enumerados repitiendo
  los mismos ocho renglones; los cuatro nuevos ya no los repiten.
- **El quinto paso del asistente**, intercalado antes del resumen y solo cuando el parámetro está en
  sí. Su lista de campos obligatorios está vacía a propósito. En el resumen se enseña únicamente lo
  que se llenó, y si no se llenó nada se dice con palabras en vez de dejar ocho renglones vacíos.
- **`PerfilSociodemograficoTest`** con 14 casos: el parámetro encendido y apagado, las ocho
  respuestas guardadas, el bloque en blanco, encenderlo con la planta ya cargada, el reingreso que
  actualiza el perfil existente y las listas que rechazan lo que no está en ellas. Siete casos
  nuevos de Vitest en el asistente.
- `composer verificar` en verde a la primera: 284 pruebas de Pest, PHPStan sin errores, build y 115
  de Vitest.

## Decisiones tomadas

- **Las opciones salen del archivo de listas, no de la plantilla de carga.** Las dos fuentes existen
  y **no coinciden**: la plantilla `2 - Perfil sociodemografico (opcional).xlsx` dice «Primaria ·
  Bachillerato · Técnico · Tecnólogo · Profesional · Posgrado» y rangos de desplazamiento distintos.
  Se siguió el archivo de listas porque es posterior, porque es el que resolvió D.5 para las cinco
  listas de entidades y porque la sesión pasada ya anunció que F1-06 se construiría así. **Queda una
  pregunta abierta abajo: la plantilla de carga hay que alinearla antes de F5-01.**
- **Un perfil vacío no se guarda.** Si el bloque está encendido y no se llena nada, no se crea la
  fila. Una fila de ocho nulos y la ausencia de fila significan lo mismo, y solo la primera obliga a
  explicar después por qué está ahí. Es además lo que hace que encender el parámetro con la planta
  ya cargada no marque nada como error: no hay nada que reclamar.
- **El bloque se valida siempre, encendido o apagado, pero solo se guarda encendido.** Rechazar el
  envío con un `prohibited` castigaría a quien tenía el formulario abierto cuando cambiaron el
  parámetro. Lo que llega con el bloque apagado se descarta en silencio.
- **El perfil cuelga de la persona, no de la vinculación**, y por eso un reingreso **actualiza** el
  perfil que ya existía en vez de crear un segundo. El estrato no cambia porque a alguien lo
  recontraten, pero sí pudo cambiar mientras estuvo fuera.
- **Los numéricos van con tope**: hijos y personas a cargo hasta 30, estrato del 1 al 6. El estrato
  es el rango del documento; el 30 es una barrera contra el dedo torpe, no una regla legal.

## Preguntas para Angela

- **La plantilla de carga y el formulario ya no dicen lo mismo.** `plantillas/2 - Perfil
  sociodemografico (opcional).xlsx` ofrece «Primaria · Bachillerato · Técnico · Tecnólogo ·
  Profesional · Posgrado» y los rangos «Menos de 30 min · De 30 min a 1 hora · De 1 a 2 horas · Más
  de 2 horas»; el formulario quedó con las del archivo de listas, que son otras. **¿Se corrige la
  plantilla para que coincida con el formulario?** Es lo que se recomienda: dos vocabularios para el
  mismo campo obligarían a traducir en la carga inicial. Bloquea F5-01, no antes.
- **La columna «Cesantías» del archivo de listas sigue sin dónde ir.** Trae Porvenir, Protección,
  Colfondos, Skandia, el Fondo Nacional del Ahorro y «Otro», pero **el fondo de cesantías no aparece
  en la sección 5.1 de la especificación funcional**. La pregunta concreta: **¿se agrega «fondo de
  cesantías» como campo obligatorio del formulario de empleado?** Si la respuesta es sí, es una tarea
  nueva —tabla, columna, seeder y un campo más en el paso 2 del asistente, que ya está construido.
- Menor: nivel educativo quedó con **«Técnico / Tecnólogo» unidos** y **«Posgraduado(a)» sin
  distinguir especialización, maestría y doctorado**, tal como los pediste. Separarlos después es
  agregar casos al enumerado y nada más: lo guardado es texto legible, no un número de orden.
- **D.1 sigue abierta: ¿se registra el tipo de prórroga?** No ha bloqueado nada todavía, pero la
  respuesta cambia el formulario de «Renovar» de F1-09: o pregunta cuál de las dos es, o no pregunta.
- **D.2 y D.3 se vuelven bloqueantes en la fase 3**: el umbral del contrato de obra o labor
  prolongado y la anticipación de la alerta de cercanía al tope de cuatro años.

## Decisiones para David

- **La fecha de salida no existe todavía, y el énfasis de los 30 días de la advertencia de reingreso
  depende de ella.** Hoy `Vinculacion::fechaDeSalida()` devuelve `fecha_fin` de una vinculación
  inactiva. La advertencia se muestra siempre igual; lo único que se pierde es el énfasis. **Está
  anotado en F2-09**, que es donde se corrige con un método de tres líneas.
- **Contrastar las cuatro listas de seguridad social contra las fuentes oficiales antes de vender.**
  Lo advierte la propia hoja «Notas y fuentes» del archivo: hay nueve EPS intervenidas por la
  Supersalud —siguen operando, por eso se cargaron— y el sector está en movimiento. Las fuentes son
  Supersalud, Fasecolda, la Superintendencia del Subsidio Familiar y la Superintendencia Financiera.
  **Es una lista global: si está mal, está mal para todos los clientes a la vez.**
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
- Abierta de diecisiete sesiones atrás: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`.
- **`.claude/rules/10-multiempresa.md` no se pudo editar** (el entorno lo trata como archivo
  sensible). Faltan tres líneas en «Capa 2»: que la migración llama a `PoliticaRls::activarEn()`
  copiando `database/stubs/migracion-tabla-de-negocio.stub`, que nunca se llama a `fijarEmpresa()` a
  mano, y que el rol de conexión no puede ser superusuario.
- **El menú dice «Movimientos» donde el prototipo dice «Aumentos y promociones».** Se siguió la tabla
  4.2 del documento 3. Si prefieres el texto largo, se cambia en `Modulo::etiqueta()`.

## Notas para la próxima sesión

- La siguiente es **F1-07 · Ficha del empleado**: encabezado con el saldo de vacaciones siempre
  visible y cuatro pestañas —datos, contrato, novedades y movimientos—.
- **El saldo de vacaciones no existe hasta F2-02.** La ficha tendrá que enseñar el encabezado con lo
  que haya, sin inventar un cálculo ni guardar una columna: la regla 2 del proyecto lo prohíbe.
  Decidir en esa tarea cómo se ve el hueco.
- **La ruta y el controlador de la ficha ya existen** desde F1-03: `EmpleadoController::ficha()`
  resuelve la vinculación de verdad y hoy pinta `SeccionPendiente`. Es cambiar el `Inertia::render`.
- **El bloque sociodemográfico se enseña en la pestaña de datos solo si el parámetro está en sí**, y
  `PerfilSociodemografico::estaVacio()` existe desde F1-01 justo para eso: enseñarlo como pendiente
  en vez de como una lista de campos vacíos. `BloqueSociodemografico::habilitado()` y `::opciones()`
  son lo que hace falta; la edición del perfil desde la ficha no es de esta tarea.
- **Los parámetros de empresa siguen sin pantalla**: es F1-12. Hasta entonces el único modo de
  encender el perfil sociodemográfico es tocando `tenants` a mano.
- **`OpcionesDeLista` está solo en los cuatro enumerados nuevos.** `Genero`, `TipoDocumento`,
  `TipoContrato`, `TipoRiesgo`, `TipoVivienda` y `EstadoVinculacion` siguen con los métodos
  escritos a mano. Unificarlos es un rato y no rompe nada, pero no era de esta tarea.
- **No queda ninguna tarea bloqueada en el backlog.**
