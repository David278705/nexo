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
| 4 | Decisión de infraestructura y modelo de seguridad | A | David | No |
| 5 | Constitución de la sociedad, DIAN y pasarela de pagos | A | Ambos | No |
| 6 | Confirmar el conteo de prórrogas cortas con asesor laboral | B | Ambos | Sí, parcial |
| 7 | Confirmar el periodo de prueba en cambio de modalidad contractual | B | Ambos | No |
| 8 | Decidir si las validaciones de prórroga bloquean o solo advierten | B | Ambos | Sí, parcial |
| 9 | ¿Se registra el tipo de prórroga (pactada o automática)? | D | Angela | Sí |
| 10 | Umbral de la alerta de contrato de obra o labor prolongado | D | Angela | Sí |
| 11 | Anticipación de la alerta de cercanía al tope de cuatro años | D | Angela | Sí |
| 12 | Vigencia del usuario de demostración | D | Angela | No |
| 13 | Rangos de edad y de antigüedad para el dashboard | D | Angela | Sí |
| 14 | Listados maestros: EPS, fondos, ARL, cajas, bancos | D | Angela | Sí |
| 15 | Confirmar el orden propuesto del dashboard | D | Angela | No |
| 16 | Nombre definitivo del proceso de carga inicial | D | Angela | No |
| 17 | Módulo de llamados de atención | E | Ambos | No |
| 18 | Enlace al expediente en Google Drive | E | Ambos | No |
| 19 | Módulo de reportes centralizado | E | Ambos | No |
| 20 | Asistente de inteligencia artificial | E | Ambos | No |

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

### A.5 Infraestructura y seguridad

Se decidió mirarlo cuando exista producto. Cuando llegue el momento, la comparación es esta:

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

### B.1 El conteo de prórrogas cortas

**BLOQUEANTE PARCIAL.** La regla está clara en la sección C.1 de este documento, respaldada por el concepto del Ministerio del Trabajo. Lo que conviene confirmar es su aplicación práctica antes de convertirla en una validación que impide guardar. La razón: si el sistema bloquea algo que en realidad es legal, el cliente no puede operar y la culpa es del sistema.
Mientras se obtiene la confirmación, se puede construir el conteo de prórrogas y la alerta, dejando el bloqueo como advertencia. Convertir la advertencia en bloqueo es un cambio de una línea.

### B.2 Periodo de prueba en cambio de modalidad contractual

La conclusión de la sección C.3 es que no procede pactar periodo de prueba cuando un contrato de obra o labor pasa a indefinido, ni por otrosí ni firmando un contrato nuevo. Es la lectura conservadora y la que protege al cliente, pero conviene confirmarla porque el escenario de "la nueva labor es completamente distinta" no está resuelto expresamente en la norma.

### B.3 ¿Bloquear o advertir?

**BLOQUEANTE PARCIAL.** Angela planteó que si una prórroga supera el tope legal el sistema no debería dejarla registrar, porque sería registrar algo ilegal. El argumento es sólido. La contrapartida a considerar:

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

### C.1 La regla de la cuarta prórroga

**La duda:** si se firma un contrato de un mes, ¿pueden hacerse cuatro prórrogas más y es en la quinta cuando debe ser de un año?
**La respuesta:** sí, en las prórrogas pactadas por escrito. Pero la regla no es la misma para los dos tipos de prórroga, y esa es la parte que se presta a confusión.

|  | Prórroga pactada por escrito | Prórroga automática por silencio |
|---|---|---|
| Duración de cada prórroga | La que las partes acuerden: puede ser inferior, igual o superior a la del contrato inicial | Igual al término inicialmente pactado o al de su última prórroga |
| Cuántas pueden ser cortas | Hasta cuatro | Tres |
| A partir de cuál debe ser mínimo un año | A partir de la quinta | La cuarta ya debe ser de un año |

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

### D.1 ¿Se registra el tipo de prórroga?

**BLOQUEANTE.** Como se explicó en C.1, el conteo de prórrogas cortas es distinto según la prórroga sea pactada por escrito o automática por silencio. Para contarlas bien, el sistema necesita saber de cuál se trata.
**Propuesta:** agregar un campo a la acción "Renovar contrato" con dos opciones: **Prórroga pactada por escrito** o **Prórroga automática por silencio** . Es un campo más en un formulario que ya existe, y resuelve tanto el conteo como el reporte de historial de prórrogas.
Alternativa si se quiere simplificar: aplicar siempre la regla más estricta, la de la prórroga automática, que avisa una prórroga antes. Es más conservador y no requiere el campo, pero puede generar alertas prematuras en clientes que sí formalizan sus prórrogas por escrito.

### D.2 Umbral de contrato de obra o labor prolongado

**BLOQUEANTE.** ¿A partir de cuántos meses sin registrar terminación debe aparecer un contrato de obra o labor en la alerta mensual? No hay un criterio legal: es una señal de gestión y depende de qué tan largas suelen ser las obras en las empresas del segmento.

### D.3 Anticipación de la alerta de cercanía al tope

**BLOQUEANTE.** ¿Con cuánta anticipación debe avisarse que un contrato a término fijo se acerca a los cuatro años? La decisión importa porque de ella depende que la empresa tenga tiempo de decidir. Sugerencia: seis meses antes, para que alcance a planear una renovación de un año si la quiere, y de nuevo a los tres meses.

### D.4 Rangos de edad y de antigüedad

**BLOQUEANTE.** El dashboard muestra distribución por rango de edad y por rango de antigüedad. Faltan los cortes. Sugerencia inicial, a confirmar:
- Edad: menos de 25 · 25 a 34 · 35 a 44 · 45 a 54 · 55 o más.
- Antigüedad: menos de 6 meses · 6 meses a 1 año · 1 a 3 años · 3 a 5 años · más de 5 años.

### D.5 Listados maestros

**BLOQUEANTE.** Varias listas desplegables necesitan su contenido antes de poder construirse. Angela es quien conoce cuáles son las opciones que las empresas usan realmente:

| Lista | Comentario |
|---|---|
| EPS | Listado completo de las vigentes en Colombia. Es la lista más larga y la que más cambia. |
| Fondos de pensión |  |
| ARL |  |
| Cajas de compensación | Varían por departamento. |
| Entidades bancarias |  |
| Tipo de riesgo | Niveles I a V. Confirmar si se presentan con descripción o solo el número. |
| Género | Confirmar las opciones que se van a ofrecer. |
| Nivel educativo | Para el perfil sociodemográfico. |
| Estado civil | Para el perfil sociodemográfico. |
| Medio de transporte y tiempo de desplazamiento | Para el perfil sociodemográfico. |

*Estas listas deben poder actualizarse sin tocar el código, porque van a cambiar con el tiempo.*

### D.6 Confirmaciones menores

| Punto | Propuesta |
|---|---|
| Orden del dashboard | Está propuesto en el capítulo 12 de la Especificación: primero lo que exige acción, después el estado de la empresa. Falta el visto bueno. |
| Vigencia del usuario de demostración | Sugerencia: entre 7 y 15 días. Suficiente para explorar sin que el prospecto lo use como sistema real. |
| Nombre del proceso de carga inicial | Quedó pendiente ponerle nombre. Sugerencias: "Puesta en marcha", "Carga inicial" o "Registro de empleados antiguos". |
| ¿Se cargan también empleados retirados en el histórico? | La plantilla obligatoria cubre solo los empleados activos. Si la empresa quiere ver rotación histórica desde antes de usar el sistema, habría que cargar también las vinculaciones terminadas. No se habló de esto y probablemente no valga la pena. |

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
