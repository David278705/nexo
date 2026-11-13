# Puntos Pendientes — Sistema de Gestión de Talento Humano

> Decisiones, validaciones y preguntas abiertas
> Documento de referencia del proyecto. Generado desde la fuente original; no editar a mano.

**DOCUMENTO DE SEGUIMIENTO**
**Puntos Pendientes**
**Decisiones, validaciones y preguntas abiertas**
*Todo lo que aún no está cerrado, en un solo lugar*
*Lo ya decidido se encuentra en el documento «Especificación Funcional»*
**Angela Lozano  ·  Experta en Gestión Humana**
**David Lozano  ·  Desarrollo**
Sistema de Gestión de Talento Humano · Versión 3.0

## Cómo usar este documento

Aquí está todo lo que todavía no se puede dar por cerrado. Se organiza en cinco bloques según qué hay que hacer con cada cosa, y ninguno de ellos bloquea el desarrollo salvo los marcados expresamente como bloqueantes.

| Bloque | Qué contiene | Cuándo se atiende |
|---|---|---|
| A. Aplazado a propósito | Precios, contratos legales, infraestructura, formalización. Se decidió no resolverlos ahora. | Cuando el producto esté cerca de venderse. |
| B. Validaciones legales | Puntos que exigen la confirmación de un asesor laboral antes de programarse. | Antes de codificar las reglas afectadas. |
| C. Consultas resueltas | Respuestas a las dudas planteadas en la última revisión, con su fundamento. | Ya resueltas. Se conservan como referencia. |
| D. Preguntas para Angela | Detalles pequeños que faltan para poder construir sin adivinar. | En la próxima sesión. |
| E. Fase dos | Ideas descartadas del MVP pero vivas. | Con los primeros clientes. |

> **Criterio que se acordó en la revisión**
>
> "Eso no obstaculiza el desarrollo." Los temas de precio, contrato legal, infraestructura y seguridad se miran cuando exista siquiera algo del producto. Construir primero, vender después.
>
> Este documento existe para que esos temas no se olviden, no para que frenen el trabajo.

## Contenido

<!-- tabla de contenido generada por el editor -->

## Tablero general

| # | Pendiente | Bloque | Responsable | ¿Bloquea? |
|---|---|---|---|---|
| 1 | Tabla de precios por tamaño de empresa y número de usuarios | A | Ambos | No |
| 2 | Contrato de prestación del servicio revisado por abogado | A | Ambos | No |
| 3 | Acuerdo de tratamiento de datos revisado por abogado | A | Ambos | No |
| 4 | ~~Decisión de infraestructura~~ **RESUELTA:** Railway, plataforma administrada | A | David | No |
| 5 | Constitución de la sociedad, DIAN y pasarela de pagos | A | Ambos | No |
| 6 | ~~Confirmar el conteo de prórrogas cortas~~ **RESUELTA:** tres, en una sola cuenta | B | Ambos | No |
| 7 | Confirmar el periodo de prueba en cambio de modalidad contractual | B | Ambos | No |
| 8 | ~~Decidir si las validaciones de prórroga bloquean o solo advierten~~ **RESUELTA:** ver B.3 | B | Ambos | No |
| 9 | ~~¿Se registra el tipo de prórroga?~~ **RESUELTA:** sí, pero no para contar | D | Angela | No |
| 10 | ~~Umbral de obra o labor prolongado~~ **RESUELTA:** sin umbral, listado bimestral | D | Angela | No |
| 11 | ~~Anticipación de la alerta de cercanía al tope~~ **RESUELTA:** tres meses antes | D | Angela | No |
| 12 | ~~Vigencia del usuario de demostración~~ **RESUELTA:** 14 días | D | David | No |
| 13 | ~~Rangos de edad y de antigüedad~~ **RESUELTA:** ver D.4 | D | David | No |
| 14 | ~~Listados maestros: EPS, fondos, ARL, cajas, bancos~~ **RESUELTA:** ver D.5 | D | Angela | No |
| 15 | ~~Confirmar el orden del dashboard~~ **RESUELTA:** el construido ya es el correcto | D | David | No |
| 16 | ~~Nombre del proceso de carga inicial~~ **RESUELTA:** «Carga inicial» | D | David | No |
| 17 | ~~Bogotá bajo Cundinamarca o bajo Bogotá D.C.~~ **RESUELTA:** el catálogo no se toca, se arregla la guía | D | David | No |
| 18 | ~~Cómo registrar las prórrogas en la carga masiva~~ **RESUELTA:** una sola plantilla con columnas | D | David | No |
| 19 | ~~Incapacidad que cae sobre vacaciones ya registradas~~ **RESUELTA:** recalcular y dejar nota | D | David | No |
| 20 | ~~Terminación de un contrato sin estado claro~~ **RESUELTA:** se bloquea hasta aclararlo | D | David | No |
| 21 | Norma que sustenta que la incapacidad interrumpe las vacaciones | D | Angela | No |
| 22 | Módulo de llamados de atención | E | Ambos | No |
| 23 | Enlace al expediente en Google Drive | E | Ambos | No |
| 24 | Módulo de reportes centralizado | E | Ambos | No |
| 25 | Asistente de inteligencia artificial | E | Ambos | No |

## A. Aplazado a propósito

Estos temas se discutieron y se decidió deliberadamente no resolverlos todavía. Ninguno impide avanzar en el producto.

### A.1 Tabla de precios

Está definido que el precio depende de dos variables: número de empleados gestionados y número de usuarios administradores. Lo que falta es la tabla concreta.
**Antes de construirla hay que calcular el costo real por cliente.** Los componentes a sumar son:

