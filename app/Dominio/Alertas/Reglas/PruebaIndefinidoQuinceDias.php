<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\TipoDeAlerta;
use Illuminate\Support\Carbon;

/**
 * «Esta persona completará en 15 días (fecha) su periodo de prueba»
 * (documento 1, 9.2, segunda fila).
 *
 * ## Quince días antes del final, no cuarenta y cinco después del ingreso
 *
 * La tabla del documento titula la fila «al cumplir 45 días desde el ingreso» y
 * el mensaje dice «completará en 15 días». En el ejemplo del propio documento
 * las dos lecturas coinciden: quien entra el 15 de abril termina el 14 de junio
 * y la alerta sale el 30 de mayo, que es a la vez el día 45 y quince días antes
 * del final.
 *
 * Dejan de coincidir cuando el periodo de prueba cae sobre febrero: quien entra
 * el 31 de enero termina el 30 de marzo, y ahí el día 45 es el 17 de marzo pero
 * los quince días antes son el 15. **Manda el mensaje**, que es lo que el
 * cliente lee: una alerta que dice «en 15 días» y sale trece días antes está
 * mintiendo sobre un plazo legal. El «45 días» es la descripción del caso
 * corriente; el «en 15 días» es la promesa.
 */
class PruebaIndefinidoQuinceDias extends ReglaDePeriodoDePrueba
{
    /**
     * Cuántos días antes del final avisa.
     */
    private const DIAS_DE_ANTICIPACION = 15;

    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::PruebaIndefinidoQuinceDias;
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
        return $termina->copy()->subDays(self::DIAS_DE_ANTICIPACION);
    }

    protected function asunto(string $nombre, ReferenciaTemporal $referencia): string
    {
        return $this->asuntoDeCuentaAtras($nombre, $referencia);
    }

    protected function cuerpo(string $nombre, string $cargo, ReferenciaTemporal $referencia): string
    {
        return $nombre.', '.$cargo.', completará '.$referencia->frase().' su periodo de prueba.';
    }
}
