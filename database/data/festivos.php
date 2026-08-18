<?php

/**
 * El calendario de festivos de Colombia, año por año.
 *
 * **No se calcula: se escribe y se verifica a mano.** Es la instrucción del
 * capítulo 10 del documento 1 y de la sección 4.3 del documento 2. La Ley 51 de
 * 1983 traslada al lunes siguiente unos festivos y otros no, y los tres que
 * dependen de la Pascua se trasladan además con desfases distintos: cualquier
 * fórmula falla tarde o temprano y el error no se nota hasta que un cálculo de
 * vacaciones sale mal para todos los clientes a la vez.
 *
 * **Están incluidos los festivos que caen en sábado** —el 1 de mayo y el 7 de
 * agosto de 2027, por ejemplo—, porque para las empresas que tienen el sábado
 * como día laboral sí cambian el conteo. Omitirlos daría cuentas equivocadas
 * justo en ese grupo de clientes.
 *
 * Cuando dos celebraciones caen en la misma fecha —el 30 de junio de 2025, San
 * Pedro y el Sagrado Corazón— van en una sola fila con los dos nombres: lo que
 * le importa al motor de cálculo es si el día es festivo, no cuántas fiestas
 * hay en él.
 *
 * **Cargar el año siguiente es una tarea anual del proveedor**, hecha y
 * verificada antes de que el año empiece.
 *
 * @return array<int, array<string, string>> año => (fecha `Y-m-d` => nombre)
 */
return [
    2025 => [
        '2025-01-01' => 'Año Nuevo',
        '2025-01-06' => 'Día de los Reyes Magos',
        '2025-03-24' => 'Día de San José',
        '2025-04-17' => 'Jueves Santo',
        '2025-04-18' => 'Viernes Santo',
        '2025-05-01' => 'Día del Trabajo',
        '2025-06-02' => 'Ascensión del Señor',
        '2025-06-23' => 'Corpus Christi',
        '2025-06-30' => 'San Pedro y San Pablo · Sagrado Corazón de Jesús',
        '2025-07-20' => 'Día de la Independencia',
        '2025-08-07' => 'Batalla de Boyacá',
        '2025-08-18' => 'Asunción de la Virgen',
        '2025-10-13' => 'Día de la Raza',
        '2025-11-03' => 'Día de Todos los Santos',
        '2025-11-17' => 'Independencia de Cartagena',
        '2025-12-08' => 'Día de la Inmaculada Concepción',
        '2025-12-25' => 'Navidad',
    ],
    2026 => [
        '2026-01-01' => 'Año Nuevo',
        '2026-01-12' => 'Día de los Reyes Magos',
        '2026-03-23' => 'Día de San José',
        '2026-04-02' => 'Jueves Santo',
        '2026-04-03' => 'Viernes Santo',
        '2026-05-01' => 'Día del Trabajo',
        '2026-05-18' => 'Ascensión del Señor',
        '2026-06-08' => 'Corpus Christi',
        '2026-06-15' => 'Sagrado Corazón de Jesús',
        '2026-06-29' => 'San Pedro y San Pablo',
        '2026-07-20' => 'Día de la Independencia',
        '2026-08-07' => 'Batalla de Boyacá',
        '2026-08-17' => 'Asunción de la Virgen',
        '2026-10-12' => 'Día de la Raza',
        '2026-11-02' => 'Día de Todos los Santos',
        '2026-11-16' => 'Independencia de Cartagena',
        '2026-12-08' => 'Día de la Inmaculada Concepción',
        '2026-12-25' => 'Navidad',
    ],
    2027 => [
        '2027-01-01' => 'Año Nuevo',
        '2027-01-11' => 'Día de los Reyes Magos',
        '2027-03-22' => 'Día de San José',
        '2027-03-25' => 'Jueves Santo',
        '2027-03-26' => 'Viernes Santo',
        // Cae en sábado y se carga igual: para la empresa con sábado laboral
        // este día sí cuenta.
        '2027-05-01' => 'Día del Trabajo',
        '2027-05-10' => 'Ascensión del Señor',
        '2027-05-31' => 'Corpus Christi',
        '2027-06-07' => 'Sagrado Corazón de Jesús',
        '2027-07-05' => 'San Pedro y San Pablo',
        '2027-07-20' => 'Día de la Independencia',
        // También en sábado.
        '2027-08-07' => 'Batalla de Boyacá',
        '2027-08-16' => 'Asunción de la Virgen',
        '2027-10-18' => 'Día de la Raza',
        '2027-11-01' => 'Día de Todos los Santos',
        '2027-11-15' => 'Independencia de Cartagena',
        '2027-12-08' => 'Día de la Inmaculada Concepción',
        // Y el tercero en sábado de este año.
        '2027-12-25' => 'Navidad',
    ],
];
