<?php

namespace App\Terminaciones;

use App\Contratacion\HistorialDeContrato;
use App\Contratacion\TipoContrato;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * El vacío entre el último periodo contractual conocido y la fecha de salida
 * (documento 6, D.7).
 *
 * Un contrato a término fijo que venció sin que nadie registrara su prórroga
 * ni su paso a indefinido deja a la vinculación «en el aire»: el sistema no
 * sabe qué contrato regía después del vencimiento —si se prorrogó solo, si
 * firmaron algo más largo o si pasó a indefinido—, y de eso depende qué
 * indemnización aplicaría si la salida la motivó la empresa. Por eso la salida
 * no se registra hasta aclararlo.
 *
 * ## Qué cuenta como vacío
 *
 * Solo el término fijo con fecha de vencimiento, y solo si la salida cae
 * **después** de ese vencimiento. Salir el mismo día en que vence, o antes, es
 * una salida dentro del periodo vigente. El indefinido y la obra o labor no
 * tienen fecha contra la que medir: no vencen por plazo.
 *
 * El vacío corre desde el día siguiente al vencimiento hasta la fecha de
 * salida, los dos incluidos: del 1 de julio al 10 de septiembre cuando el
 * contrato vencía el 30 de junio y la salida es el 10 de septiembre.
 */
final class ContratoSinAclarar
{
    private function __construct(
        private readonly Carbon $vencimiento,
        private readonly Carbon $salida,
    ) {}

    /**
     * El vacío que dejaría esa salida, o nulo si el contrato está claro.
     */
    public static function para(Vinculacion $vinculacion, Carbon $fechaDeSalida): ?self
    {
        $historial = HistorialDeContrato::de($vinculacion);

        if ($historial->tipoDeContratoVigente() !== TipoContrato::Fijo) {
            return null;
        }

        $vencimiento = $historial->fechaDeVencimiento();

        if (! $vencimiento instanceof Carbon || ! $fechaDeSalida->copy()->startOfDay()->greaterThan($vencimiento->copy()->startOfDay())) {
            return null;
        }

        return new self($vencimiento->copy()->startOfDay(), $fechaDeSalida->copy()->startOfDay());
    }

    /**
     * El primer día que nadie sabe cómo estaba cubierto.
     */
    public function desde(): Carbon
    {
        return $this->vencimiento->copy()->addDay();
    }

    /**
     * El último: la propia fecha de salida.
     */
    public function hasta(): Carbon
    {
        return $this->salida->copy();
    }

    /**
     * Lo que se le dice a quien intenta registrar la salida: qué falta, las
     * fechas exactas y las dos cosas que resuelven el vacío.
     */
    public function mensaje(): string
    {
        $sinCubrir = $this->desde()->equalTo($this->hasta())
            ? 'quedaría sin cubrir el día '.Formato::fechaLarga($this->desde())
            : 'quedarían sin cubrir los días del '.Formato::fechaLarga($this->desde())
                .' al '.Formato::fechaLarga($this->hasta());

        return 'El contrato a término fijo venció el '.Formato::fechaLarga($this->vencimiento)
            .' y no hay ninguna prórroga ni cambio a término indefinido registrados después. '
            .'Con esta fecha de salida '.$sinCubrir.', y sin saber '
            .'qué contrato regía no se puede saber qué indemnización aplicaría. '
            .'En Contratos, registra la prórroga o el cambio a término indefinido y después '
            .'vuelve a registrar la salida.';
    }
}
