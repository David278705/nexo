# Plan de salida al mercado

> Los treinta pasos en orden para que el sistema se le pueda vender a una PYME colombiana. Revisión
> dirigida de las partes críticas, no línea por línea, el 2 de noviembre de 2026. Versión legible y compartible con el abogado y el
> contador: https://claude.ai/artifact/CDMcrjydi9TpAp6LFhSU2g
>
> **Este documento no reemplaza al `BACKLOG.md`.** El backlog tiene las tareas de código; aquí están
> además las que no son código, que son la mayoría de lo que falta. Los pasos que sí son código
> entran al backlog como tareas cuando llegue su turno.

## Estado verificado el 2026-11-02

| Qué | Cómo está |
|---|---|
| Código | 39.035 líneas PHP · 13.133 Vue · 48 migraciones |
| Verificación | 1.388 Pest + 418 Vitest en verde · PHPStan y Pint sin errores |
| Backlog | 102 de 105 tareas hechas; las 3 abiertas (`F3-06b`, `F6-07`, `HD-05`) no son código |
| Aislamiento entre empresas | Dos capas y 49 casos de prueba. No auditado línea por línea |
| Motor de alertas | 13 reglas registradas, idempotencia por índice único, 3 reintentos, registro de ejecuciones |
| Panel del proveedor | 8 de 9 secciones construidas; falta «Equipo» |

**Hallazgos de la revisión:**

1. **`composer audit --locked` reporta 5 avisos, tres altos**: tres en `phpoffice/phpspreadsheet`, uno en
   `symfony/yaml` y uno en `symfony/mime`. El `vendor/` local está desactualizado (Laravel 12.66.0 frente a
   12.69.3 en el lock): por eso allí salen 12, con un crítico que no llegaría a la imagen.
2. Los cuatro documentos de `resources/legal/` siguen siendo borradores con un aviso.
3. `MAIL_MAILER=log`: ningún correo sale todavía.
4. La copia de seguridad nunca se ha restaurado.
5. `public/favicon.ico` pesa 0 bytes. No hay logo, ni landing, ni tabla de precios.
6. No hay canal de soporte visible para el cliente dentro de la aplicación.

## Quién hace cada paso

Cada paso lleva una etiqueta. La línea que las separa es simple: **todo lo que vive dentro del
repositorio lo hace Claude; todo lo que pide una cuenta, un pago, una firma, un registro en el DNS,
una bandeja de entrada o una decisión de negocio, no puede hacerlo y no debería.**

| Etiqueta | Cuántos | Qué significa |
|---|---|---|
| `[Claude]` | 5 | Entero dentro del repositorio: pasos 01, 12, 16, 17 y 26. Alrededor de una semana con la landing. |
| `[Los dos]` | 11 | Claude hace el código o el borrador; David y Angela dan el dato o la decisión. Cada uno dice cuál es su mitad. |
| `[Solo David]` | 14 | Cuentas, pagos, firmas, registros en el DNS, y lo que hay que mirar con los ojos propios: en qué carpeta cayó el correo, si la restauración trajo el dato. |

**Lo que Claude no debe hacer aunque pudiera:** entrar a la cuenta de Railway. Es infraestructura de
producción y está anotado así desde `F6-07`. Los comandos de la consola del servicio (`equipo:invitar`,
`despliegue:verificar`, `alertas:prueba`) los corre David allá, no una sesión de código.

## Fase 1 · Lo que no puede esperar

Esta semana. Sin costo.

- [x] `[Claude]` **01 · Actualizar las dependencias.** **Hecho el 2026-11-03 (`PROD-13`).** El lock
      del 1 de octubre se había generado con PHP 8.4 y traía Symfony 8.1, que exige PHP 8.4, aunque el
      proyecto declara 8.2 como mínimo: por eso `composer install` fallaba en local y las pruebas
      corrían contra un `vendor/` viejo. Se fijó `config.platform.php` en 8.2.12 y se actualizó: Laravel
      12.71.0, PhpSpreadsheet 1.30.7, Symfony 7.4.22. `composer audit` sin avisos, en lo instalado y en
      el lock; la verificación completa en verde. Producción sigue en PHP 8.4, que es compatible.
