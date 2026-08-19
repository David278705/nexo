<?php

/**
 * Las cinco listas de entidades del formulario de empleado: EPS, fondos de
 * pensión, ARL, cajas de compensación y bancos.
 *
 * Es la respuesta a la decisión D.5, que llegó en
 * `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`, hoja «Listas».
 * **Los nombres están copiados literalmente de ese archivo**, uno por uno y sin
 * corregirlos: son los que el cliente reconoce y los que va a leer en las
 * exportaciones. Si alguno hay que cambiarlo, se cambia allá y aquí, no aquí
 * solo.
 *
 * `codigo` queda nulo en las cinco listas: el archivo trae los nombres, no los
 * códigos oficiales de la Supersalud ni de la Superfinanciera. La columna sigue
 * ahí para cuando se carguen, y no se inventa un código para llenarla.
 *
 * La hoja «Notas y fuentes» del mismo archivo advierte dos cosas que conviene
 * no perder:
 *
 * - Nueve de estas EPS están intervenidas por la Supersalud pero siguen
 *   operando y afiliando, así que se ofrecen como opciones válidas.
 * - Las cuatro listas del sistema de seguridad social cambian con frecuencia
 *   —intervenciones, fusiones, traslados—, y **antes de vender hay que
 *   contrastarlas contra las fuentes oficiales**: Supersalud, Fasecolda, la
 *   Superintendencia del Subsidio Familiar y la Superintendencia Financiera.
 *   Eso está anotado en `ESTADO.md` como tarea de David.
 *
 * Cada lista termina en «Otra» u «Otro», tal como viene del archivo: es la
 * salida para la entidad que no esté, y evita que alguien deje el campo vacío o
 * escriba el nombre de una EPS dentro del cargo.
 *
 * @return array<string, list<array{codigo?: string|null, nombre: string}>>
 */

/**
 * Convierte una lista de nombres en la forma que espera `EntidadesSeeder`.
 *
 * @param  list<string>  $nombres
 * @return list<array{nombre: string}>
 */
$lista = fn (array $nombres): array => array_map(
    fn (string $nombre): array => ['nombre' => $nombre],
    $nombres,
);

return [
    'eps' => $lista([
        'Sura EPS',
        'Sanitas EPS',
        'Compensar EPS',
        'Aliansalud EPS',
        'Salud Total EPS',
        'Capital Salud EPS',
        'Comfenalco Valle EPS',
        'Nueva EPS',
        'Famisanar EPS',
        'Coosalud EPS',
        'Emssanar EPS',
        'Savia Salud EPS',
        'Asmet Salud EPS',
        'S.O.S. (Servicio Occidental de Salud)',
        'Capresoca EPS',
        'Mutual Ser EPS',
        // Quien está en un régimen de excepción sí puede ser contratado por una
        // empresa del régimen general: no se afilia a una EPS y el aporte de
        // este vínculo va a la subcuenta de solidaridad de la ADRES. Por eso es
        // una opción de la lista y no un campo vacío.
        'Régimen Especial / de Excepción (Fuerzas Militares, Policía, Magisterio - FOMAG, Ecopetrol, universidades públicas)',
        'Otra',
    ]),

    'fondos_pension' => $lista([
        'Colpensiones (Régimen de Prima Media)',
        'Porvenir',
        'Protección',
        'Colfondos',
        'Skandia',
        // No es un fondo: es el estado de quien ya está pensionado y se vincula
        // de nuevo. Viene así del archivo del cliente.
        'Pensionado',
        'Otro',
    ]),

    'arls' => $lista([
        'ARL Sura',
        'Positiva Compañía de Seguros',
        'Colmena Seguros (Riesgos Laborales)',
        'Seguros Bolívar',
        'Seguros Colpatria (Axa Colpatria)',
        'La Equidad Seguros',
        'Mapfre Seguros Generales de Colombia',
        'Seguros de Vida Alfa',
        'Compañía de Seguros de Vida Aurora',
    ]),

    'cajas_compensacion' => $lista([
        'Cafam',
        'Colsubsidio',
        'Compensar',
        'Comfama',
        'Comfenalco Antioquia',
        'Comfandi',
        'Comfenalco Valle',
        'Comfenalco Santander',
        'Cajasan',
        'Comfenalco Quindío',
        'Comfenalco Cartagena',
        'Comfaboy',
        'Confa (Caldas)',
        'Comfacasanare',
        'Comfacauca',
        'Comfacesar',
        'Comfachocó',
        'Comfacundi',
        'Cajamag',
        'Cofrem',
        'Comfaoriente',
        'Comfamiliar Putumayo',
        'Comfasucre',
        'Combarranquilla',
        'Cajacopi Atlántico',
        'Comfamiliar Atlántico',
        'Comfamiliar Huila',
        'Comfamiliar Nariño',
        'Comfamiliar Risaralda',
        'Comfamiliar Tolima',
        'Comfaguajira',
        'Comfacor (Córdoba)',
        'Cafamaz (Amazonas)',
        'Cajasai (San Andrés, Providencia y Santa Catalina)',
    ]),

    'bancos' => $lista([
        'Bancolombia',
        'Banco de Bogotá',
        'Banco Davivienda',
        'BBVA Colombia',
        'Banco de Occidente',
        'Banco Popular',
        'Banco Caja Social',
        'Scotiabank Colpatria',
        'Banco AV Villas',
        'Banco Agrario de Colombia',
        'Itaú Colombia',
        'Banco Falabella',
        'Banco Pichincha',
        'Banco GNB Sudameris',
        'Banco Serfinanza',
        'Bancoomeva',
        'Banco W',
        'Banco Finandina',
        'Bancamía',
        'Lulo Bank',
        'Nu Colombia (Nubank)',
        // Las tres billeteras digitales están en la lista del cliente porque hay
        // empleados que reciben el sueldo ahí. Son cuenta de destino, no banco.
        'Nequi (billetera digital – Bancolombia)',
        'Daviplata (billetera digital – Davivienda)',
        'RappiPay',
        'Otra',
    ]),
];
