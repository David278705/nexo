<?php

namespace App\Dominio\Alertas\Reglas;

use App\Dominio\Alertas\TipoDeAlerta;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * «Año cumplido sin disfrute mínimo» (documento 1, 9.5, segunda fila).
 *
 * El resumen del mes con las personas que **al mes siguiente** cumplen un año de
 * servicio sin haber disfrutado al menos seis días hábiles continuos de
 * vacaciones. El artículo 190 del CST exige ese mínimo cada año y lo declara no
 * acumulable, así que el aviso llega con un mes de margen: es el tiempo que hay
 * para programarlas antes de que el año se cierre.
 *
 * ## Cada año de servicio, no solo el primero
 *
 * La plantilla de la sección 5.4 dice «cumplen el mes entrante un año en la
 * empresa», y esta regla la lee como «un año más», no como «su primer año». El
 * motivo está en la frase siguiente de la misma plantilla: «que es el mínimo
 * anual que exige la ley». Un aviso que solo mirara el primer aniversario
 * dejaría fuera para siempre a quien lleva cuatro años sin tomar seis días
 * seguidos, que es exactamente la persona sobre la que la norma habla. El texto
 * dice «un año de servicio» —dos palabras más que la plantilla— porque a los
 * tres años «cumple un año en la empresa» sería falso.
 *
 * ## Qué se mira de cada año
 *
 * El año de servicio que se cierra, no el historial entero: quien tomó sus seis
 * días continuos el año pasado pero no este sí aparece. Se cuenta con la regla
 * de meses de la sección 9.1 —`MotorDeCalculo::cumpleMesesEl()`, la víspera del
 * mismo número—, que es la misma con la que el sistema cuenta el periodo de
 * prueba: dos formas de contar el mismo aniversario se separarían el día en que
 * una de las dos se corrija.
 *
 * ## «Seis días hábiles continuos» es un solo periodo
 *
 * Continuos quiere decir seguidos, así que la condición la cumple **un** periodo
 * registrado de seis días hábiles o más, no la suma de varios cortos. Dos
 * periodos de tres días no son un descanso de seis. Los días de cada periodo los
 * cuenta `Vacacion::dias()`, que es la regla de días hábiles del motor y ya mira
 * el sábado laboral de la empresa.
 */
class AnioSinDisfruteMinimo extends ResumenMensual
{
    private const MESES_DEL_ANIO = 12;

    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::AnioSinDisfruteMinimo;
    }

    protected function asunto(int $cuantos): string
    {
        return $cuantos === 1
            ? 'Vacaciones pendientes: 1 persona cumple un año de servicio'
            : 'Vacaciones pendientes: '.$cuantos.' personas cumplen un año de servicio';
    }

    protected function encabezado(int $cuantos): string
    {
        $quienes = $cuantos === 1
            ? 'Esta persona cumple el mes entrante un año de servicio en la empresa sin haber tomado'
            : 'Estas personas cumplen el mes entrante un año de servicio en la empresa sin haber tomado';

        return $quienes.' al menos '.Formato::dias($this->disfruteMinimo()).' hábiles continuos de '
            .'vacaciones, que es el mínimo anual que exige la ley.';
    }

    /**
     * @return list<string>
     */
    protected function lineas(Carbon $corte): array
    {
        $desde = $corte->copy()->addMonthNoOverflow()->startOfMonth();
        $hasta = $desde->copy()->endOfMonth()->startOfDay();

        $lineas = [];

        foreach ($this->activas()->with('vacaciones')->get() as $vinculacion) {
            $anios = $this->aniosQueCumpleEntre($vinculacion, $desde, $hasta);

            if ($anios === null) {
                continue;
            }

            [$anio, $aniversario] = $anios;

            if ($this->disfrutoElMinimoEn($vinculacion, $this->inicioDelAnio($vinculacion, $anio), $aniversario)) {
                continue;
            }

            $lineas[] = $this->linea($vinculacion, $anio, $aniversario);
        }

        return $lineas;
    }

    private function linea(Vinculacion $vinculacion, int $anio, Carbon $aniversario): string
    {
        return $this->quien($vinculacion).' — cumple '
            .Formato::tiempoTranscurrido($anio * self::MESES_DEL_ANIO).' el '
            .Formato::fechaLarga($aniversario).'.';
    }

    /**
     * Qué año de servicio cumple en ese mes, y qué día, o nulo si ninguno.
     *
     * El año se deduce de los meses cumplidos al final del mes mirado, así que
     * se resuelve de una vez y no probando aniversario por aniversario. Que el
     * resultado caiga dentro del mes lo decide la comparación: alguien que
     * cumplió tres años en abril tiene el mismo número de años cumplidos en
     * junio, y no le toca.
     *
     * @return array{0: int, 1: Carbon}|null
     */
    private function aniosQueCumpleEntre(Vinculacion $vinculacion, Carbon $desde, Carbon $hasta): ?array
    {
        $ingreso = $vinculacion->fecha_ingreso;

        $anios = intdiv(MotorDeCalculo::mesesCumplidos($ingreso, $hasta), self::MESES_DEL_ANIO);

        if ($anios < 1) {
            return null;
        }

        $aniversario = Carbon::parse(
            MotorDeCalculo::cumpleMesesEl($ingreso, $anios * self::MESES_DEL_ANIO)->toDateString()
        );

        return $aniversario->betweenIncluded($desde, $hasta) ? [$anios, $aniversario] : null;
    }

    /**
     * El primer día del año de servicio que se cierra.
     *
     * Para el primero es la fecha de ingreso; para los siguientes, el día
     * después del aniversario anterior.
     */
    private function inicioDelAnio(Vinculacion $vinculacion, int $anio): Carbon
    {
        if ($anio === 1) {
            return Carbon::parse($vinculacion->fecha_ingreso->toDateString());
        }

        return Carbon::parse(
            MotorDeCalculo::cumpleMesesEl($vinculacion->fecha_ingreso, ($anio - 1) * self::MESES_DEL_ANIO)->toDateString()
        )->addDay();
    }

    /**
     * Si en ese año hay un solo periodo de vacaciones de al menos seis días
     * hábiles.
     *
     * Los periodos se ubican por su fecha de inicio, que es la misma convención
     * con la que `SaldoDeVacaciones` los descuenta.
     */
    private function disfrutoElMinimoEn(Vinculacion $vinculacion, Carbon $desde, Carbon $hasta): bool
    {
        $minimo = $this->disfruteMinimo();

        return $vinculacion->vacaciones
            ->filter(fn (Vacacion $vacacion): bool => $vacacion->fecha_inicio->betweenIncluded($desde, $hasta))
            ->contains(fn (Vacacion $vacacion): bool => $vacacion->dias() >= $minimo);
    }

    /**
     * Los seis días hábiles continuos del artículo 190 del CST.
     */
    private function disfruteMinimo(): int
    {
        return (int) config('reglas_laborales.disfrute_minimo_dias_habiles');
    }
}