- [ ] `[Los dos]` **02 · Fijar el nombre comercial y comprobar que esté libre.** Búsqueda de antecedentes en el
      registro de marcas de la SIC (gratis, en línea) y disponibilidad del dominio. Bloquea el
      dominio, el logo, la sociedad y la marca. **1 día.**
      **Mi parte:** busco los antecedentes en el registro de marcas de la SIC y compruebo qué dominios
      están libres. El nombre lo eligen ustedes dos.
- [ ] `[Los dos]` **03 · Calcular el costo por cliente y fijar la tabla de precios** (documento 6, A.1: primero
      el costo, después el precio). La cuenta está abajo. Bloquea el contrato y la landing. **Medio día.**
      **Mi parte, hecha el 2026-11-03:** `docs/salida/propuesta-de-precios.md` — tres planes por tamaño —Micro, Pequeña, Mediana— con la cuenta del punto de equilibrio. **Falta:** que David y Angela fijen los números y creen los planes en el panel.
## Fase 2 · Formalización

2 a 4 semanas. Arranca la misma semana que la fase 1: depende de terceros.

- [ ] `[Solo David]` **04 · Constituir la SAS** en la Cámara de Comercio. Sin notaría ni capital mínimo. Matrícula
      del primer tramo (activos hasta $78.715.000 en 2026): `$24.220 + $8.477 × (activos/1.000.000)`.
      Más derechos de inscripción, formulario RUES, libros y certificado. **~$200.000 · 1-2 semanas.**
      Motivo (documento 6, A.6): vender como persona natural expone el patrimonio personal ante un
      incidente de datos, y aquí se tratan datos de salud de empleados de terceros.
- [ ] `[Solo David]` **05 · RUT, responsabilidad de IVA y régimen tributario.** El SaaS está gravado al 19%, así que
      el precio publicado tiene que decir si lo incluye. Decidir con el contador: régimen simple
      (tarifa única bimestral, reemplaza renta e ICA) contra ordinario. **$0 · 1 día.**
- [ ] `[Solo David]` **06 · Cuenta bancaria de la sociedad.** **$0 · pocos días.**
- [ ] `[Solo David]` **07 · Habilitar facturación electrónica ante la DIAN.** La solución gratuita de la DIAN
      incluye el certificado de firma y basta para pocas facturas al mes. Un proveedor habilitado
      cuesta mensualidad y se integra con la contabilidad. **$0 con la DIAN, o $50.000-$120.000/mes · 1 semana.**
- [ ] `[Solo David]` **08 · Contratar al contador.** **$300.000-$900.000/mes · 1 semana.** Es el costo recurrente
      más alto del proyecto, por encima de la infraestructura.
- [ ] `[Solo David]` **09 · Firmar el acuerdo entre socios.** Participación, aportes, propiedad del código y qué
      pasa si uno se retira. Va con el abogado de la fase 3. **$1.000.000-$2.500.000 · 2 semanas.**

## Fase 3 · El paquete legal

2 a 4 semanas, en paralelo con la fase 2. El plazo lo pone el abogado.

- [ ] `[Los dos]` **10 · Encargarle al abogado los cuatro textos.** Uno por archivo de `resources/legal/`:
      contrato de prestación del servicio, acuerdo de tratamiento de datos (cliente responsable /
      proveedor encargado, Ley 1581 de 2012), política de tratamiento del proveedor, y plantilla de
      autorización para los empleados del cliente **con autorización expresa para datos de salud**
      (el sistema guarda `codigo_diagnostico`, que es dato sensible). Los componentes del contrato
      están en el documento 6, A.2, y no hay que inventarlos. **$2.000.000-$6.000.000 · 2-4 semanas.**
      **Mi parte, hecha el 2026-11-03:** `docs/salida/encargo-al-abogado.md` — qué datos trata el sistema, cómo se protegen, los terceros que los tocan fuera de Colombia y las seis preguntas que el abogado tiene que resolver. **Falta:** contratar al abogado y entregárselo.
