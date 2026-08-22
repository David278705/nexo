<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * «Esta persona ha completado un mes de periodo de prueba» (documento 1, 9.2,
 * primera fila).
 *
 * Se dispara el día en que se cumple un mes desde el ingreso, que es la víspera
 * del mismo número del mes siguiente: quien entró el 15 de abril cumple el mes
 * el 14 de mayo. Eso lo sabe `MotorDeCalculo::cumpleMesesEl()` y no se cuenta
 * aquí.
 *
 * **Es la única de las cinco que no lleva referencia relativa.** Su plantilla
 * dice «Su periodo de prueba termina el {fecha}», una fecha absoluta, así que
 * el corrimiento al día hábil anterior no tiene nada que ajustarle: la frase
 * sigue siendo cierta salga el jueves o el viernes. Por eso escribe con
 * `Formato` y no con `ReferenciaTemporal`, y por eso la fecha va con año: el
 * fin del periodo de prueba queda a un mes largo y puede caer en el año
 * siguiente.
 */
class PruebaIndefinidoUnMes extends ReglaDePeriodoDePrueba
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::PruebaIndefinidoUnMes;
    }

    /**
     * @return list<TipoContrato>
     */
    protected function modalidades(): array
    {
        return self::PRUEBA_GENERAL;
    }

    protected function disparaEl(Carbon $ingreso, Carbon $termina): Carbon
    {
        return Carbon::parse(MotorDeCalculo::cumpleMesesEl($ingreso, 1)->toDateString());
    }

    protected function asunto(string $nombre, ReferenciaTemporal $referencia): string
    {
        return 'Periodo de prueba: '.$nombre.' completó un mes';
    }

    protected function cuerpo(string $nombre, string $cargo, ReferenciaTemporal $referencia): string
    {
        return $nombre.', '.$cargo.', ha completado un mes de periodo de prueba. '
            .'Su periodo de prueba termina el '.Formato::fechaLarga($referencia->fecha).'.';
    }
}
