<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\HistorialDeContrato;
use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * «Definir renovación del contrato» — 32 días antes del vencimiento
 * (documento 1, 9.4, primera fila).
 *
 * Es la primera de las dos alertas del preaviso y la única que da margen para
 * decidir: cuando llega, todavía quedan dos días para avisarle al trabajador
 * que el contrato no se renueva. Pasados esos dos días, el silencio ya prorroga
 * el contrato solo.
 *
 * ## Dos fechas en un mismo mensaje, y cada una escrita como debe
 *
 * El cuerpo de la sección 5.4 nombra dos días distintos y no se pueden
 * confundir:
 *
 * - **El vencimiento del contrato**, que va escrito entero y con año
 *   —`Formato::fechaLarga()`—, porque puede caer en el año siguiente y porque
 *   el mensaje no habla de él en relativo.
 * - **La fecha límite del preaviso**, que sí es una cuenta atrás y la escribe
 *   `ReferenciaTemporal`, con la fecha entre paréntesis (9.1) y ya ajustada al
 *   corrimiento al día hábil anterior.
 *
 * De la segunda es de la que habla la alerta, y por eso es la `fechaDelHecho`.
 */
class PlazoDeRenovacion extends ReglaDePreavisoDelFijo
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::PlazoDeRenovacion;
    }

    /**
     * Los dos días que el propio texto anuncia —«en dos días vence el plazo
     * legal de 30 días»—, que sumados a los treinta del preaviso dan los 32 de
     * la tabla del documento. Están en la clase base porque la alerta de última
     * renovación corta comparte este mismo día.
     */
    protected function diasAntesDelPreaviso(): int
    {
        return self::DIAS_DE_MARGEN_DEL_PLAZO;
    }

    protected function asunto(string $nombre): string
    {
        return 'Definir renovación del contrato de '.$nombre;
    }

    protected function cuerpo(
        string $nombre,
        string $cargo,
        Carbon $vencimiento,
        ReferenciaTemporal $referencia,
        HistorialDeContrato $historial,
    ): string {
        return 'El contrato de '.$nombre.', '.$cargo.', termina el '.Formato::fechaLarga($vencimiento).'. '
            .$referencia->fraseCapitalizada().' vence el plazo legal de '.$this->diasDePreaviso()
            .' días para avisarle si el contrato no se va a renovar. Si van a pactar la prórroga, el '
            .'documento debe firmarse antes del '.Formato::fechaLarga($vencimiento).'.';
    }
}
