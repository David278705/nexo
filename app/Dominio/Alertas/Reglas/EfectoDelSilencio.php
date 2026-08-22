<?php

namespace App\Dominio\Alertas\Reglas;

use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\TipoDeAlerta;
use Illuminate\Support\Carbon;

/**
 * «Último día para avisar la no renovación» — 30 días antes del vencimiento
 * (documento 1, 9.4, segunda fila).
 *
 * Es la alerta de mayor consecuencia jurídica del producto: el día que sale es
 * el último en que la empresa puede evitar que el contrato se prorrogue solo.
 * A partir de mañana, callar ya prorroga.
 *
 * Dispara el mismo día de la fecha límite del preaviso, así que su
 * `fechaDelHecho` y su fecha de disparo son la misma y la frase dice «hoy». Con
 * el corrimiento al día hábil anterior deja de decirlo —«en dos días (1 de
 * diciembre)»— y eso no es un defecto: el correo sale antes porque el día del
 * plazo es domingo, y quien lo lee tiene que saber cuál es el día de verdad.
 *
 * El cuerpo no nombra el cargo; el de la sección 5.4 tampoco. Aquí la
 * consecuencia es del contrato y no de quién lo ocupa.
 */
class EfectoDelSilencio extends ReglaDePreavisoDelFijo
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::EfectoDelSilencio;
    }

    protected function diasAntesDelPreaviso(): int
    {
        return 0;
    }

    protected function asunto(string $nombre): string
    {
        return 'Último día para avisar la no renovación de '.$nombre;
    }

    protected function cuerpo(
        string $nombre,
        string $cargo,
        Carbon $vencimiento,
        ReferenciaTemporal $referencia,
    ): string {
        return $referencia->fraseCapitalizada().' es el último día para notificar a '.$nombre
            .' que su contrato no se renovará. Si no se avisa, el contrato queda prorrogado '
            .'automáticamente por un término igual al último pactado.';
    }
}
