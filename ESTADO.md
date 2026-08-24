# Estado del proyecto

**Última sesión:** 2026-08-24 · Tarea F6-01 (estados vacíos)
**Verificación:** verde — Pint, PHPStan sin errores, **1.135** pruebas de Pest, build y **343** de Vitest.
**Siguiente tarea:** F6-02 · Textos del sistema.

## Qué se hizo

- **F6-01, estados vacíos** (documento 3, 6.3). Al revisar los siete listados —Empleados, Contratos,
  Incapacidades, Vacaciones, Licencias, Movimientos, Terminaciones— resultó que ya tenían título,
  descripción y botón de primera acción desde que se construyeron en fases anteriores: no era un
  módulo por hacer, era encontrar los dos huecos que quedaban.
- **A Contratos le faltaba el botón del caso realmente vacío.** El `accion-vacio` solo ofrecía «Quitar
  filtros»; cuando de verdad no hay ningún contrato en la empresa —se ve poniendo el filtro de
  vencimiento en «Todos los contratos»—, no había ninguna acción. Como un contrato nace de registrar un
  empleado (F1-04), no de una pantalla propia, el botón nuevo es «Registrar empleado» hacia
  `/empleados/nuevo`, igual que el de la pantalla de empleados.
- **Los otros cinco tenían el título y la descripción cambiados de lugar.** El título se quedaba fijo
  en «Aún no has registrado…» aunque el vacío fuera de un filtro sin resultados —lo cual miente si la
  empresa sí tiene registros y el filtro los excluyó todos—, y «No encontramos resultados con estos
  filtros.» vivía en la descripción, debajo del título equivocado. Quedaron como Empleados y Contratos,
  que ya alternaban bien: el título dice qué pasó de verdad y la descripción con el botón de primera
  acción solo aparece cuando el vacío es real, no filtrado.
- Prueba nueva en `Contratos/Index.test.js` para el botón del caso sin filtros y sin contratos. Los
  otros cinco no tenían prueba de ese texto exacto y no se les agregó una: cambiar el orden de dos
  valores que ya existían no es una conducta nueva que necesite su propio caso.

## Decisiones tomadas

- **El botón de Contratos manda a registrar un empleado, no a "nuevo contrato".** No existe esa
  pantalla: todo contrato nace de F1-04, y el botón de Empleados ya resuelve exactamente ese vacío.
- **No se tocó el texto que ya tenían los siete listados**, solo el orden de título/descripción en los
  cinco que lo tenían invertido y el botón que le faltaba a Contratos. El documento 3, 6.3 solo da el
  texto literal de Empleados e Incapacidades; los demás siguen el mismo tono que ya traían desde su
  propia tarea (F1-08, F2-05, F2-07, F2-08, F2-09), que no era parte de lo que había que arreglar hoy.

## Preguntas para Angela

- Las tres de F5-01 siguen abiertas: «Sura» contra «Sura EPS» y «ARL Sura» en la fila de ejemplo de la
  plantilla 1; el código de diagnóstico que la plantilla 3 marca opcional y el sistema exige; y el
  rango «Menos de 30 min» de la plantilla 2, que el sistema parte en dos.
- Heredadas, las cinco de sesiones anteriores: los tres nombres del filtro «Estado del contrato», el
  texto del correo de prueba de entregabilidad, los textos del correo de obra o labor, el texto del
  contrato que vence el día mismo del tope y «acumula 3 prórrogas inferiores a un año».
- D.12 sigue formalmente abierta: no hay un número sugerido de referencia para la vigencia de un
  acceso de prueba; hoy el formulario no ofrece ninguno y quien lo genera decide caso por caso.

## Decisiones para David

- **La tercera condición del 4.4 (F5-07) sigue siendo tuya y es de papel**: «el contrato y el acuerdo
  de tratamiento de datos deben mencionarlo» el acceso al entorno del cliente. El software ya cumple
  las dos suyas; no debería usarse con un cliente real hasta que ese párrafo exista (A.2 y A.6).
- **El mínimo del motivo (diez caracteres) de una visita al entorno sigue siendo propuesta**, en
  `EntrarAlEntornoDelCliente`.
- **El umbral de «clientes en riesgo» de F5-06 sigue siendo una propuesta**, en `config/panel.php`,
  clave `riesgo_de_cancelacion`.
- **La escalera de mora sigue siendo tuya**, en `config/panel.php`, clave `cobro.escalera_de_mora`.
- **La tabla de precios (A.1) sigue sin decidir** y el catálogo de planes nace vacío.
- **Un contrato a término fijo cargado con prórrogas anteriores arranca contando cero prórrogas.**
- **La plantilla 1 sigue sin país de expedición del documento.**
- **`REV-06` sigue tuya: acotar la regla de «la fecha entre paréntesis».**
- **D.4 sigue abierta y sigue bloqueando dos indicadores de Composición** —rangos de edad y de
  antigüedad—. Es el único pendiente marcado **BLOQUEANTE** en el documento 6.
- **F3-06b sigue bloqueada y no la toma ninguna sesión.** Proveedor transaccional, `.env`, DNS
  (SPF/DKIM/DMARC) y correr `alertas:prueba`. Hasta que eso se haga, ningún correo sale de la máquina
  y la salud del sistema sigue sin poder decir si el correo llega.
- **A.5, infraestructura y copias de seguridad, sigue abierta** y sigue bloqueando la tarjeta «Copias
  de seguridad» de la salud del sistema, y ahora también F6-07 (Operación), que la pide de nuevo.
- **Todas las demás heredadas siguen abiertas**: la regla de los meses comerciales de treinta días, la
  prórroga mínima (B.3), el fondo de cesantías, la prueba intermitente de `CatalogosTest`, el texto del
  empleado inactivo, PHP 8.2 contra el 8.3 que pide `CLAUDE.md` y la frase del prototipo sobre el
  saldo negativo.

## Notas para la próxima sesión

- **La siguiente tarea es F6-02, textos del sistema.** Revisar todos los mensajes de error y
  confirmación contra los textos del documento 3, 6.3 —la misma fuente que usó F6-01—; probablemente
  toque, además de los ya cubiertos, los mensajes de sesión expirada, sin permiso y error inesperado,
  que hoy nadie ha verificado uno por uno contra la tabla del documento.
- **`REV-04` sigue pendiente**: el módulo `Auditoria` del entorno del cliente sigue siendo el último
  `SeccionPendiente`. El acceso del proveedor deja constancia en la auditoría del cliente desde F5-07,
  pero el cliente todavía no la puede mirar en ninguna pantalla.
- **`stash@{0}` sigue en el repositorio y ya no sirve para nada.** No hay que aplicarlo.
- **`LEEME PRIMERO.md` y `scripts/vibe.ps1` siguen modificados y sin commitear**: son cambios del bucle
  de desarrollo, ajenos a esta tarea y a las anteriores.
