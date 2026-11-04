# Manual de consultas y reclamos de datos personales

> Pasos 4 y 11 del plan de salida al mercado. Borrador del 2026-11-03 para revisión del abogado.
>
> **Es obligatorio.** La Ley 1581 de 2012, artículo 18, literal f, le exige al encargado del tratamiento
> «adoptar un manual interno de políticas y procedimientos para garantizar el adecuado cumplimiento de la
> presente ley y, en especial, para la atención de consultas y reclamos por parte de los Titulares».

## 1. Los dos papeles del proveedor

El proveedor trata datos en dos calidades distintas, y el procedimiento cambia según cuál:

| Datos | Calidad del proveedor | Quién decide la respuesta |
|---|---|---|
| Los empleados de cada empresa cliente | **Encargado** | La empresa cliente, que es la responsable. El proveedor la apoya y ejecuta |
| Los usuarios administradores de los clientes, sus contactos y los prospectos | **Responsable** | El proveedor |

## 2. El canal

- **Un correo propio, del dominio de la empresa**, que se publica en la política de tratamiento.
  Puede ser el mismo de soporte (`SOPORTE_CORREO`) o uno aparte. *Por definir por David.*
- Responde: *por definir* (recomendación: Angela, con copia a David).
- La ley exige que el medio permita **mantener prueba** de la solicitud (artículo 14). Un correo lo
  permite; un WhatsApp personal no.

## 3. Registro de cada solicitud

Toda solicitud se anota el mismo día en que llega, en una hoja con estas columnas. Es la prueba de que se
cumplieron los plazos:

| Columna | Ejemplo |
|---|---|
| Fecha de recibo | 3/11/2026 |
| Titular y documento | Ana Gómez, C.C. 52.998.877 |
| Empresa cliente, si es empleado | Comercializadora del Valle |
| Tipo | Consulta · Reclamo de corrección · Reclamo de actualización · Reclamo de supresión · Revocatoria · Queja por incumplimiento |
| Fecha límite | Calculada con los plazos del punto 4, en días hábiles |
| Traslado a la empresa cliente | Fecha y a quién |
| Respuesta | Fecha y resumen |

## 4. Los plazos

Son los de la ley, contados en **días hábiles**:

| Trámite | Plazo | Si no alcanza |
|---|---|---|
| **Consulta** (qué datos tienen de mí) | 10 días hábiles desde el recibo (artículo 14) | Se informa el motivo y la nueva fecha, que no puede pasar de 5 días hábiles más |
| **Reclamo** (corregir, actualizar, suprimir, o queja) | 15 días hábiles desde el día siguiente al recibo (artículo 15) | Se informa el motivo y la nueva fecha, que no puede pasar de 8 días hábiles más |
| Reclamo incompleto | Se le pide al titular que lo complete dentro de los **5 días** siguientes al recibo | Si en **2 meses** no lo completa, se entiende que desistió |
| Si quien recibe no es el competente | Traslado en **2 días hábiles**, informando al titular | — |
| Leyenda «reclamo en trámite» | Dentro de **2 días hábiles** del reclamo completo, hasta que se decida | — |
| Actualizar lo que reporte la empresa cliente | **5 días hábiles** desde su recibo (artículo 18, literal d) | — |

## 5. El procedimiento

### Si la solicitud es de un empleado de un cliente

1. **Registrarla** el mismo día.
2. **Trasladarla a la empresa cliente** dentro de **2 días hábiles**, que es la responsable, e informarle al
   titular que se trasladó y a quién.
3. **Ejecutar lo que la empresa decida.** Una consulta se responde con lo que hay en el sistema: la ficha
   del empleado y la exportación del listado sirven para reunirlo. Una corrección la hace la empresa
   desde su cuenta, o el proveedor si la empresa lo pide por escrito.
4. **Una supresión merece cuidado.** Mientras el empleador tenga el deber de conservar —tres años de
   prescripción laboral (artículo 488 del CST), veinte años de SG-SST (Decreto 1072 de 2015)— la
   supresión no procede, porque el titular tiene un deber legal de permanecer en la base de datos
   (Decreto 1377, artículo 11). Esto lo decide la empresa cliente con su asesor, no el proveedor.

### Si la solicitud es de un usuario, un contacto o un prospecto

El proveedor la resuelve directamente dentro de los plazos del punto 4. Una supresión de un usuario
administrador se hace **desactivándolo**: el sistema no borra físicamente (regla 4 del proyecto) y la
auditoría conserva quién hizo qué mientras el cliente esté activo.

## 6. Lo que el sistema todavía no permite

**La leyenda «reclamo en trámite».** La ley obliga al encargado a registrarla en la base de datos
(artículo 18, literal g), y hoy el sistema no tiene dónde. Es una tarea de código pequeña —una marca en la
persona, con su motivo, visible en la ficha— y está anotada en el backlog como siguiente tarea de código.
Mientras no exista, queda solo en la hoja de registro del punto 3.

## 7. Incidentes de seguridad

No son consultas ni reclamos, pero el mismo artículo 18 (literal k) obliga al encargado a informar a la
SIC las violaciones a los códigos de seguridad. El procedimiento está en
`docs/07-plan-de-incidentes.md`, sección 3, y el plazo para avisarle al cliente lo fija el acuerdo de
tratamiento (ver `docs/salida/encargo-al-abogado.md`, punto 5).

## 8. Revisión

Este manual se revisa cada año, o antes si cambia la ley o el sistema. La primera revisión es la del
abogado, junto con los cuatro documentos legales.
