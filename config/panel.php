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

    /*
    |----------------------------------------------------------------------
    | El cobro y la escalera de mora
    |----------------------------------------------------------------------
    |
    | El capítulo 6 del documento 4 pide el indicador de pago del listado de
    | empresas —«al día, por vencer o en mora con los días de atraso»— y la
    | escalera de la mora, y de esta última dice literalmente: «conviene
    | definir la escalera: aviso a los 5 días de mora, segundo aviso a los
    | 15, suspensión a los 30. **Los números son una decisión de ustedes**».
    | El capítulo 17.3 la repite entre lo que sigue pendiente.
    |
    | Los números los decidió David el 2026-11-03 —5, 10 y 15 días— y viven
    | en el único sitio donde se pueden cambiar sin tocar lógica. No son una
    | regla legal: son criterio comercial, igual que la salud de uso de arriba.
    | El contrato de servicio tiene que decir exactamente lo mismo
    | (`docs/salida/encargo-al-abogado.md`).
    |
    | Dos cosas que estos números **no** hacen, y es a propósito:
    |
    | - **No suspenden a nadie solos.** Llegar a los días de la escalera pone
    |   el aviso en la pantalla de Suscripciones; suspender sigue siendo una
    |   acción del owner, con su motivo escrito, porque el documento la pide
    |   «con motivo y constancia» y un motivo no lo puede redactar un
    |   proceso. Suspender a un cliente que paga es una decisión que se toma
    |   mirando el caso, no algo que deba hacer un proceso solo.
    | - **No mandan ningún correo.** Los dos avisos de la escalera son
    |   correos al cliente, y los correos del sistema salen del motor de
    |   alertas del capítulo 9, que es de las empresas y sus empleados. Un
    |   aviso de cobro es otra cosa y no tiene todavía tarea.
    |
    */
    /*
    |----------------------------------------------------------------------
    | Clientes en riesgo
    |----------------------------------------------------------------------
    |
    | La tarjeta de atención del inicio del panel (documento 4, 3.1) pide
    | «empresas cuyo uso cayó de forma marcada frente al mes anterior». El
    | documento no fija qué es «de forma marcada» —a diferencia de «clientes
    | sin arrancar», que sí trae sus dos números en el propio texto («creada
    | hace más de una semana con menos de cinco empleados»)—, así que estos
    | dos sí son criterio comercial y viven aquí, con el mismo espíritu que
    | `salud_de_uso`: una propuesta, anotada en `ESTADO.md`.
    |
    | Se mide contando los inicios de sesión exitosos de la empresa —tabla
    | `accesos`— en dos ventanas seguidas de treinta días. Si la ventana
    | actual cae a la mitad o menos de la anterior, y hubo algo que perder,
    | la empresa entra a la tarjeta.
    |
    */
    'riesgo_de_cancelacion' => [

        // El tamaño de cada ventana que se compara.
        'ventana_dias' => 30,

        // Hasta qué porcentaje de la ventana anterior sigue siendo una caída
        // «de forma marcada». 50 significa que el uso se redujo a la mitad
        // o más.
        'caida_minima_porcentaje' => 50,

    ],

    /*
    |----------------------------------------------------------------------
    | Vigencia sugerida del acceso de demostración
    |----------------------------------------------------------------------
    |
    | El formulario de «generar acceso de prueba» (documento 4, capítulo 10
    | y 12.1) propone este número de días en el campo de vigencia, ya
    | escrito y editable. F5-03 lo dejó sin sugerencia a propósito porque la
    | decisión todavía no existía; el documento 6, D.6, la cerró el
    | 2026-08-25 en 14 días —dentro del rango sugerido de 7 a 15, con margen
    | para que una conversación comercial se alargue una semana sin que el
    | prospecto lo use como sistema real—.
    |
    | Es criterio comercial, no una regla legal: cambiarlo es editar este
    | número y nada más, igual que la salud de uso de arriba. El tope duro
    | de 90 días vive en `GenerarAccesoDePrueba`, que es una validación y no
    | una sugerencia.
    |
    */
    'demostracion' => [

        // Días de vigencia que el formulario propone por defecto, editables.
        'dias_de_vigencia_sugeridos' => 14,

    ],

    /*
    |--------------------------------------------------------------------------
    | La salida de un cliente (documento 6, A.4)
    |--------------------------------------------------------------------------
    |
    | Los días que tienen que pasar entre la entrega de toda su información y
    | su eliminación definitiva: el «periodo de gracia corto y pactado» de A.4,
    | por si el cliente nota que le falta algo. Treinta es lo habitual; si el
    | contrato pacta otra cosa, se cambia aquí.
    |
    */
    'salida' => [

        'dias_de_gracia' => 30,

    ],

    'cobro' => [

        // A cuántos días del próximo cobro la columna «Pago» pasa de «al
        // día» a «por vencer». No es un paso de la escalera —la escalera
        // empieza cuando ya hay mora—, es el aviso de que el cobro viene:
        // el prototipo lo enseña como «Vence en 3 días» en ámbar.
        'dias_por_vencer' => 5,

        // La escalera decidida por David el 2026-11-03: avisos a los 5 y a
        // los 10 días de mora, suspensión a los 15. El documento 4 proponía
        // 5, 15 y 30. El contrato tiene que copiar estos mismos números.
        'escalera_de_mora' => [
            'primer_aviso' => 5,
            'segundo_aviso' => 10,
            'suspension' => 15,
        ],

    ],

];
