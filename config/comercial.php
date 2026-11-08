<?php

/*
|--------------------------------------------------------------------------
| Lo comercial que enseña la página de bienvenida
|--------------------------------------------------------------------------
|
| La página pública de Nexo (`PROD-27`) lee los planes de la tabla `planes`,
| que se editan en el panel. Lo que no es un plan vive aquí: el cargo único de
| implementación por tamaño y el precio del usuario adicional. Son los números
| de `docs/salida/propuesta-de-precios.md`, aceptados por David el 2026-11-08.
|
| Todos los valores son en pesos colombianos, mensuales salvo la
| implementación, y **sin IVA**: la página lo dice.
|
*/

return [

    // El cargo único de la puesta en marcha, por el nombre del plan.
    'implementacion' => [
        'Micro' => 400000,
        'Pequeña' => 800000,
        'Mediana' => 1500000,
    ],

    // Cada usuario administrador por encima de los que incluye el plan.
    'usuario_adicional' => 40000,

    // A dónde escribe quien quiere una demostración. Si no se configura, el de
    // soporte: un solo correo atiende las dos cosas mientras el equipo sea de dos.
    'correo' => env('COMERCIAL_CORREO', env('SOPORTE_CORREO', '')),

];
