# Plan de salida al mercado

> **Esta es la única lista.** Todo lo que falta para vender Nexo está aquí, en el orden en que conviene
> hacerlo, y en ningún otro sitio. Una decisión nueva, un hallazgo o una tarea nueva entra a esta lista en
> su lugar, no a una nota suelta. `BACKLOG.md` sigue teniendo el detalle técnico de las tareas de código, y
> `ESTADO.md` solo dice en qué número de esta lista vamos.
>
> Cómo se lee cada paso: **quién** lo hace (**Tú**, **Yo** o **Los dos**), **de qué depende**, y cuánto
> cuesta si cuesta algo. Versión para compartir con el abogado y el contador:
> https://claude.ai/artifact/CDMcrjydi9TpAp6LFhSU2g
>
> Actualizada el 2026-11-03.

## Lo que falta, en orden

### Primero: cuatro decisiones tuyas

No cuestan nada y destraban casi todo lo demás. Cada una ya tiene una propuesta escrita.

1. **El nombre comercial.** — **Tú.** Bloquea el logo, la landing, el dominio y la marca (pasos 15, 16,
   17 y 27). Si quieres, yo busco antes los antecedentes en el registro de marcas de la SIC.
2. **Los precios.** — **Tú y Angela.** Propuesta: Micro $250.000, Pequeña $450.000 y Mediana $750.000 al
   mes, más IVA, en `docs/salida/propuesta-de-precios.md`. Bloquea al abogado (11) y la landing (16).
3. **El SLA.** — **Tú y Angela.** Propuesta: 99 % de disponibilidad y los tiempos de respuesta del
   documento 4, en `docs/salida/opciones-de-sla.md`. Bloquea al abogado (11).
4. **El correo de soporte y quién responde.** — **Tú.** El mismo correo sirve para las consultas de datos
   personales. Bloquea la producción (20).

### Segundo: preguntas cortas del producto

Respóndelas con sí o no. Entre paréntesis, lo que recomiendo.

5. **¿La invitación al cliente sale al terminar la carga de sus datos, y no al crear la empresa?** (Sí.)
   Hoy el cliente recibe acceso a un sistema vacío, y los documentos 1 y 4 dicen que no debe pasar.
6. **¿Quito la opción de WhatsApp del soporte?** (Sí.) El documento 4 pide un solo canal, el correo, porque
   deja registro.
7. **Seis más, más pequeñas.**
   - ¿Agrego la fecha estimada de finalización de la obra o labor a la plantilla de Excel de la carga
     inicial? (Sí.)
   - ¿El aviso de reingreso cambia su texto según el tipo de contrato? Hoy promete alertas de periodo de
     prueba que una obra o labor sin fecha estimada no recibe. (Sí.)
   - ¿Se puede corregir la fecha estimada de una obra o labor después de registrarla? (Sí, si te pasa.)
   - ¿El proveedor que entra a `/` va directo a su panel, en vez de recibir un error 403? (Sí.)
   - ¿Hace falta una plantilla de Excel para cargar terminaciones? Ningún documento la pide (`HD-12`). (No.)
   - ¿Borro el respaldo de git `stash@{0}`, que es un trabajo viejo a medias que ya no sirve? (Sí.)

### Tercero: los trámites que tardan

Arráncalos todos la misma semana: corren en paralelo y el camino crítico son ellos, no el código.

8. **Constituir la SAS** en la Cámara de Comercio. — **Tú.** ~$200.000 · 1 a 2 semanas.
9. **RUT, responsabilidad de IVA, régimen tributario y cuenta bancaria de la sociedad.** — **Tú**, con el
   contador. $0. El SaaS está gravado con IVA del 19 %.
10. **Contador y facturación electrónica ante la DIAN.** — **Tú.** Contador $300.000 a $900.000 al mes; la
    facturación, gratis con la solución de la DIAN.
11. **Contratar al abogado** y entregarle `docs/salida/encargo-al-abogado.md` junto con el precio y el SLA
    decididos. Le pide los cuatro textos legales, el acuerdo entre socios, y que valide lo que el sistema
    conserva cuando un cliente se va. — **Tú.** $3.000.000 a $8.500.000 · 2 a 4 semanas. Depende de 2 y 3.
    Al asesor laboral, aparte: la pregunta del periodo de prueba de `docs/salida/dos-preguntas-investigadas.md`.
    A Angela, las dos que siguen abiertas: el fondo de cesantías (B.2 del documento 6) y la norma que
    sustenta que la incapacidad interrumpe las vacaciones (punto 21 del documento 6, `HD-04`).