- [ ] `[Los dos]` **11 · Decidir el SLA antes de que el abogado lo redacte.** Recomendación del documento 6, A.3:
      99% mensual, respuesta el mismo día hábil si está caído para todos (documento 4, 13.1), y
      descuento en factura si se incumple. Un SLA sin consecuencia no es exigible. **$0 · 1 hora.**
      **Mi parte, hecha el 2026-11-03:** `docs/salida/opciones-de-sla.md` — recomendación de 99 %, los tiempos de respuesta del documento 4, un compromiso propio sobre las alertas y la tabla de descuentos. **Falta:** que David y Angela confirmen los números.
- [ ] `[Claude]` **12 · Pegar los cuatro textos en el sistema.** Cada uno en su archivo, `estado: borrador` →
      `estado: vigente`, subir la `version`. Sin tocar código (`docs/08-despliegue.md`). Desde ahí
      ninguna empresa trabaja hasta que su superadministrador acepte. **$0 · 1 hora.**
- [ ] `[Los dos]` **13 · Montar el canal de habeas data y escribirlo en la política.** Un correo propio y los
      plazos de la Ley 1581: consultas 10 días hábiles, reclamos 15. **$0 · 2 horas.**
      **Mi parte, hecha el 2026-11-03:** `docs/salida/manual-de-consultas-y-reclamos.md` — el manual que el artículo 18, literal f, de la Ley 1581 le exige al encargado, con los plazos de la ley. **Falta:** el correo y quién responde.
- [ ] `[Los dos]` **14 · Las dos preguntas abiertas: respondidas, falta validarlas.** Salen de la norma
      y de sentencias; el abogado y el asesor laboral validan. **$0 extra.**

      **Pregunta 1. ¿Qué conserva el proveedor cuando un cliente se va?** Suprime los datos de los
      empleados y conserva solo lo que una ley o el contrato exijan. Base: artículos 11 y 25 del
      Decreto 1377 de 2013 y artículo 28 de la Ley 962 de 2005 (diez años). El decreto no regula el fin
      del contrato con el encargado, así que la regla la fija el contrato.

      | Dato | Hoy | Qué debe pasar |
      |---|---|---|
      | Empleados y operación, usuarios, auditoría, accesos | Entrega y elimina | Correcto |
      | Suscripciones y pagos | Conserva sin plazo | Diez años desde el último asiento; después eliminar |
      | Notas internas | Conserva sin plazo | Solo lo comercial; son texto libre y pueden nombrar empleados |
      | Aceptaciones legales | Las elimina | Conservarlas como prueba del contrato (recomendación) |
      | Alertas enviadas | Entrega y elimina | Eliminar el contenido; guardar registro sin nombres (recomendación) |
      | Constancias de entrega y eliminación | Solo se imprimen | Guardarlas, con el SHA-256 del ZIP entregado |

      El contrato no debe decir «no conserva nada» (A.4), sino qué se suprime y qué se conserva y por
      cuánto. Esto **no se resuelve con solo cambiar una lista**: el plazo pide una tarea de borrado, y
      aceptaciones, registro de alertas y constancias piden código (las aceptaciones dependen de `users`).

      **Pregunta 2 (B.2). ¿Periodo de prueba al pasar de obra o labor a indefinido con labor distinta?**
      No. Por otrosí es el mismo contrato. Si la obra termina y la persona sigue, el artículo 46 del CST
      (artículo 6 de la Ley 2466 de 2025) lo vuelve indefinido desde el inicio de la relación, y la
      excepción de «nueva y diferente obra» solo permite seguir en obra o labor. Si se liquida y se firma
      otro contrato, el artículo 78 solo admite la prueba en el primero y no exceptúa la labor distinta.
      Reseñas del Concepto 95880 de 2016 de MinTrabajo abren una tesis contraria, pero no encontré
      sentencia de la Corte Suprema que avale un nuevo periodo. Si la cláusula no vale, el riesgo es la
      indemnización del artículo 64. La Ley 2466 no menciona el periodo de prueba.

      El sistema **no aplica** esa lectura: advierte, no bloquea y genera las alertas (documento 1, 5.6),
      y así debe seguir. **Hallazgo:** el otrosí de obra o labor a indefinido no se puede registrar, porque
      `CambioAIndefinido` solo admite término fijo. Hay que decidir si se extiende.

      **Límite:** se leyó el texto oficial del Decreto 1377, la Ley 2466 y la T-1097 de 2012. La Ley 962, el
      Concepto 95880 y la consecuencia del artículo 64 vienen de fuentes secundarias. La frase sobre
      «diferencias esenciales» que se atribuye a la T-1097 no está en esa sentencia: no citarla.

      **Para validar.** Al abogado: «¿Está de acuerdo con esta tabla de conservación y sus plazos, y cómo
      debe quedar en el contrato y en el acuerdo de tratamiento?». Al asesor laboral: «¿Existe
      jurisprudencia de la Corte Suprema que admita un nuevo periodo de prueba en un contrato sucesivo
      con labor distinta, o mantenemos la recomendación de no pactarlo?».
      **Mi parte, hecha el 2026-11-03:** la pregunta del abogado va en el punto 3 de `docs/salida/encargo-al-abogado.md`; la del asesor laboral es la de arriba. Cuando respondan, aplico los cambios en `TablasDeLaEmpresa`, la eliminación y las constancias. **Falta:** las dos respuestas.

