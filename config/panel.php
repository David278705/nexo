<?php

/*
|--------------------------------------------------------------------------
| Los umbrales del panel del proveedor
|--------------------------------------------------------------------------
|
| El listado de empresas del capítulo 4 del documento 4 pide un «indicador
| de salud de uso en tres estados: activa, con uso bajo, sin uso». Los tres
| estados están escritos en el documento; **a cuántos días empieza cada uno
| no lo está en ninguna parte**, y no es una regla legal que se pueda
| consultar: es una decisión comercial sobre cuándo un cliente empieza a
| preocupar.
|
| Por eso los números viven aquí y no dentro de la lógica, igual que los
| estados de `validaciones.php`: son una propuesta, están anotados en
| `ESTADO.md` bajo «Decisiones para David», y confirmarlos o cambiarlos es
| editar este archivo y nada más. Ni la consulta, ni el filtro, ni la
| pantalla cambian de código.
|
| La propuesta y su porqué:
|
| - **Siete días para «activa».** Una empresa que entra al menos una vez por
|   semana está operando: la nómina se mueve con esa frecuencia.
| - **Treinta días para «sin uso».** Un mes entero sin que nadie entre es un
|   cliente que dejó de usar el producto, no uno que estuvo ocupado.
| - Entre uno y otro, «uso bajo», que es la franja en la que todavía sirve
|   llamar. El capítulo 16 lo dice: la caída en la frecuencia de entrada es
|   la señal más temprana de cancelación.
|
| La fecha que se mide es la del último inicio de sesión de cualquier
| usuario de la empresa, que es un hecho registrado en `accesos` y no una
| estimación.
|
*/

return [

    'salud_de_uso' => [

        // Hasta cuántos días desde el último ingreso la empresa se considera
        // activa.
        'dias_de_actividad' => 7,

        // A partir de cuántos días sin que entre nadie se considera sin uso.
        // Nunca haber entrado cuenta también como sin uso.
        'dias_sin_uso' => 30,

    ],

];
