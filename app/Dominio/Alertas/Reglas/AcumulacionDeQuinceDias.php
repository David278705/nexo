<?php

namespace App\Dominio\Alertas\Reglas;

use App\Dominio\Alertas\TipoDeAlerta;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * «Acumulación de 15 días» (documento 1, 9.5, primera fila).
 *
 * El resumen del mes con las personas que **ese mes** completan quince días de
 * vacaciones acumuladas. Ya causaron un periodo completo y conviene programarlas
 * antes de que la acumulación siga creciendo.
 *
 * ## Los quince días no están escritos aquí
 *
 * Son los de un año de servicio: doce meses a 1,25 días, que es la constante
 * `dias_vacaciones_por_mes` del artículo 186 del CST. Lo calcula
 * `SaldoDeVacaciones::diasDeUnPeriodoCompleto()`, en el motor, porque desde
 * F4-03 también lo pregunta la tarjeta de acción del panel de inicio y dos
 * declaraciones del mismo quince se separarían solas.
 *
 * ## Se mira contra el fin del mes calendario, no contra el corte
 *
 * El texto dice «completaron **este mes**», así que el mes tiene que estar
 * contado entero. El saldo causa el mes completo al cerrarse —regla del motor,
 * fijada por dos de los diez casos obligatorios—, de modo que preguntarlo el
 * último día hábil, que a veces es el 29, daría el saldo del mes anterior y el
 * resumen no listaría a nadie. La consecuencia es que el correo puede salir uno
 * o dos días antes del cierre anunciando un saldo que se causa el día 31: es lo
 * que la plantilla pide, y el día del cierre no cambia nada de lo que el lector
 * tiene que hacer.
 *
 * ## Por qué hay que mirar también el mes anterior
 *
 * «Completan este mes» es un cruce, no un umbral: quien ya tenía dieciséis días
 * el mes pasado no completa nada este mes y no puede volver a aparecer todos los
 * meses hasta que se tome unas vacaciones. Por eso la condición son las dos
 * caras —al cierre de este mes alcanza los quince y al cierre del anterior no—,
 * y por eso quien disfruta vacaciones y vuelve a acumularlos sí aparece otra
 * vez: la segunda vez también los completó.
 *
 * ## A todas las modalidades
 *
 * También al contrato de obra o labor: la sección 9.6 dice expresamente que sí
 * le aplican las alertas de vacaciones, aunque no le apliquen las de periodo de
 * prueba ni las de vencimiento.
 */
class AcumulacionDeQuinceDias extends ResumenMensual
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::AcumulacionDeQuinceDias;
    }

    protected function asunto(int $cuantos): string
    {
        return $cuantos === 1
            ? 'Vacaciones acumuladas: 1 persona completó 15 días'
            : 'Vacaciones acumuladas: '.$cuantos.' personas completaron 15 días';
    }

    protected function encabezado(int $cuantos): string
    {
        return $cuantos === 1
            ? 'Esta persona completó este mes 15 días de vacaciones acumuladas. Ya causó un periodo '
                .'completo y conviene programarlas.'
            : 'Estas personas completaron este mes 15 días de vacaciones acumuladas. Ya causaron un '
                .'periodo completo y conviene programarlas.';
    }

    /**
     * @return list<string>
     */
    protected function lineas(Carbon $corte): array
    {
        $cierre = $corte->copy()->endOfMonth()->startOfDay();
        $cierreAnterior = $corte->copy()->startOfMonth()->subDay();
        $periodo = SaldoDeVacaciones::diasDeUnPeriodoCompleto();

        $lineas = [];

        foreach ($this->activas()->get() as $vinculacion) {
            $saldo = SaldoDeVacaciones::de($vinculacion);

            $alCierre = $saldo->al($cierre);

            if ($alCierre < $periodo || $saldo->al($cierreAnterior) >= $periodo) {
                continue;
            }

            $lineas[] = $this->linea($vinculacion, $alCierre);
        }

        return $lineas;
    }

    private function linea(Vinculacion $vinculacion, float $saldo): string
    {
        return $this->quien($vinculacion).' — '.Formato::diasDecimales($saldo).' acumulados.';
    }
}