## Fase 4 · Marca y remates del producto

1 semana, en paralelo. Es código y diseño.

- [ ] `[Los dos]` **15 · Logo y favicon.** `public/favicon.ico` pesa 0 bytes.
      **$0 si lo hacen ustedes, o $400.000-$1.500.000 · 2-5 días.**
      **Mi parte:** puedo hacer una marca de palabra y un favicon válido, e instalarlos. Un diseñador lo
      hará mejor; esto evita salir con la pestaña en blanco.
- [x] `[Claude]` **16 · Canal de soporte visible dentro de la aplicación.** **Hecho el 2026-11-03
      (`PROD-12`).** Bloque «¿Necesita ayuda?» al pie de la barra lateral, con correo, WhatsApp opcional
      y horario, para todo usuario de una empresa. Se configura con `SOPORTE_CORREO`,
      `SOPORTE_WHATSAPP` y `SOPORTE_HORARIO`, sin tocar código; mientras el correo esté vacío no se
      enseña y `despliegue:verificar` lo avisa. **Le falta a David:** crear el correo y ponerlo en
      Railway. El horario tiene que coincidir con el SLA del paso 11.
- [x] `[Claude]` **17 · Campo de dinero con formato colombiano.** **Hecho el 2026-11-03 (`PROD-11`).**
      El problema no estaba solo en «Monto recibido»: eran **cinco campos**, también el salario y la
      bonificación del registro de empleado, el nuevo salario de un movimiento y el valor mensual de un
      plan. Los cinco usan ahora `CampoDinero`: se escribe «1.750.905» o «380.000,50», se ve
      «$ 1.750.905» al salir del campo, y al servidor llega el número limpio.

## Fase 5 · Poner el sistema en producción

1 semana. Necesita el paso 02. La guía completa es `docs/08-despliegue.md`.

- [ ] `[Solo David]` **18 · Comprar el dominio.** La landing en la raíz, la aplicación en `app.`. Revisar el precio
      de **renovación**, no el del primer año. **$60.000-$130.000/año · 1 hora.**
