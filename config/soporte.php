<?php

/*
|--------------------------------------------------------------------------
| El canal de soporte que ve el cliente
|--------------------------------------------------------------------------
|
| El canal de soporte del cliente (`PROD-12`). El contrato va a
| prometer tiempos de respuesta, y el cliente tiene que saber a dónde
| escribir para que ese reloj empiece a correr. Hasta ahora no había ningún
| sitio dentro de la aplicación que lo dijera.
|
| Los tres datos viven en variables de entorno y no en el código porque son
| de operación: cambian el día que cambie quien atiende, sin desplegar.
| Mientras `SOPORTE_CORREO` esté vacío, el bloque no se enseña —un canal sin
| dirección es peor que ninguno— y `despliegue:verificar` lo avisa.
|
*/

return [

    // La dirección a la que el cliente escribe. Del dominio propio, igual que
    // el remitente de las alertas.
    'correo' => env('SOPORTE_CORREO', ''),

    // Opcional. Diez dígitos, como un celular colombiano; se enseña tal cual.
    'whatsapp' => env('SOPORTE_WHATSAPP', ''),

    // Cuándo se responde. Tiene que coincidir con lo que diga el SLA del
    // contrato (`docs/salida/opciones-de-sla.md`): si el contrato promete otra cosa, manda el
    // contrato y este texto miente.
    'horario' => env('SOPORTE_HORARIO', 'Lunes a viernes, de 8:00 a. m. a 5:00 p. m.'),

];
