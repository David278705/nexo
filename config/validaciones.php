<?php

/*
|--------------------------------------------------------------------------
| Estado de las validaciones que dependen de una decisión abierta
|--------------------------------------------------------------------------
|
| Hay reglas legales que el sistema sabe calcular pero de las que aún no
| está decidido si deben impedir el guardado o solo advertir con fuerza.
| Es la pregunta B.3 de `docs/06-decisiones-pendientes.md`, y no es una
| duda de código: si el sistema bloquea algo que en realidad es legal, el
| cliente no puede operar; si no bloquea nada, termina almacenando
| situaciones ilegales y pierde su valor preventivo.
|
| Por eso el estado de cada una vive aquí y no escrito dentro de la
| lógica: cuando el asesor laboral confirme la aplicación práctica, se
| cambia una palabra en este archivo y ni la validación ni la pantalla
| cambian de código.
|
| Los dos valores posibles son los del enumerado
| `App\Contratacion\EstadoDeValidacion`:
|
| - `bloquea`  → no se puede guardar, y el mensaje sale en rojo.
| - `advierte` → se puede guardar, y el mensaje sale en ámbar.
|
| Son valores globales y no parámetros de empresa a propósito. El capítulo
| 4 del documento 1 lista los parámetros que el cliente configura y estos
| no están: dejar que una empresa desactive un bloqueo legal es
| exactamente lo que el bloqueo existe para evitar.
|
*/

return [

    /*
    | Renovaciones del contrato a término fijo (documento 1, 5.5)
    |
    | 1. Tope de cuatro años.
    |
    | Bloquea. Aquí la ley no deja alternativa: pasado el tope el contrato
    | ES indefinido por ministerio de la ley, no puede ser otra cosa, así
    | que registrar la prórroga sería registrar algo que no existe. El
    | sistema ofrece en ese mismo momento la acción «Cambiar a término
    | indefinido», que es la salida correcta y deja al cliente resuelto.
    |
    | Norma: artículo 46 del CST, reformado por la Ley 2466 de 2025.
    | Decisión: documento 6, B.3, como allí se sugiere.
    */
    'renovacion_tope_termino_fijo' => 'bloquea',

    /*
    | 2. Prórroga mínima de un año después del límite de prórrogas cortas.
    |
    | Advierte con fuerza y permite guardar. La regla está clara, pero su
    | aplicación práctica es lo que el documento 6 pide confirmar con el
    | asesor laboral antes de impedir un guardado (B.1), y aquí sí puede
    | haber una prórroga ya firmada en el mundo real que la empresa tenga
    | que registrar. Confirmada la regla, este valor pasa a `bloquea` y
    | tanto el mensaje como el color se ajustan solos.
    |
    | Norma: artículo 46 del CST, interpretado por el Concepto MinTrabajo
    | 08SE2026120300000006157 del 18 de febrero de 2026.
    | Decisión: documento 6, B.1 y B.3.
    */
    // PROD-33, decidido por Angela el 2026-11-11: bloquea. Qué prórroga corta se
    // permite lo dice `HistorialDeContrato::limiteParaUnaProrrogaCorta()`: con
    // tres prórrogas, todas pactadas y en un contrato creado en Nexo, una cuarta
    // pactada corta sí cabe; en los demás casos, desde la cuarta, mínimo un año.
    'renovacion_prorroga_minima' => 'bloquea',

];