- [ ] `[Solo David]` **19 · Desplegar en Railway: PostgreSQL y los tres servicios** (`NEXO_ROL` = web, worker,
      scheduler). Sin el worker las alertas se calculan y no sale ningún correo; sin el scheduler ni
      se calculan. Scheduler **siempre una réplica**; los dos de fondo con reinicio *Always*.
      **USD 25-45/mes · 1-2 días.**
      **Mi parte:** no entro a la cuenta de Railway, y no debería. Sí puedo dejar la lista de variables
      lista para pegar y leer el log del primer arranque si David lo copia a la sesión.
- [ ] `[Solo David]` **20 · Conectar Sentry** (`SENTRY_LARAVEL_DSN`). El paquete ya está instalado. **$0 · 1 hora.**
- [ ] `[Solo David]` **21 · Correo transaccional, SPF, DKIM, DMARC, y `alertas:prueba` cayendo en bandeja de
      entrada.** Es la tarea `F3-06b` y la que decide si el producto cumple lo que vende. Resend da
      3.000 correos al mes gratis; Postmark desde USD 15 por 10.000; SES el más barato a volumen.
      **$0-USD 15/mes · 1 día.**
      **Mi parte:** no veo la bandeja de entrada, que es justo lo que hay que mirar. Sí puedo dejar escritas
      las cinco líneas `MAIL_*` y los tres registros del DNS para el proveedor que se elija.
- [ ] `[Solo David]` **22 · Crear las dos primeras cuentas con `equipo:invitar`,** activar el segundo factor en las
      dos, y **borrar `DB_URL_ADMIN`** del servicio web. **$0 · 1 hora.**
- [ ] `[Solo David]` **23 · Hacer el simulacro de restauración de la base** (`F6-07`, `docs/07-plan-de-incidentes.md`
      sección 4) y dejar la evidencia en `bitacora/`. Ninguna prueba automática puede demostrarlo.
      **$0 · 2 horas. Solo David, con su cuenta.**

También quedan por comprobar allá las tres cosas del paso 8 de `docs/08-despliegue.md`: que el
registro de accesos guarde las IPs reales, que la imagen compile, y `despliegue:verificar` todo en ✔.

## Fase 6 · Cómo se cobra

2 días. Necesita la fase 2.

- [ ] `[Solo David]` **24 · Cobrar por transferencia o PSE, con factura electrónica.** Es lo convencional con los
      primeros clientes de un software B2B en Colombia y es lo que el sistema ya hace: el panel
      registra el pago, calcula la mora y su escalera, y el cliente ve su historial en la pestaña
      «Facturación». Nada que programar. La pasarela recurrente (Wompi, ePayco: 1,5-2% por PSE,
      2,5-3,5% + IVA por tarjeta) exige una integración que no existe; déjenla para diez o quince
      clientes. **$0 · medio día.**
- [ ] `[Los dos]` **25 · Escribir en el contrato los mismos números de mora que ya tiene el sistema**
      (`config/panel.php`: aviso a los 5 días, segundo a los 15, suspensión a los 30). Y la regla del
      documento 6: ante el impago se suspende el servicio, **nunca** se retienen los datos. **$0.**
      **Mi parte, hecha el 2026-11-03:** `docs/salida/encargo-al-abogado.md` — la tabla con los siete números que el contrato tiene que copiar del sistema. **Falta:** que el abogado los copie.
## Fase 7 · Salir a vender

2 semanas. Necesita todo lo anterior.

- [ ] `[Claude]` **26 · Una landing estática en el dominio raíz.** Qué hace, para quién, la tabla de precios y
      los cuatro documentos legales enlazados a `/legal/{clave}`, que ya se leen sin sesión.
      Cloudflare Pages o similar. **$0 · 3-5 días.**
- [ ] `[Los dos]` **27 · Revisar el ambiente de demostración** y dejar listo un acceso de prueba de 14 días. Ya
      está construido (planta de muestra, restauración a las 3:00). **$0 · medio día.**
      **Mi parte:** levanto el entorno local y recorro las pantallas. Mirarla con ojos de cliente es criterio
      de David y Angela.
