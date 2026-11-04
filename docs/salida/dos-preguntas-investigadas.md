# Dos preguntas investigadas

> Investigación del 2026-11-02, paso 14 del plan de salida al mercado. Se conserva aquí para el abogado y
> el asesor laboral.
>
> **Estado al 2026-11-03.** La primera pregunta ya la decidió David y está construida (`PROD-15`): ver
> `docs/06-decisiones-pendientes.md`, A.4. La tabla de abajo es la investigación que la sustentó; donde
> dice «diez años y después eliminar» para suscripciones y pagos, la decisión final fue **conservarlos sin
> plazo**. La segunda sigue esperando la validación del asesor laboral, y el hallazgo que traía —el otrosí
> de obra o labor a indefinido no se podía registrar— ya está resuelto (`PROD-16`).

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
`CambioAIndefinido` solo admitía término fijo. **Resuelto el 2026-11-03 (`PROD-16`).**

**Límite:** se leyó el texto oficial del Decreto 1377, la Ley 2466 y la T-1097 de 2012. La Ley 962, el
Concepto 95880 y la consecuencia del artículo 64 vienen de fuentes secundarias. La frase sobre
«diferencias esenciales» que se atribuye a la T-1097 no está en esa sentencia: no citarla.

**Para validar.** Al abogado: «¿Está de acuerdo con esta tabla de conservación y sus plazos, y cómo
debe quedar en el contrato y en el acuerdo de tratamiento?». Al asesor laboral: «¿Existe
jurisprudencia de la Corte Suprema que admita un nuevo periodo de prueba en un contrato sucesivo
con labor distinta, o mantenemos la recomendación de no pactarlo?».