### Cuarto: código que hago yo mientras los trámites avanzan

12. **Marcar «reclamo en trámite»** en un empleado (`PROD-14`). — **Yo.** Lo exige la Ley 1581. No depende de
    nada.
13. **El periodo de prueba después de un cambio de modalidad** (`PROD-19`). — **Yo.** Hoy, si un término fijo
    o una obra o labor pasa a indefinido en sus primeros meses, el sistema recalcula el periodo de prueba
    como si el contrato hubiera nacido indefinido, y puede mandar alertas de un periodo que no existe. No
    depende de nada.
14. **Lo que respondas en 5, 6 y 7.** — **Yo.**
15. **Logo y favicon.** — **Yo** (o un diseñador). Depende de 1.
16. **La landing** en el dominio raíz, con los precios y los documentos legales enlazados. — **Yo.** Depende
    de 1 y 2.

### Quinto: poner el sistema en producción

Yo no entro a tu cuenta de Railway. Te dejo las variables listas y leo contigo el log si lo copias aquí.
La guía paso a paso es `docs/08-despliegue.md`.

17. **Comprar el dominio.** — **Tú.** $60.000 a $130.000 al año. Depende de 1.
18. **Desplegar en Railway**: PostgreSQL y los tres servicios. — **Tú.** USD 25 a 45 al mes.
19. **Sentry y un monitor de disponibilidad** externo (UptimeRobot o similar) que consulte `/up`. — **Tú.**
    $0. Sin el monitor no se puede demostrar que se cumplió el SLA.
20. **El correo**: proveedor transaccional, SPF, DKIM y DMARC, `alertas:prueba` cayendo en bandeja de
    entrada, y `SOPORTE_CORREO` puesto. — **Tú.** $0 a USD 15 al mes. Depende de 4 y 17.
21. **Las dos primeras cuentas** con `equipo:invitar`, segundo factor activado, borrar `DB_URL_ADMIN`, y
    comprobar dos cosas que solo se ven allá: que la imagen compiló y que el registro de accesos guarda las
    IPs reales y no la del proxy de Railway. — **Tú.**
22. **El simulacro de restauración de la base**, con la evidencia en `bitacora/`. — **Tú.** 2 horas.

### Sexto: cerrar lo legal

23. **Pegar los cuatro textos del abogado** en `resources/legal/` y pasarlos a vigente. — **Yo.** 1 hora.
    Depende de 11.
24. **Crear los planes en el panel**, en Suscripciones. — **Tú.** Depende de 2.

### Séptimo: vender

25. **Revisar la demostración y una cuenta de prueba** con ojos de cliente, y de paso las verificaciones en
    navegador que quedaron pendientes desde `HD-04`. — **Los dos.** Yo recorro las pantallas; el criterio es
    tuyo y de Angela.
26. **El primer cliente, como piloto.** — **Los dos.** Contrato y acuerdo firmados; cobro por transferencia
    o PSE con factura electrónica; la reunión con `docs/salida/guion-de-puesta-en-marcha.md`, anotando el
    tiempo real; yo valido sus plantillas con `carga:simular` antes de cargar; a la mañana siguiente,
    comprobar que sus alertas llegaron. Medir las horas de soporte del primer mes: confirman o corrigen el
    precio.
27. **Registrar la marca ante la SIC.** — **Tú.** $1.179.000 a $1.347.500. Puede ir después de la primera
    venta. Depende de 1.

### Cada año

- **Antes de diciembre de 2027: cargar los festivos de 2028** en `database/data/festivos.php`. — **Yo**, y
  tú verificas las fechas. Un calendario vencido descuadra cada cálculo de vacaciones sin avisar.

## Lo que ya está hecho