- [ ] `[Los dos]` **28 · Escribir y cronometrar el guion de la reunión de puesta en marcha.** La carga inicial es
      el momento de mayor riesgo del ciclo de vida del cliente, y la hacen ustedes. Cronometrarla da
      el número de soporte que el paso 03 solo pudo estimar. **$0 · 1 día.**
      **Mi parte, hecha el 2026-11-03:** `docs/salida/guion-de-puesta-en-marcha.md` — antes, durante y después de la reunión, con las pantallas reales y una columna para el tiempo real. **Falta:** cronometrarlo con el primer cliente.
- [ ] `[Solo David]` **29 · Registrar la marca ante la SIC.** Puede ir después de la primera venta, pero antes de
      gastar en publicidad. Tarifa reducida para microempresas; el sistema admite varias clases en
      una solicitud. **$1.179.000-$1.347.500 · meses de trámite.**
- [ ] `[Los dos]` **30 · El primer cliente, tratado como piloto.** Contrato y acuerdo firmados, carga inicial
      ejecutada por ustedes, y **a la mañana siguiente comprobar que sus alertas llegaron a bandeja
      de entrada**. Medir empleados cargados, frecuencia de entrada y **horas de soporte**: esa
      última confirma o corrige el precio. **El primer mes.**
      **Mi parte:** valido el archivo de carga inicial con `carga:simular` antes de la reunión: reporta los
      errores fila por fila y no escribe nada.

## Lo que cuesta

Valores de 2026. Las tarifas oficiales se recalculan cada enero sobre la UVB ($12.110 en 2026) o la
UVT. Las marcadas **(por confirmar)** hay que verificarlas antes de comprometer presupuesto.

### Un solo pago

| Concepto | Pesos |
|---|---|
| Constitución de la SAS (matrícula por activos bajos + inscripción + RUES + libros + certificado) | ~200.000 |
| Paquete legal con abogado, los cuatro documentos **(por confirmar)** | 2.000.000 – 6.000.000 |
| Acuerdo entre socios **(por confirmar)** | 1.000.000 – 2.500.000 |
| Logo y favicon | 0 – 1.500.000 |
| Registro de marca, una clase en línea, tarifa de microempresa **(por confirmar)** | 1.179.000 – 1.347.500 |
| **Total para arrancar** | **4.400.000 – 11.500.000** |

### Cada mes

| Concepto | Pesos |
|---|---|
| Contador | 300.000 – 900.000 |
| Railway (PostgreSQL + tres servicios; Pro USD 20 con crédito, más consumo) | ~100.000 – 180.000 |
| Correo transaccional (Resend gratis hasta 3.000; Postmark USD 15 por 10.000) | 0 – 60.000 |
| Sentry (plan gratuito) | 0 |
| Facturación electrónica (gratis con la DIAN) | 0 – 120.000 |
| Dominio, prorrateado | ~10.000 |
| **Total mensual** | **410.000 – 1.270.000** |

### De ahí sale el precio, y no al revés

El documento 6, A.1, pide el costo por cliente antes de la tabla de precios. Escenario medio con
cinco empresas clientes:

| | |
|---|---|
| Costo fijo mensual, escenario medio | $800.000 |
| Dividido entre 5 empresas | $160.000 |
| Soporte: 3 horas/mes por cliente a $60.000/hora | $180.000 |
| **Costo real por cliente, al mes** | **$340.000** |
| Con recargo del 60% (margen del 38%), precio por empresa | ~$550.000 |
| Cargo único de implementación (práctica habitual del segmento) | $500.000 – $1.500.000 |

Para comparar, el salario mínimo de 2026 es **$1.750.905**: una PYME pagaría menos de un tercio de un
salario mínimo al mes por no perder un plazo legal. El único número que falta es el soporte real, y
solo lo da el paso 30.

**El correo es el único costo que crece con los clientes**, porque cada alerta va a cada administrador
que recibe copia. Es la justificación técnica de cobrar por usuario además de por empleado.

