<?php

/*
|--------------------------------------------------------------------------
| Los umbrales del motor de alertas
|--------------------------------------------------------------------------
|
| Aquí no hay ni un número legal: esos viven en `reglas_laborales.php` con
| su norma citada. Lo que vive aquí es lo otro que el documento 2, sección
| 8.1, manda sacar del código: «los umbrales que decide Angela van en el
| mismo lugar, para poder ajustarlos sin desplegar código».
|
| Son decisiones de criterio, no de ley. Cambiarlas es editar este archivo:
| ni la regla de disparo, ni el texto del correo, ni el proceso diario
| cambian de código.
|
| Hay uno solo, y no es que falte el otro: el «tiempo para considerar
| prolongado un contrato de obra o labor» que esa sección menciona **no
| existe**. La decisión D.2 lo eliminó en agosto de 2026 cambiándole la forma
| a la alerta —un listado de todos los activos cada dos meses— porque ningún
| criterio legal dice a partir de cuándo una obra es prolongada.
|
*/

return [

    /*
    | 1. Anticipación de la alerta de cercanía al tope de cuatro años
    |
    | Un solo aviso, tres meses antes del día en que el vínculo completaría
    | los cuatro años a término fijo. No son dos: los «35 días» que se
    | mencionaron al principio no son una alerta nueva, son las dos del
    | preaviso que ya existen ajustando su texto.
    |
    | Decidido por Angela el 22 de agosto de 2026. Está escrito en la
    | decisión D.3 de `docs/06-decisiones-pendientes.md`, que quedó resuelta.
    */
    'cercania_al_tope_meses' => 3,

];