| Qué | Cuándo | Dónde |
|---|---|---|
| Revisión dirigida del código, los costos y este plan | 2026-11-02 | Este documento |
| La obra o labor maneja periodo de prueba sobre una fecha estimada | 2026-11-02 | `OL-01` |
| Las dependencias instalan en PHP 8.2 y sin avisos de seguridad | 2026-11-03 | `PROD-13` |
| Los cinco campos de dinero se escriben como en Colombia | 2026-11-03 | `PROD-11` |
| El cliente ve el canal de soporte en la barra lateral | 2026-11-03 | `PROD-12` |
| Encargo al abogado, opciones de SLA, manual de consultas y reclamos, propuesta de precios y guion de puesta en marcha | 2026-11-03 | `docs/salida/`, `DOC-01` |
| Escalera de mora: avisos a los 5 y 10 días, suspensión a los 15 | 2026-11-03 | `PROD-18` |
| Al irse un cliente se conservan los pagos, las suscripciones, la aceptación del contrato, el registro de alertas sin nombres y las constancias de entrega y borrado | 2026-11-03 | `PROD-15` |
| La obra o labor se puede cambiar a término indefinido | 2026-11-03 | `PROD-16` |
| Cada prospecto recibe su propia cuenta de prueba con datos | 2026-11-03 | `PROD-17` |
| Lo anterior al plan: despliegue, RLS en producción, documentos legales con versión, salida del cliente | 2026-10-01 | `PROD-01` a `PROD-10` |

## Anexos

### Lo que cuesta

Valores de 2026. Las tarifas oficiales se recalculan cada enero sobre la UVB ($12.110 en 2026) o la
UVT. Las marcadas **(por confirmar)** hay que verificarlas antes de comprometer presupuesto.

#### Un solo pago

| Concepto | Pesos |
|---|---|
| Constitución de la SAS (matrícula por activos bajos + inscripción + RUES + libros + certificado) | ~200.000 |
| Paquete legal con abogado, los cuatro documentos **(por confirmar)** | 2.000.000 – 6.000.000 |
| Acuerdo entre socios **(por confirmar)** | 1.000.000 – 2.500.000 |
| Logo y favicon | 0 – 1.500.000 |
| Registro de marca, una clase en línea, tarifa de microempresa **(por confirmar)** | 1.179.000 – 1.347.500 |
| **Total para arrancar** | **4.400.000 – 11.500.000** |

#### Cada mes

| Concepto | Pesos |
|---|---|
| Contador | 300.000 – 900.000 |
| Railway (PostgreSQL + tres servicios; Pro USD 20 con crédito, más consumo) | ~100.000 – 180.000 |
| Correo transaccional (Resend gratis hasta 3.000; Postmark USD 15 por 10.000) | 0 – 60.000 |
| Sentry (plan gratuito) | 0 |
| Facturación electrónica (gratis con la DIAN) | 0 – 120.000 |
| Dominio, prorrateado | ~10.000 |
| **Total mensual** | **410.000 – 1.270.000** |

#### De ahí sale el precio, y no al revés

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
solo lo da el primer cliente (paso 26).

**El correo es el único costo que crece con los clientes**, porque cada alerta va a cada administrador
que recibe copia. Es la justificación técnica de cobrar por usuario además de por empleado.

### Lo que NO hay que hacer antes de vender

Nada de esto se lo pide un cliente PYME colombiano a un proveedor de tres clientes.

- **Pasarela de pagos con cobro recurrente.** Se cobra por transferencia o PSE con factura electrónica (paso 26).
- **Registro Nacional de Bases de Datos de la SIC.** Solo obliga a sociedades con activos superiores
  a 100.000 UVT (~$5.237.400.000 en 2026). Dejar escrita la razón y revisarla cada enero. Las demás
  obligaciones de la Ley 1581 aplican igual: el abogado (paso 11) y el manual de consultas y reclamos.
- **ISO 27001 o SOC 2.** Requisito de grandes cuentas. Decenas de millones y un año.
- **Prueba de penetración externa.** El aislamiento ya tiene dos capas y 49 casos de prueba.
- **Seguro de ciberriesgo.** El límite de responsabilidad del contrato cumple esa función primero.
- **La sección «Equipo» del panel.** `equipo:invitar` basta mientras sean dos personas.
- **Infraestructura propia.** Documento 6, A.5: cuando la factura supere el costo del tiempo.
- **Los festivos históricos de diez años** (`HD-05`). El cálculo por ley Emiliani ya coincide fecha
  por fecha con 2025, 2026 y 2027.

### De dónde salen las cifras

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
