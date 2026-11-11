# Plan de salida al mercado

> **Esta es la única lista.** Todo lo que falta para vender Nexo está aquí, en orden, y **todo es de
> David y Angela**: trámites, contratos, cuentas y pruebas con ojos propios. El código está terminado; lo
> único técnico que falta es subirlo a Railway (paso 7). Una decisión, un hallazgo o una tarea nueva
> entra a esta lista en su lugar, no a una nota suelta. `ESTADO.md` solo dice en qué paso vamos.
>
> Versión para compartir con el abogado y el contador: https://claude.ai/artifact/CDMcrjydi9TpAp6LFhSU2g
>
> Actualizada el 2026-11-08.

## Lo que falta, en orden

### Primero: los trámites que tardan

Arráncalos la misma semana. Corren en paralelo y son el camino crítico.

1. **Contratar al abogado** y entregarle `docs/salida/encargo-al-abogado.md`. Ya lleva el precio, el SLA,
   la escalera de mora y lo que el sistema conserva cuando un cliente se va. Le pide cuatro textos
   —contrato de servicio, acuerdo de tratamiento de datos, política de tratamiento y autorización para los
   empleados del cliente— y el acuerdo entre socios. $3.000.000 a $8.500.000 · 2 a 4 semanas.
2. **Al asesor laboral, y a Angela**, las tres preguntas que siguen abiertas: el periodo de prueba al pasar
   de obra o labor a indefinido con labor distinta (`docs/salida/dos-preguntas-investigadas.md`), el fondo
   de cesantías (B.2 del documento 6) y la norma que sustenta que la incapacidad interrumpe las vacaciones
   (punto 21 del documento 6). $0 si las responde Angela.
3. **Constituir la SAS** en la Cámara de Comercio. ~$200.000 · 1 a 2 semanas.
4. **RUT, responsabilidad de IVA, régimen tributario y cuenta bancaria de la sociedad**, con el contador.
   $0 el trámite. El SaaS está gravado con IVA del 19 %.
5. **Contador y facturación electrónica ante la DIAN.** Contador $300.000 a $900.000 al mes; la
   facturación, gratis con la solución de la DIAN.

### Segundo: poner el sistema en producción

La guía, paso a paso y con todas las variables, es `docs/08-despliegue.md`.

6. **Comprar el dominio** de Nexo. $60.000 a $130.000 al año. La página pública y la aplicación viven en
   el mismo dominio.
7. **Desplegar en Railway** — **hecho el 2026-11-10**, en el proyecto `nexo`, con la dirección
   provisional `https://web-production-eac14903.up.railway.app`. Lo que sigue de este paso es poner el
   dominio propio en el servicio web y en `APP_URL`. Quedaron PostgreSQL y los tres servicios —web, worker y scheduler—. Al arrancar, el
   sistema crea su rol de base de datos, revisa la configuración, migra y carga solo los catálogos, los
   festivos y los tres planes. USD 25 a 45 al mes.
8. **El correo.** Desde el 2026-11-10 el sistema ya envía por Resend, con su remitente de prueba
   (`onboarding@resend.dev`) y solo a los correos de `MAIL_DESTINATARIOS_PERMITIDOS`. Falta, con el dominio
   del paso 6:
   - **Verificar el dominio en Resend** y poner SPF, DKIM y DMARC en el DNS. Mientras no, Resend solo
     entrega al correo dueño de la cuenta de Resend: a Angela no le llega nada.
   - **Cambiar `MAIL_FROM_ADDRESS`** a una dirección del dominio, por ejemplo `alertas@` del dominio.
   - **Pendiente: el correo de soporte.** `SOPORTE_CORREO` tiene hoy `soporte@nexo.invalid`, que es de
     relleno y no recibe nada. Se ve en el menú de los clientes y en la página pública. Responde Angela,
     con copia a David.
   - **Al empezar a vender, vaciar `MAIL_DESTINATARIOS_PERMITIDOS`**: mientras tenga algo, ningún correo
     sale a los clientes.
9. **Sentry y un monitor de disponibilidad** externo (UptimeRobot o similar) que consulte `/up`. $0. Sin
   el monitor no se puede demostrar que se cumplió el SLA del 99 %.
10. **Las dos primeras cuentas** con `php artisan equipo:invitar`, el segundo factor activado, y después:
    borrar `DB_URL_ADMIN`, comprobar que el registro de accesos guarda las IPs reales y que
    `php artisan despliegue:verificar` sale todo en ✔.
11. **El simulacro de restauración de la base** en Railway, con la evidencia en `bitacora/`. 2 horas.

### Tercero: cerrar lo legal

