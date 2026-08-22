<?php

namespace App\Dominio\Alertas\Reglas;

use App\Dominio\Alertas\ReferenciaTemporal;
use Illuminate\Support\Carbon;

/**
 * El último aviso del periodo de prueba, la víspera de que se acabe.
 *
 * Son dos alertas del catálogo y no una —la del indefinido (9.2, tercera fila)
 * y la del término fijo (9.3, segunda fila)—, pero **el documento les da
 * exactamente el mismo asunto y el mismo cuerpo**, palabra por palabra. Se
 * escriben una sola vez aquí para que sigan siendo iguales: dos copias del
 * mismo texto acaban divergiendo en el primer retoque de redacción, y entonces
 * el cliente recibe dos versiones del mismo aviso según la modalidad del
 * contrato.
 *
 * Siguen siendo dos tipos distintos porque son dos filas distintas del
 * catálogo, y el registro de envíos tiene que poder decir cuál de las dos salió.
 */
abstract class ReglaDeVisperaDePrueba extends ReglaDePeriodoDePrueba
{
    protected function disparaEl(Carbon $ingreso, Carbon $termina): Carbon
    {
        return $termina->copy()->subDay();
    }

    /**
     * «Mañana (14 de junio) vence el periodo de prueba de Ana Gómez».
     *
     * El asunto de la sección 5.4 empieza con un «Mañana» escrito a mano que
     * deja de ser cierto en cuanto el corrimiento adelanta el envío. Lo escribe
     * `ReferenciaTemporal`, igual que el cuerpo, y con la fecha entre
     * paréntesis: el asunto es lo primero que se lee y es donde más daño hace
     * una fecha equivocada.
     */
    protected function asunto(string $nombre, ReferenciaTemporal $referencia): string
    {
        return $referencia->fraseCapitalizada().' vence el periodo de prueba de '.$nombre;
    }

    protected function cuerpo(string $nombre, string $cargo, ReferenciaTemporal $referencia): string
    {
        return $referencia->fraseCapitalizada().' es el último día del periodo de prueba de '
            .$nombre.', '.$cargo.'.';
    }
}
