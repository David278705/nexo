<?php

namespace App\Dominio\Calculo;

use Illuminate\Support\Carbon;

/**
 * El saldo de vacaciones acumulado de toda la planta, y de dónde salió.
 *
 * Es lo que devuelve `CacheDeSaldos::total()` y lo que pintará la tarjeta del
 * panel de inicio. Lleva cuatro cosas además de la cifra, y ninguna es adorno:
 *
 * - `al` es la fecha a la que se refiere el saldo. Un total de días sin la fecha
 *   a la que corresponde no se puede contrastar con nada, y la pantalla tiene
 *   que poder escribir «actualizado al 22 de agosto» en lugar de dar a entender
 *   que la cifra es de este instante.
 * - `empleados` es sobre cuántos se sumó. Con él, la tarjeta puede decir «de 47
 *   personas» y quien la mira nota si falta alguien.
 * - `desdeLaCache` y `calculadosAlVuelo` son las dos mitades de ese número.
 *   La segunda son los empleados registrados **después** del último refresco:
 *   entran igualmente en el total, calculados en el momento, porque un total
 *   que se dejara fuera a los de ayer sería un número equivocado y no un número
 *   viejo. En régimen, después de cada noche, vale cero.
 */
class TotalDeSaldos
{
    public function __construct(
        public readonly float $dias,
        public readonly int $empleados,
        public readonly int $desdeLaCache,
        public readonly int $calculadosAlVuelo,
        public readonly Carbon $al,
    ) {}

    /**
     * Si la cifra salió entera de la caché.
     *
     * Es la señal de que el proceso diario está al día. Su contrario no es un
     * error —el total sigue siendo correcto— sino la advertencia de que se pagó
     * el cálculo de unos cuantos empleados en medio de una petición.
     */
    public function vieneToda(): bool
    {
        return $this->calculadosAlVuelo === 0;
    }
}