12. **Cuando el abogado entregue los textos**, pegarlos en `resources/legal/`, uno por archivo: cada uno
    reemplaza el aviso de borrador, se cambia `estado: borrador` por `estado: vigente` y se sube la
    `version`. Desplegar. Desde ese momento cada empresa acepta los textos al primer ingreso, con la
    constancia de quién, cuándo, desde dónde y la huella del texto exacto.

### Cuarto: vender

13. **Revisar la demostración y una cuenta de prueba** con ojos de cliente, y de paso las verificaciones
    en navegador pendientes desde `HD-04`. Una cuenta de prueba se genera en Panel → Demostración.
14. **El primer cliente, como piloto.** Contrato y acuerdo firmados; cobro por transferencia o PSE con
    factura electrónica; la reunión con `docs/salida/guion-de-puesta-en-marcha.md`, anotando el tiempo
    real; validar sus plantillas en Panel → Puesta en marcha antes de cargar; enviarle la invitación desde
    la ficha cuando su carga esté lista; y a la mañana siguiente, comprobar que sus alertas llegaron. Medir
    las horas de soporte del primer mes: confirman o corrigen el precio.
15. **Registrar la marca Nexo ante la SIC.** $1.179.000 a $1.347.500. Puede ir después de la primera venta.

### Cada año

- **Antes de diciembre de 2028: los festivos de 2029** en `database/data/festivos.php`, verificados fecha
  por fecha, y desplegar. El arranque los carga solo.
- **Si la Corte Constitucional tumba el artículo 6 de la Ley 2578 de 2026**, el festivo de la Virgen de
  Chiquinquirá se queda el 9 de julio en vez de trasladarse al lunes: hay que corregir esas tres fechas en
  el mismo archivo.

## Lo que ya está hecho

| Qué | Cuándo | Tarea |
|---|---|---|
| El arranque carga los catálogos, los festivos y los planes en una base nueva | 2026-11-08 | `PROD-20`, `PROD-24` |
| Los tres planes de la propuesta: Micro, Pequeña y Mediana | 2026-11-08 | `PROD-24` |
| El festivo de la Ley 2578 de 2026 y el calendario de 2028 | 2026-11-08 | `PROD-28` |
| El periodo de prueba se calcula con el tramo inicial del contrato | 2026-11-08 | `PROD-19` |
| La leyenda «reclamo en trámite» de la Ley 1581 | 2026-11-08 | `PROD-14` |
| La fecha estimada de obra o labor se corrige y se carga por Excel | 2026-11-08 | `PROD-23` |
| La invitación se envía desde la ficha cuando la carga está lista | 2026-11-08 | `PROD-22` |
| El proveedor va a su panel desde `/`; el aviso de reingreso según el contrato; soporte solo por correo | 2026-11-08 | `PROD-21` |
| La aceptación legal guarda la huella del texto exacto | 2026-11-08 | `PROD-25` |
| El logo y el favicon de Nexo | 2026-11-08 | `PROD-26` |
| La página pública en la raíz del dominio, con precios y documentos legales | 2026-11-08 | `PROD-27` |
| Escalera de mora, conservación al irse un cliente, obra o labor a indefinido, cuenta de prueba propia | 2026-11-03 | `PROD-15` a `PROD-18` |
| Encargo al abogado, SLA, manual de consultas y reclamos, precios y guion de puesta en marcha | 2026-11-03 | `DOC-01` |
| Dependencias sin avisos de seguridad, campos de dinero, canal de soporte | 2026-11-03 | `PROD-11` a `PROD-13` |
| Obra o labor con fecha estimada de finalización | 2026-11-02 | `OL-01` |
| Despliegue, RLS en producción, documentos legales con versión, salida del cliente | 2026-10-01 | `PROD-01` a `PROD-10` |

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
solo lo da el primer cliente (paso 14).

**El correo es el único costo que crece con los clientes**, porque cada alerta va a cada administrador
que recibe copia. Es la justificación técnica de cobrar por usuario además de por empleado.

### Lo que NO hay que hacer antes de vender

Nada de esto se lo pide un cliente PYME colombiano a un proveedor de tres clientes.

- **Pasarela de pagos con cobro recurrente.** Se cobra por transferencia o PSE con factura electrónica (paso 14).
- **Registro Nacional de Bases de Datos de la SIC.** Solo obliga a sociedades con activos superiores
  a 100.000 UVT (~$5.237.400.000 en 2026). Dejar escrita la razón y revisarla cada enero. Las demás
  obligaciones de la Ley 1581 aplican igual: el abogado (paso 1) y el manual de consultas y reclamos.
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