## Lo que NO hay que hacer antes de vender

Nada de esto se lo pide un cliente PYME colombiano a un proveedor de tres clientes.

- **Pasarela de pagos con cobro recurrente.** Paso 24.
- **Registro Nacional de Bases de Datos de la SIC.** Solo obliga a sociedades con activos superiores
  a 100.000 UVT (~$5.237.400.000 en 2026). Dejar escrita la razón y revisarla cada enero. Las demás
  obligaciones de la Ley 1581 aplican igual: son los pasos 10 y 13.
- **ISO 27001 o SOC 2.** Requisito de grandes cuentas. Decenas de millones y un año.
- **Prueba de penetración externa.** El aislamiento ya tiene dos capas y 49 casos de prueba.
- **Seguro de ciberriesgo.** El límite de responsabilidad del contrato cumple esa función primero.
- **La sección «Equipo» del panel.** `equipo:invitar` basta mientras sean dos personas.
- **Infraestructura propia.** Documento 6, A.5: cuando la factura supere el costo del tiempo.
- **Los festivos históricos de diez años** (`HD-05`). El cálculo por ley Emiliani ya coincide fecha
  por fecha con 2025, 2026 y 2027.

## La única fecha que hay que anotar en el calendario

**Antes de diciembre de 2027: cargar los festivos de 2028** en `database/data/festivos.php` y correr
`FestivosSeeder`. Hoy están 2025, 2026 y 2027, verificados a mano. Un calendario vencido descuadra
cada cálculo de vacaciones del sistema, y es el tipo de falla que no avisa.

## El camino corto

Seis a ocho semanas hasta el paso 29; el paso 30 dura el primer mes del primer cliente. **El camino
crítico no es el código** —alrededor de una semana con la landing— sino el abogado y la Cámara de
Comercio. Por eso las fases 2 y 3 arrancan la misma semana que la 1.

| Cuándo | Qué |
|---|---|
| Semana 1 | Pasos 01 a 03, y mandar a arrancar la sociedad y el abogado |
| Semanas 2-4 | La sociedad y el paquete legal avanzan solos; marca, soporte, campo de dinero y despliegue completo con correo real |
| Semana 5 | Llegan los textos legales, se pegan y el sistema exige su aceptación. Simulacro de restauración |
| Semanas 6-8 | Landing, demostración revisada, guion de puesta en marcha. Vender |

**Las tres decisiones que solo pueden tomar David y Angela, y que bloquean a otros:** el nombre
comercial (paso 02, bloquea dominio, logo, sociedad y marca), el precio (paso 03, bloquea el contrato
y la landing) y el SLA (paso 11, bloquea al abogado). Ninguna cuesta dinero.

## De dónde salen las cifras

- UVB 2026 ($12.110): Resolución 3488 de 2025 del Ministerio de Hacienda.
- Matrícula mercantil 2026: tablas del Decreto 045 replicadas por las cámaras de comercio.
  Confirmar en el simulador de la Cámara de Comercio de Bogotá (ccb.org.co).
- Registro de marca: Resolución 6167 de 2026 de la SIC. Las fuentes secundarias no coinciden entre
  sí; confirmar en sic.gov.co.
- Umbral del RNBD: 100.000 UVT, ~$5.237.400.000 en 2026.
- IVA del 19% sobre SaaS y licenciamiento de software. Confirmar con el contador.
- Honorarios de abogado: referencia del Conalbos (consulta escrita ≈ 1 salario mínimo); se pactan
  libremente. Honorarios de contador: no hay tarifa regulada.
- Salario mínimo 2026 ($1.750.905) y auxilio de transporte ($249.095): Decretos 1469 y 1470 de 2025.
- Railway, Resend, Postmark, SES, Wompi, ePayco y registradores de dominio: páginas de precios de
  cada proveedor. Confirmar antes de presupuestar; cambian sin aviso.

*Las fuentes con enlace están en la versión publicada del plan.*
