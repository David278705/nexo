<?php

/*
|--------------------------------------------------------------------------
| Constantes legales del régimen laboral colombiano
|--------------------------------------------------------------------------
|
| Ningún número legal se escribe suelto dentro de la lógica: todos viven
| aquí, con la norma que los sustenta y la fecha en que se verificó esa
| norma. Cuando una reforma cambie un valor, se cambia en este archivo y
| se actualiza su fecha de verificación, no se busca por todo el código.
|
| Estos valores producen números iguales para todos los clientes a la vez.
| Un error aquí no rompe una pantalla: liquida mal a una persona real.
|
| Fuentes: Código Sustantivo del Trabajo · Ley 2466 de 2025 ·
| Concepto MinTrabajo 08SE2026120300000006157 del 18 de febrero de 2026.
|
*/

return [

    /*
    | 1. Acumulación de vacaciones
    |
    | Quince días hábiles de vacaciones por cada año de servicio, que
    | equivalen a 1,25 días por mes trabajado.
    |
    | Norma: artículo 186 del Código Sustantivo del Trabajo.
    | Verificado: 2026-08-17.
    */
    'dias_vacaciones_por_mes' => 1.25,

    /*
    | 2. Periodo de prueba en contrato a término indefinido
    |
    | Dos meses contados desde la fecha de ingreso.
    |
    | Norma: artículo 78 del Código Sustantivo del Trabajo.
    | Verificado: 2026-08-17.
    */
    'periodo_prueba_indefinido_meses' => 2,

    /*
    | 3. Periodo de prueba en contrato a término fijo inferior a un año
    |
    | No puede exceder la quinta parte del término pactado. Se guarda el
    | divisor y no la fracción decimal para que el cálculo sea exacto:
    | duración pactada ÷ 5.
    |
    | Norma: artículo 78 del Código Sustantivo del Trabajo.
    | Verificado: 2026-08-17.
    */
    'periodo_prueba_fijo_divisor' => 5,

    /*
    | 4. Tope del periodo de prueba en contrato a término fijo
    |
    | La quinta parte del término pactado nunca puede superar dos meses,
    | que es el máximo legal absoluto del periodo de prueba.
    |
    | Norma: artículo 78 del Código Sustantivo del Trabajo.
    | Verificado: 2026-08-17.
    */
    'periodo_prueba_fijo_tope_meses' => 2,

    /*
    | 5. Preaviso de no prórroga del contrato a término fijo
    |
    | Treinta días calendario de anticipación al vencimiento. Si el
    | empleador guarda silencio, el contrato se entiende prorrogado.
    |
    | Norma: artículo 46 del Código Sustantivo del Trabajo, modificado
    | por la Ley 2466 de 2025.
    | Verificado: 2026-08-17.
    */
    'preaviso_dias' => 30,

    /*
    | 6. Tope de duración del contrato a término fijo
    |
    | Cuatro años. Alcanzado el tope, el contrato es indefinido por
    | ministerio de la ley; no es una opción del empleador.
    |
    | Norma: artículo 46 del Código Sustantivo del Trabajo, reformado
    | por la Ley 2466 de 2025.
    | Verificado: 2026-08-17.
    */
    'tope_termino_fijo_anios' => 4,

    /*
    | 7. Disfrute mínimo anual de vacaciones
    |
    | El trabajador debe disfrutar al menos seis días hábiles continuos
    | por año, que no son acumulables.
    |
    | Norma: artículo 190 del Código Sustantivo del Trabajo.
    | Verificado: 2026-08-17.
    */
    'disfrute_minimo_dias_habiles' => 6,

    /*
    | 8. Prórrogas cortas pactadas por escrito
    |
    | En contratos a término fijo inferiores a un año pueden pactarse
    | hasta cuatro prórrogas por un término inferior a un año; a partir
    | de la quinta, la renovación no puede ser inferior a un año.
    |
    | Norma: artículo 46 del Código Sustantivo del Trabajo, interpretado
    | por el Concepto MinTrabajo 08SE2026120300000006157 del 18 de
    | febrero de 2026.
    | Verificado: 2026-08-17.
    */
    'prorrogas_cortas_pactadas' => 4,

    /*
    | 9. Prórrogas cortas automáticas por silencio
    |
    | La prórroga automática opera tres veces por el mismo término; la
    | cuarta ya debe ser por un año. Es una prórroga menos que la
    | pactada por escrito, y por eso se cuentan por separado.
    |
    | Norma: artículo 46 del Código Sustantivo del Trabajo, interpretado
    | por el Concepto MinTrabajo 08SE2026120300000006157 del 18 de
    | febrero de 2026.
    | Verificado: 2026-08-17.
    */
    'prorrogas_cortas_automaticas' => 3,

    /*
    | 10. Entrada en vigencia del régimen del término fijo
    |
    | Los cuatro años del tope corren desde la celebración del contrato,
    | salvo en los contratos que ya estaban vigentes ese día: para ellos
    | corren desde esta fecha y no desde su firma original. Se venían
    | prorrogando bajo la norma anterior, son situaciones consolidadas y
    | la ley no tiene efecto retroactivo.
    |
    | Es una fecha y no un número de días a propósito: se compara contra
    | la fecha de inicio del contrato, nunca se suma a nada.
    |
    | Norma: Ley 2466 de 2025, entrada en vigencia. Su aplicación al tope
    | está en la sección C.2 de `docs/06-decisiones-pendientes.md`.
    | Verificado: 2026-08-18.
    */
    'vigencia_reforma_termino_fijo' => '2025-06-25',

];