| Componente | Comportamiento |
|---|---|
| Servidor de aplicación | Costo base, poco sensible al número de clientes al inicio. |
| Base de datos | Crece con el volumen de registros. Sin almacenamiento de archivos, es modesto y predecible. |
| Correo transaccional | Se cobra por volumen. Es el costo que sí escala con el número de empleados y con el número de administradores que reciben copia de cada alerta. Es la justificación técnica de cobrar por usuario. |
| Copias de seguridad | Almacenamiento adicional según la retención definida. |
| Dominio y certificados | Costo fijo anual, menor. |
| Pasarela de pagos | Comisión por transacción. |
| Onboarding y carga inicial | Tiempo del equipo. Puede cobrarse como cargo único de implementación, que es práctica habitual en este segmento. |
| Soporte continuo | El costo más grande y el más fácil de ignorar. Conviene medirlo desde el primer cliente. |

Método sugerido: costo mensual total de infraestructura dividido entre el número de empresas proyectadas, más un estimado de horas de soporte por cliente. Ese número es el precio mínimo viable, y a partir de ahí se construyen los niveles de licencia. No al revés.

### A.2 Contrato de prestación del servicio

Se revisará con abogado cuando el producto esté listo para venderse. Los componentes que debe tener quedaron identificados:
- Naturaleza: licencia de uso no exclusiva sobre una plataforma en la nube, más prestación de servicios. No es una compraventa de software. El código y la propiedad intelectual permanecen en el proveedor.
- Propiedad de los datos: la información cargada es del cliente; el proveedor es un custodio.
- Niveles de servicio: disponibilidad mínima mensual, tiempos de respuesta por gravedad y consecuencias del incumplimiento. Ver A.3.
- Limitación de responsabilidad: tope equivalente a un múltiplo del valor pagado en los últimos doce meses, con excepciones para dolo, culpa grave y violación de confidencialidad.
- **Exclusión de asesoría legal.** El sistema entrega información, cálculos y recordatorios; no sustituye la asesoría jurídica ni traslada al proveedor la responsabilidad del empleador de cumplir la legislación laboral colombiana. Cláusula ya aprobada por Angela.
- **Responsabilidad por la información digitada manualmente.** Toda la información que el cliente ingresa a mano está bajo su responsabilidad y le corresponde a él verificar que sea correcta. Es especialmente relevante para los campos de departamento y ciudad del exterior, que el sistema no puede validar.
- Obligaciones del cliente: veracidad de la información, obtención de las autorizaciones de sus empleados, custodia de credenciales.
- Salida: exportación completa de la información, periodo de gracia, eliminación y certificado. Ver A.4.

### A.3 Qué es un SLA

> **Explicación en palabras sencillas**
>
> SLA significa acuerdo de nivel de servicio. Es la parte del contrato donde el proveedor se compromete por escrito a dos cosas medibles:
>
> · Cuánto tiempo va a estar funcionando el sistema. Se expresa en porcentaje mensual. Prometer 99% significa que el sistema puede estar caído como máximo unas siete horas al mes; prometer 99,9% baja ese máximo a unos cuarenta y tres minutos.
>
> · En cuánto tiempo se responde cuando algo falla. Se separa por gravedad: si el sistema está caído para todos, responder en dos horas; si es un error que molesta pero permite seguir trabajando, en un día hábil.
>
> Sirve para dar confianza al cliente y, sobre todo, para fijar un límite. Sin SLA, si el sistema se cae dos días, la discusión de qué era razonable es un pulso sin reglas.
>
> Consejo: ser conservadores. Es mejor prometer 99% y cumplirlo que prometer 99,9% y fallar. Y si el SLA no dice qué pasa cuando se incumple —normalmente un descuento en la factura—, no es un compromiso exigible.

### A.4 Salida del cliente y conservación de la información

Quedó decidido que al terminar el contrato con un cliente se le entrega toda su información y el proveedor no conserva nada. Esa decisión es correcta y conviene entender por qué.

| Obligación de conservar | A quién obliga |
|---|---|
| Prescripción de acciones laborales: tres años (artículo 488 del CST) | A la empresa cliente, como empleador |
| Registros del SG-SST: veinte años desde que cesa la relación (Decreto 1072 de 2015) | A la empresa cliente |
| Conservación de la historia laboral en el sector privado | A la empresa cliente |

Ninguna se traslada al proveedor. Al contrario: el principio de temporalidad de la Ley 1581 de 2012 exige no conservar datos más allá de lo necesario para la finalidad autorizada. Terminado el servicio, la finalidad se agotó.

##### Procedimiento

1. Exportación completa de la información en formato utilizable, con constancia escrita de la entrega.
2. Periodo de gracia corto y pactado —treinta días es lo habitual— por si el cliente detecta que le falta algo.
3. Eliminación definitiva en producción y en copias de seguridad.
4. Certificado de eliminación remitido al cliente. La práctica del sector es hacerlo dentro de los siete días hábiles siguientes.
5. Constancia de que la obligación de conservación queda en cabeza del cliente.
*Punto en sentido contrario: retener la información como presión por facturas impagadas es mal negocio y jurídicamente delicado. La vía correcta ante el impago es suspender el servicio, no retener los datos.*

##### Cómo quedó construido (2026-10-01)

- **Paso 1:** el botón «Exportar toda la información» de la ficha de la empresa en el panel. Entrega
  un ZIP con un CSV por tabla, el certificado de Cámara de Comercio y un `LEEME.txt`, y deja la
  fecha de la entrega en la empresa.
- **Pasos 2 y 3:** `php artisan empresa:eliminar {nit}`. Solo procede con el servicio suspendido, la
  información ya entregada y el periodo de gracia cumplido (`config/panel.php`, `salida.dias_de_gracia`,
  30 por defecto), y pide escribir el NIT para confirmar. Es la única excepción a la regla de nunca
  borrar físicamente, y así está escrito en el `CLAUDE.md`.
- **Paso 4:** el comando imprime la constancia de eliminación con los registros borrados por tabla.
- **Lo que el proveedor conserva**, decidido por David el 2026-11-03 (`PROD-15`): la empresa, sus
  suscripciones y pagos **sin plazo**, las notas internas, **la aceptación de los documentos legales**
  —con el nombre y el correo de quien aceptó—, **el registro de alertas sin datos personales** y **las
  constancias de entrega y de eliminación**, con la huella SHA-256 del archivo entregado. El porqué está
  en `App\Proveedor\Salida\TablasDeLaEmpresa`. El abogado lo valida al redactar el contrato.
- **Las copias de seguridad** no se pueden borrar de forma selectiva: los datos salen de ellas al
  cumplirse su periodo de retención. La constancia lo dice así.

### A.5 Infraestructura y seguridad — RESUELTA: Railway

**Decidido por David el 23 de agosto de 2026, siguiendo la recomendación de este mismo documento.**
El sistema corre en **Railway**, plataforma administrada. Con eso, F6-07 deja de estar bloqueada:

- **Copias de seguridad y restauración:** Railway ofrece *Point-in-Time Recovery* nativo sobre
  PostgreSQL —WAL continuo, ventana de retención de 7 días, restauración al segundo exacto—. Restaurar
  **crea un servicio nuevo al lado del que está en producción**; el original sigue sirviendo tráfico
  sin tocarse durante toda la prueba. Eso es lo que permite cumplir el «hecho cuando» de F6-07 sin
  arriesgar los datos reales: se restaura al servicio nuevo, se verifica ahí, y se descarta.
- **Monitoreo de errores:** Railway da logs y métricas de infraestructura (CPU, memoria, red), pero
  no rastrea excepciones de la aplicación con su traza. Eso hace falta aparte. Se instala
  [Sentry](https://sentry.io) —tiene paquete oficial de Laravel, plan gratuito suficiente para esta
  etapa— y se conecta el canal `log` de Laravel a él. No es una decisión de infraestructura nueva: es
  uno de los «controles mínimos» que ya pedía esta misma sección, con el proveedor concreto elegido.
- **HTTPS y cifrado:** Railway los provee por defecto en sus dominios; no hay nada que configurar.

Cuando llegue a existir producto y volumen reales, la comparación de abajo sigue siendo válida para
decidir si conviene migrar a infraestructura propia. Se conserva como referencia.

| Criterio | Plataforma administrada | Infraestructura propia |
|---|---|---|
| Costo mensual directo | Mayor por unidad de recurso | Menor por unidad de recurso |
| Tiempo de configuración | Horas | Días o semanas |
| Mantenimiento continuo | Mínimo, parches incluidos | Permanente y a cargo de ustedes |
| Conocimiento requerido | Bajo | Alto en redes, sistemas y seguridad |
| Riesgo de configuración errónea | Bajo | Alto; es la causa más frecuente de incidentes |
| Copias de seguridad | Generalmente incluidas | Hay que construirlas y probarlas |

> **Recomendación cuando llegue el momento**
>
> Empezar en plataforma administrada. Con dos personas y un producto sin validar, el recurso escaso no es el dinero de la infraestructura sino el tiempo de desarrollo.
>
> El momento de migrar llega cuando el costo mensual del proveedor administrado supere de forma clara el costo del tiempo necesario para administrar la propia.

##### Controles mínimos, independientemente de la decisión

- HTTPS en todo el sistema y cifrado de la base de datos y las copias de seguridad.
- Contraseñas con hashing moderno, nunca reversibles. Segundo factor al menos para el superadministrador.
- Acceso restringido al campo de código de diagnóstico, por ser dato sensible.
- Copias de seguridad automáticas y, sobre todo, probadas. Una copia que nunca se ha restaurado no es una copia de seguridad.
- Pruebas automatizadas que intenten acceder a datos de otra empresa y fallen si lo logran.
- Correo con dominio propio y configuración de SPF, DKIM y DMARC. Si las alertas caen en spam, el producto no cumple su promesa central.
- Plan de incidentes escrito, aunque sea de una página.

### A.6 Formalización

- Constituir la sociedad antes de la primera venta. Operar como persona natural expone el patrimonio personal ante un incidente de datos.
- Habilitación de facturación electrónica ante la DIAN.
- Verificar con un contador el tratamiento del IVA sobre servicios de software y el efecto de facturar suscripciones recurrentes.
- Pasarela de pagos con cobro recurrente, de la que depende el módulo de pago del superadministrador.
- Acuerdo entre socios: participación, aportes, propiedad del código y qué ocurre si uno se retira.
- Registro de marca una vez definido el nombre comercial.

## B. Validaciones legales pendientes

Estos puntos exigen la confirmación de un asesor laboral antes de programarse. Un error aquí no afecta a un cliente: produce comportamientos equivocados en todos a la vez.

### B.1 El conteo de prórrogas cortas — RESUELTA

**Respondida por Angela en agosto de 2026, y corrige lo que el sistema había construido.** El conteo es **uno solo, de tres prórrogas cortas**, sin separar por tipo. La sección C.1 quedó reescrita con la explicación completa y con la razón por la que la primera lectura de este documento llevó a construirlo mal.

La otra mitad de la pregunta, si esa regla debe **impedir el guardado** o solo **advertir**, también quedó resuelta: ver B.3.

### B.2 Periodo de prueba en cambio de modalidad contractual

La conclusión de la sección C.3 es que no procede pactar periodo de prueba cuando un contrato de obra o labor pasa a indefinido, ni por otrosí ni firmando un contrato nuevo. Es la lectura conservadora y la que protege al cliente, pero conviene confirmarla porque el escenario de "la nueva labor es completamente distinta" no está resuelto expresamente en la norma.

### B.3 ¿Bloquear o advertir? — RESUELTA, implementada tal como Angela la propuso

**Confirmado el 2026-08-25: el código ya hace exactamente lo que dice la sugerencia de abajo**, y no se encontró ninguna razón para apartarse de ella. `config/validaciones.php` tiene `renovacion_tope_termino_fijo` en `bloquea` y `renovacion_prorroga_minima` en `advierte`, que es la combinación que Angela planteó desde el principio. Queda el argumento original como referencia, porque explica el porqué de cada una:

| Opción | A favor | En contra |
|---|---|---|
| Bloquear el guardado | El sistema nunca registra algo ilegal. Protege al cliente de sí mismo. Es coherente con la promesa del producto. | Si el cliente ya firmó esa prórroga en el mundo real, el sistema queda desalineado de la realidad y el cliente no tiene dónde registrarla. |
| Advertir con fuerza y permitir | El sistema siempre refleja la realidad. La responsabilidad queda documentada. | El sistema termina almacenando situaciones ilegales y pierde parte de su valor preventivo. |

> **Sugerencia**
>
> Bloquear en el caso del tope de cuatro años, porque ahí la ley no deja alternativa: pasado el tope el contrato ES indefinido, no puede ser otra cosa. El sistema debe ofrecer en ese mismo momento la acción "Cambiar a término indefinido", que es la salida correcta y deja al cliente resuelto.
>
> Advertir con fuerza, pero permitir, en el caso de la prórroga corta después del límite, porque ahí sí puede haber un contrato ya firmado que haya que registrar.
>
> Esta es una decisión de producto tanto como legal, y conviene tomarla entre los dos.

### B.4 Revisión del paquete contractual

- Contrato de prestación del servicio.
- Acuerdo de tratamiento de datos personales, cuyo borrador ya está redactado en la carpeta «Documentos legales».
- Plantilla de autorización de tratamiento para que el cliente la use con sus empleados, incluida la autorización expresa para datos sensibles de salud. Está en el Anexo 3 del mismo borrador.

## C. Consultas resueltas en esta revisión

Respuestas a las dudas que quedaron planteadas, con su fundamento. Se conservan aquí como referencia; las conclusiones ya están incorporadas a la Especificación Funcional.

### C.1 La regla de la cuarta prórroga — CORREGIDA por Angela, agosto de 2026

> **Reemplazada el 2026-11-11 (`PROD-33`).** Angela pidió que el tipo de prórroga sí cuente: con 3 prórrogas, si
> alguna fue automática o el contrato vino de la carga inicial, la 4.ª debe ser de un año; si las 3 fueron
> pactadas en un contrato creado en Nexo, cabe una 4.ª pactada corta; desde la 5.ª, siempre un año. Bloquea.
> El caso mixto no lo resuelve la ley y se aplica la lectura más favorable al trabajador. **Falta que el asesor
> laboral confirme** el caso mixto, que el tope prevalece sobre la prórroga mínima de un año y que las prórrogas
> anteriores al 25 de junio de 2025 cuentan para el límite. Lo que sigue es la versión de agosto, como referencia.

**La duda:** si se firma un contrato de un mes, ¿pueden hacerse cuatro prórrogas más y es en la quinta cuando debe ser de un año?

**La respuesta, en la forma que hay que programar:** **el límite es de tres prórrogas cortas, en una sola cuenta.** La cuarta prórroga de un contrato inferior a un año ya no puede ser corta, sin importar de qué tipo sea.

> **Lo que la primera versión de este documento entendió mal**
>
> La tabla anterior enfrentaba «hasta cuatro» contra «tres» como si fueran dos cuentas separadas, y de ahí el sistema construyó **dos cupos independientes** —cuatro prórrogas cortas pactadas *más* tres automáticas—. Eso no existe. Un contrato tiene **una sola cadena de prórrogas**, numeradas 1ª, 2ª, 3ª, sin importar cómo se originó cada una.
>
> Lo único que decide el tipo de prórroga es **cuánto dura**, no cuántas caben:
>
> · **Automática:** nadie avisó, así que se renueva por el mismo término anterior. Contrato de seis meses → prórroga de seis meses.
>
> · **Pactada:** las partes acuerdan la duración, que puede ser mayor o menor. Contrato de seis meses → la prórroga puede ser de ocho, o de tres.
>
> Al sistema esa diferencia no le cambia el conteo: registra que hubo una prórroga y cuánto duró. Si duró menos de un año, gasta uno de los tres cupos.

|  | Regla |
|---|---|
| Cuántas prórrogas pueden ser inferiores a un año | **Tres en total**, sean pactadas, automáticas o mezcladas |
| A partir de cuál debe ser mínimo un año | **La cuarta**, sea del tipo que sea |
| Qué decide el tipo de prórroga | Solo la duración del tramo nuevo, nunca el conteo |
| Tope global | La suma de todo —contrato inicial más prórrogas— nunca supera cuatro años |

**Nota sobre el texto de la norma.** El artículo 46 redacta la regla dos veces, una por tipo, y la de las pactadas dice «después de la cuarta prórroga», que leída sola sugeriría cuatro cortas en vez de tres. Angela confirmó que la aplicación correcta es **tres**, que además es la lectura conservadora: el sistema avisa un tramo antes y nunca deja pasar por bueno algo ilegal. Como esta regla está configurada en `config/validaciones.php` como `advierte` y no como `bloquea`, ser conservador no le impide operar a nadie —solo pone el aviso—, así que el riesgo de la lectura estricta es nulo.

> **Lo que dice el Ministerio del Trabajo**
>
> Sobre las prórrogas pactadas: "para los casos de contratos a término fijo inferiores a un año, se pueden dar hasta cuatro (4) prórrogas, como quiera que después de la última, la renovación no podrá ser inferior a un año."
>
> Sobre las automáticas: "para los contratos a término fijo inferiores a un año aplica la prórroga automática, tres (3) veces por el mismo término, sin embargo, la cuarta prórroga será por el término de un año."
>
> Concepto 08SE2026120300000006157 del 18 de febrero de 2026.

##### Dos precisiones adicionales

- **La prórroga automática cambió de referencia.** Antes el contrato se entendía renovado siempre por el término inicial. Ahora se renueva por el término inicial **o por el de su última prórroga** . Ejemplo: contrato de seis meses, prorrogado por escrito a tres meses; si luego nadie avisa, se renueva por tres meses, no por seis.
- **La prórroga pactada debe hacerse antes de que opere la automática.** Si ya operó el silencio, una prórroga pactada posterior solo podría ser por un periodo superior al que quedó prorrogado automáticamente, por el principio de irrenunciabilidad de los derechos mínimos laborales.

### C.2 El tope de cuatro años: desde cuándo se cuenta

| Situación del contrato | Desde cuándo corren los cuatro años |
|---|---|
| Suscrito después de la entrada en vigencia de la reforma | Desde la fecha de celebración del contrato. |
| Vigente antes de la entrada en vigencia de la reforma | Desde el 25 de junio de 2025, no desde la firma original. Los contratos que se venían prorrogando indefinidamente bajo la norma anterior son situaciones consolidadas y la ley no tiene efecto retroactivo. |

##### Consecuencias para el sistema

- El sistema puede calcular esto solo, siempre que se registre la fecha de inicio real del contrato. Basta con comparar contra el 25 de junio de 2025.
- **Esto hace que la plantilla de carga inicial deba traer la fecha real de inicio de cada contrato a término fijo,** no la fecha de la última prórroga. Sin ese dato el cálculo del tope queda mal.
- Cuando el contrato muta a indefinido por alcanzar el tope, no hay que liquidar salarios ni prestaciones en ese momento: se liquidan cuando efectivamente termine la relación laboral. Es un dato que conviene incluir en el texto de la alerta, porque es la duda inmediata que le va a surgir al cliente.

### C.3 Contrato de obra o labor que pasa a indefinido

**La duda:** si un contrato de obra o labor está por acabarse, ¿se puede cambiar a indefinido, o es mejor terminarlo e iniciar uno nuevo? ¿Y tendría periodo de prueba?

##### Sobre el camino a seguir

Ambos son válidos, y en los dos casos se requiere el consentimiento del trabajador: el empleador no puede imponer un cambio de modalidad.

| Camino | Cómo funciona | Consideración |
|---|---|---|
| Otrosí al contrato existente | Se suscribe un documento que modifica la modalidad, manteniendo la continuidad del vínculo. No se liquida nada. | Es el camino más simple y el que menos se presta a discusión. La antigüedad y todos los cálculos siguen corriendo sin interrupción. |
| Liquidar y firmar contrato nuevo | Se termina el contrato de obra o labor, se liquida, y se suscribe uno nuevo a término indefinido. | Implica el costo de la liquidación y, si no hay solución de continuidad real, puede alegarse que el vínculo nunca se interrumpió. |

##### Sobre el periodo de prueba

**No procede en ninguno de los dos caminos.**
- Si se hace por otrosí, no hay contrato nuevo: es el mismo contrato continuando, y el periodo de prueba solo puede pactarse al inicio de un contrato.
- Si se firma un contrato nuevo, aplica el artículo 78 del Código Sustantivo del Trabajo: cuando entre un mismo empleador y trabajador se celebran contratos sucesivos, no es válido estipular periodo de prueba salvo en el primero.
- La lógica de fondo es la misma en ambos casos: el empleador ya tuvo la oportunidad de evaluar a esa persona, y esa evaluación no se repite.
*El único escenario que la norma no resuelve expresamente es aquel en que la nueva labor sea completamente distinta de la anterior. Está en el bloque B para confirmación con abogado. Mientras tanto, la recomendación conservadora es no pactar periodo de prueba.*

### C.4 Contratos sucesivos: cuánto tiempo debe pasar

**La respuesta corta: la ley no fija un plazo.** No existe una regla que diga "si pasaron seis meses, se puede volver a pactar periodo de prueba".
Lo que la norma protege es que el empleador ya evaluó a esa persona. El criterio que aplican los jueces atiende a la continuidad real de la relación y a si el nuevo contrato es una prolongación del anterior o una vinculación genuinamente distinta. Esa valoración es casuística y no admite una regla automática.

> **Por eso el sistema hace lo que hace**
>
> Como no hay un plazo objetivo, el sistema no puede decidir por el cliente. La conducta acordada es: generar las alertas de periodo de prueba por defecto, y mostrar la advertencia al crear el registro cuando el documento ya tuvo una vinculación previa, con énfasis especial si el reingreso ocurre dentro de los 30 días siguientes a la salida anterior.
>
> El texto exacto de la advertencia está en la sección 5.6 de la Especificación Funcional.

### C.5 El código de diagnóstico

Quedó resuelto en la revisión anterior y se confirma: el campo se mantiene. El empleador es responsable de tramitar la incapacidad ante la EPS y recibe legítimamente el certificado con el diagnóstico; el SG-SST le exige llevar registro de ausentismo y desarrollar vigilancia epidemiológica. Lo prohibido es exigir la historia clínica o la epicrisis, e indagar más allá del certificado.
Por ser dato sensible, requiere acceso restringido dentro del sistema, autorización expresa del titular —que recoge el cliente— y presentación agregada en el dashboard.

## D. Preguntas para Angela

Detalles pequeños que faltan para poder construir sin adivinar. Ninguno requiere investigación: son decisiones de criterio.

### D.1 ¿Se registra el tipo de prórroga? — RESUELTA

**Respondida por Angela.** El campo se queda en la acción "Renovar contrato" —**Prórroga pactada por escrito** o **Prórroga automática por silencio**—, pero deja de usarse para contar el límite de prórrogas cortas: ver la corrección de C.1. Sigue sirviendo para dos cosas que no cambiaron: el historial de cada contrato y la frase informativa del modal ("sería la 2ª prórroga, pactada por escrito").

### D.2 Umbral de contrato de obra o labor prolongado — RESUELTA, y el umbral desaparece

**Respondida por Angela en agosto de 2026, cambiando la forma de la alerta en vez de poner un número.** La pregunta era a partir de cuántos meses sin registrar terminación debía aparecer un contrato de obra o labor en el aviso. La respuesta: **no hay umbral.** En su lugar, **un correo cada dos meses con el listado de todos los contratos de obra o labor activos de la empresa, para visibilidad.**

Es la salida limpia a una pregunta que no tenía respuesta: no existe criterio legal para decidir cuándo una obra es «prolongada», y cualquier número habría sido inventado. Un listado periódico completo da la misma señal de gestión —«revisa si alguna de estas obras ya terminó»— sin afirmar que unas importan y otras no. La cadencia bimestral es lo que evita que un listado completo se vuelva ruido mensual.

Consecuencias, ya reflejadas en los documentos 1 (9.6 y 9.7) y 2 (5.4):

- **No se agrega ningún umbral a `config/alertas.php`.** El renglón que esperaba ahí no va.
- **Es la única alerta que no sale todos los meses**, así que el armazón `ResumenMensual` necesita una cadencia y no solo un día.
- **El nombre `ObraOLaborProlongada` quedó desalineado** con lo que la alerta hace: ya no habla de contratos prolongados sino de todos los activos.

### D.3 Anticipación de la alerta de cercanía al tope — RESUELTA

**Respondida por Angela.** Un solo aviso nuevo, **tres meses antes** de la fecha en que el vínculo completaría los cuatro años (`HistorialDeContrato::fechaDelTopeLegal()`). No son dos avisos: los "35 días" que ella mencionó al principio no son una alerta nueva, son las dos que ya existen —`PlazoDeRenovacion` y `EfectoDelSilencio`— que solo necesitan ajustar su texto cuando la renovación en curso es la última posible antes del tope. Ver la nota siguiente.

**Efecto sobre `EfectoDelSilencio` cuando la renovación es la última antes del tope.** El artículo 46 del CST reformado (Ley 2466 de 2025) ya resuelve el caso: la prórroga automática por silencio siempre es "por un término igual al inicialmente pactado... sin que pueda superarse el límite máximo de cuatro (4) años". Es decir, la renovación tácita **se trunca sola** al tiempo que falte para el tope, nunca se pasa. Tres variantes de mensaje, según cuánto falte para el tope frente al término que tocaría renovar:

1. **La renovación llega exacto (o prácticamente) al tope de 4 años** → el mensaje dice que si no se avisa, el contrato se vuelve indefinido.
2. **Falta menos tiempo para el tope que el término que normalmente se renovaría** (ejemplo de Angela: falta 1 mes, el término pactado la última vez fue de 6 meses) → el mensaje no puede decir "se renueva por el mismo término" —sería ilegal, se pasaría del tope— ni "se vuelve indefinido" —todavía no llega—. Dice que la prórroga automática queda truncada hasta la fecha del tope, y que ahí se vuelve indefinido: *"Si no se avisa hoy (fecha), el contrato queda prorrogado automáticamente solo hasta el (fecha del tope), y al llegar a esa fecha se convierte en indefinido por ley."*
3. **Falta más tiempo para el tope que el término que tocaría renovar** → es el caso normal, ya construido: el mensaje literal que ya tiene `EfectoDelSilencio`.

Fuente legal: artículo 46 CST reformado — https://leyes.co/codigo_sustantivo_del_trabajo/46.htm

### D.4 Rangos de edad y de antigüedad — RESUELTA

**Decidido por David el 2026-08-25, tomando la sugerencia inicial tal como estaba escrita.** No era una regla legal ni de negocio que pudiera quedar mal decidida: son los tramos de un gráfico, y la propuesta ya era razonable y estándar para un dashboard de RR. HH.
- Edad: menos de 25 · 25 a 34 · 35 a 44 · 45 a 54 · 55 o más.
- Antigüedad: menos de 6 meses · 6 meses a 1 año · 1 a 3 años · 3 a 5 años · más de 5 años.

Implementado en `App\Inicio\Indicadores\IndicadoresDeComposicion` —`RANGOS_DE_EDAD` y `RANGOS_DE_ANTIGUEDAD`—, con prueba de los tramos límite (el de «55 o más» y el de «Más de 5 años», los dos sin techo).

### D.5 Listados maestros — RESUELTA

**Respondida** con `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`, hoja «Listas». Trae las once listas, cada una en una columna, más una hoja de notas y fuentes oficiales.

| Lista | Estado |
|---|---|
| EPS | **Cargada** (18 opciones, incluidas «Régimen Especial / de Excepción» y «Otra»). |
| Fondos de pensión | **Cargada** (7, incluidas «Pensionado» y «Otro»). |
| ARL | **Cargada** (9). |
| Cajas de compensación | **Cargada** (34). Se ofrecen todas, sin filtrar por departamento. |
| Entidades bancarias | **Cargada** (25, incluidas Nequi, Daviplata y RappiPay). |
| Tipo de riesgo | Respondida: con descripción — «Riesgo I – Mínimo» a «Riesgo V – Máximo». Se aplica en **F1-04**. |
| Género | Respondida: Femenino · Masculino. Se aplica en **F1-04**. |
| Nivel educativo | Respondida: Ninguno · Básica Primaria · Bachiller · Técnico / Tecnólogo · Profesional · Posgraduado(a). Se aplica en **F1-06**. |
| Estado civil | Respondida: Soltero(a) · Casado(a) · Unión libre · Separado(a) · Divorciado(a) · Viudo(a). Se aplica en **F1-06**. |
| Medio de transporte y tiempo de desplazamiento | Respondidas: seis medios más «Otro», y cinco rangos de tiempo. Se aplican en **F1-06**. |

Las cinco de arriba están en `database/data/entidades.php` y las carga `EntidadesSeeder`; las seis de abajo se aplican cuando se construyan los formularios que las usan.

**Queda una pregunta abierta que trajo el propio archivo:** la columna «Cesantías» (Porvenir, Protección, Colfondos, Skandia, FNA, Otro). El fondo de cesantías **no está en la sección 5.1 de la especificación** y no tiene ni tabla ni columna. Ver `ESTADO.md`.

*Estas listas deben poder actualizarse sin tocar el código, porque van a cambiar con el tiempo:* eso es la tarea **F1B-04**, administración de catálogos desde el panel del proveedor. Hasta entonces se actualizan en `database/data/entidades.php` y se recarga el seeder, que actualiza sin duplicar.

### D.6 Confirmaciones menores — RESUELTAS, las cuatro, el 2026-08-25

| Punto | Decisión |
|---|---|
| Orden del dashboard | **Confirmado tal como está construido.** F4-03 puso el bloque de acción primero y F4-04 los seis bloques de estado después, que es exactamente lo que proponía el capítulo 12. No hubo que tocar código. |
| Vigencia del usuario de demostración | **14 días.** Dentro del rango sugerido (7 a 15), y da margen para una conversación comercial que se alargue una semana sin que el prospecto lo use como sistema real. Ver la tarea nueva `F5-08` para dejarlo como valor sugerido en el formulario, que hoy no propone ninguno. |
| Nombre del proceso de carga inicial | **"Carga inicial"**, que es el nombre que ya usan el documento 1 (capítulo 12), el documento 2 y el código (`app/Carga/`, `cargas_iniciales`) desde que se construyó. Cambiarlo ahora sería el único motivo real para tocar código; se confirma el que ya está. |
| ¿Se cargan también empleados retirados en el histórico? | **No, queda fuera del alcance del MVP.** La propia propuesta ya se inclinaba por esto —"probablemente no valga la pena"—, y la plantilla obligatoria de F5-01/F5-02 sigue cubriendo solo activos. Si un cliente lo pide, es una carga manual con el módulo de terminaciones, no un cambio a la plantilla. |

### D.7 Las cuatro dudas de las grabaciones del 8 y 10 de septiembre — RESUELTAS por David el 2026-09-16

David recorrió el sistema con datos reales y dejó tres grabaciones. En cuatro puntos pidió
expresamente una recomendación antes de que se construyera nada. Las cuatro quedaron decididas
antes de escribir las tareas `HD-01` a `HD-16`, para que ninguna naciera bloqueada.

| Punto | Decisión |
|---|---|
| Bogotá bajo «Bogotá D.C.» y no bajo Cundinamarca | **El catálogo no se toca; se arregla la guía.** El DANE define Bogotá como Distrito Capital, entidad propia de nivel departamental (código 11), no como municipio de Cundinamarca, y el catálogo ya lo refleja correctamente: Cundinamarca tiene sus 116 municipios y Bogotá no está entre ellos. Duplicar Bogotá dentro de Cundinamarca partiría los reportes en dos. Lo que cambia es la ayuda al usuario: el selector advierte, el buscador la encuentra y el error de la carga masiva explica en vez de solo rechazar. Ver `HD-07`. |
| Cómo registrar las prórrogas en la carga masiva | **Una sola plantilla con columnas de prórrogas**, no dos archivos separados por tipo de contrato. Pares inicio/fin para hasta seis prórrogas, en un bloque marcado «solo para contratos a término fijo» que los demás dejan vacío. Un archivo, una carga y un solo juego de validaciones que mantener. Ver `HD-09`. |
| Qué hacer cuando una incapacidad cae sobre vacaciones ya registradas | **Recalcular y dejar nota.** Los días de vacaciones que la incapacidad se comió vuelven al saldo; la novedad conserva las fechas pedidas originalmente pero registra los días realmente disfrutados, con una nota visible que explica el traslape. La alternativa —solo advertir— deja el saldo mal hasta que alguien se acuerde de corregirlo a mano. Ver `HD-04`. |
| Terminación de un contrato a término fijo cuyo estado nadie aclaró | **Se bloquea hasta aclararlo.** No se registra la terminación mientras haya un vacío entre el último periodo contractual conocido y la fecha de salida, porque de qué contrato regía depende qué indemnización aplicaría. El mensaje dice las fechas exactas que faltan y lleva a registrar la prórroga o el paso a indefinido. Ver `HD-12`. |

##### Lo que sigue siendo pregunta para Angela

La norma exacta que sustenta que **la incapacidad interrumpe el disfrute de vacaciones**. El
comportamiento ya lo decidió David y `HD-04` se puede construir sin la respuesta, pero la constante
tiene que quedar citada en `config/reglas_laborales.php` con su norma y su fecha de verificación,
como todas las demás. Mientras no llegue, se escribe con la nota de que falta la cita —no con una
norma inventada—.

## E. Fase dos

Ideas fuera del MVP pero vivas. La posición acordada es revisarlas cuando existan los primeros clientes y se sepa qué usa realmente la gente.

### E.1 Módulo de llamados de atención

**Recomendación: prioridad alta dentro de la fase dos.** El argumento cambió con la reforma laboral de 2025, que elevó a rango legal las garantías del debido proceso disciplinario. Antes de imponer una sanción —un llamado de atención con efectos, una suspensión o un despido con justa causa por falta disciplinaria— el empleador debe respetar la presunción de inocencia, la proporcionalidad, el derecho a la defensa y la contradicción de las pruebas.
La consecuencia práctica: un despido por justa causa sin procedimiento disciplinario documentado puede terminar tratado como despido sin justa causa, con la indemnización que ello implica. Y el segmento objetivo —empresas pequeñas sin área jurídica— es justamente el menos preparado para esto.

##### Qué contendría

- Registro del hecho: fecha, descripción, testigos si los hay.
- Citación a descargos con su fecha y constancia de entrega.
- Registro de los descargos rendidos por el trabajador.
- Decisión adoptada, su fecha y su fundamento en el reglamento interno.
- Tipo de sanción: llamado verbal, escrito, suspensión, terminación.
- Historial disciplinario por empleado, que conecta de forma natural con el motivo de baja "problemas disciplinarios" ya previsto.
*Conviene que el modelo de datos contemple su existencia futura y que se mencione como funcionalidad en desarrollo en las conversaciones comerciales.*

### E.2 Enlace al expediente en Google Drive

La idea: dejar un campo donde el cliente pegue el enlace a una carpeta propia, de modo que quede el registro del expediente sin que el proveedor almacene nada.
**Se descartó del MVP por un riesgo concreto:** un cliente puede configurar ese enlace como público, filtrarse la información y atribuir la responsabilidad al proveedor. Para retomarla habría que dejar expresamente establecido en el contrato que el proveedor no accede ni controla ese contenido y que la configuración de acceso es responsabilidad exclusiva del cliente.

### E.3 Módulo de reportes centralizado

Se sacó del MVP porque la exportación desde cada módulo cubre la necesidad. Volvería a tener sentido cuando aparezcan reportes que crucen información de varios módulos y que los clientes pidan de forma recurrente —el consolidado de novedades del mes para nómina es el candidato más probable.

### E.4 Asistente de inteligencia artificial

Un asistente conversacional para consultar la información en lenguaje natural. Comercialmente atractivo y contemplado como servicio adicional de una licencia superior. El argumento para posponerlo sigue en pie: si la plataforma está bien diseñada, debería responder por sí sola la mayoría de las necesidades del área, sin un asistente que compense una interfaz difícil.

## F. Riesgos a vigilar

| Riesgo | Mitigación |
|---|---|
| Fuga de datos entre empresas clientes | Filtro por empresa en la capa de datos, verificación de permisos en el servidor y pruebas automatizadas de aislamiento. Es el fallo que mata un SaaS. |
| Error de cálculo replicado en todos los clientes | Un solo motor de cálculo con tres funciones de conteo explícitas y con nombre distinto: vacaciones en días hábiles, incapacidades en días corridos, licencias en días corridos. Pruebas automatizadas con los casos discutidos en sesión. |
| Reglas de contrato a término fijo mal codificadas | Validación con asesor laboral antes de programar. Reglas parametrizadas, no incrustadas en el código. |
| Calendario de festivos desactualizado | Tarea anual asignada a una persona, ejecutada antes de que empiece el año, incluyendo los festivos que caen en sábado. |
| Alertas que no llegan a la bandeja de entrada | Dominio propio con SPF, DKIM y DMARC, proveedor de correo transaccional y registro de envíos consultable. |
| Carga inicial mal hecha | Es el momento de mayor riesgo del ciclo de vida del cliente. Validación de plantillas con reporte de errores por fila, y ejecución a cargo del proveedor. |
| Clientes que pagan pero no usan el sistema | Medir desde el primer mes cuántos empleados tienen cargados, con qué frecuencia entran y cuántas novedades registran. |
| Crecimiento del alcance | La Especificación Funcional define el alcance por escrito. Toda idea nueva va a este documento, bloque E. |
| Dependencia de una sola persona para el desarrollo | Documentación técnica mínima, código en repositorio con acceso compartido y credenciales críticas en poder de ambos socios. |

## G. Antes de vender la primera licencia

| Ámbito | Punto |
|---|---|
| Legal | Sociedad constituida y facturación electrónica habilitada. |
| Legal | Contrato de prestación del servicio revisado por abogado. |
| Legal | Acuerdo de tratamiento de datos revisado y listo para firmar. |
| Legal | Plantilla de autorización para que el cliente la use con sus empleados. |
| Legal | Reglas de contrato a término fijo validadas con asesor laboral. |
| Técnico | Pruebas de aislamiento entre empresas en verde. |
| Técnico | Pruebas del motor de cálculo con las tres reglas de conteo. |
| Técnico | Calendario de festivos del año cargado y verificado, con sábados incluidos. |
| Técnico | Copias de seguridad automáticas y una restauración probada. |
| Técnico | Correo llegando a bandeja de entrada desde dominio propio. |
| Técnico | Módulo de auditoría activo. |
| Negocio | Costo por cliente calculado y tabla de precios definida. |
| Negocio | Pasarela de pagos con cobro recurrente operativa. |
| Negocio | Plantillas de carga inicial listas y probadas con datos reales. |
| Negocio | Ambiente de demostración poblado con datos ficticios. |
| Negocio | Guion de la reunión de onboarding documentado y cronometrado. |

## Fuentes consultadas

### Contrato a término fijo y reforma laboral

Ley 2466 de 2025, texto oficial — dapre.presidencia.gov.co
Concepto MinTrabajo 08SE2026120300000006157 del 18 de febrero de 2026, sobre prórrogas del contrato a término fijo — accounter.co
Fenalco Antioquia, «Contratos a término fijo: ¿a partir de qué prórroga debe ser renovado por el término de un año?» — fenalcoantioquia.com
Hernández & Abogados, «La regla de la cuarta prórroga y el tope de cuatro años» — hblegalcorp.com
Código Sustantivo del Trabajo, artículos 45, 46, 51, 53, 61, 62, 78, 186, 190 y 488 — secretariasenado.gov.co

### Obra o labor y cambio de modalidad

Gerencie.com, «¿Un contrato por obra o labor se puede volver indefinido?» y «Otrosí en el contrato de trabajo»
Notaría 19 de Bogotá, «Cambios en el contrato de obra o labor»
Actualícese, «Periodo de prueba cuando se firman contratos sucesivos»

### Incapacidades, vacaciones y SG-SST

Instituto Nacional de Contadores Públicos, «El empleador es quien debe solicitar la transcripción de incapacidades ante la EPS»
Gerencie.com, «Licencias no remuneradas»
Actualícese, «Vacaciones laborales en Colombia»
SafetYA, «Los indicadores como garantía técnica del SG-SST» y «Conservación de la información laboral según el Decreto 1072 de 2015»
GSL Ocupacional, «Resolución 0312 de 2019, estándares mínimos del SG-SST»

### Protección de datos

Superintendencia de Industria y Comercio: preguntas frecuentes de protección de datos, Registro Nacional de Bases de Datos y deberes del encargado del tratamiento — sic.gov.co
Ley Estatutaria 1581 de 2012 — funcionpublica.gov.co

*Fin del documento de Puntos Pendientes — Versión 3.0*
