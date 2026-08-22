<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\TipoDeAlerta;
use Illuminate\Support\Carbon;

/**
 * «El periodo de prueba de esta persona termina en dos días (fecha)»
 * (documento 1, 9.3, primera fila).
 *
 * El primero de los dos avisos del término fijo. Aquí el periodo de prueba dura
 * la quinta parte del plazo pactado sin pasar de dos meses, así que la fecha no
 * se puede deducir del ingreso: la calcula `PeriodoDePrueba::terminaEl()` con
 * la duración del contrato.
 *
 * En un contrato tan corto que el periodo de prueba quepa en uno o dos días,
 * este aviso caería antes del ingreso y no se manda. Lo decide la clase base,
 * que es donde vale para las cinco.
 */
class PruebaFijoDosDias extends ReglaDePeriodoDePrueba
{
    /**
     * Cuántos días antes del final avisa.
     */
    private const DIAS_DE_ANTICIPACION = 2;

    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::PruebaFijoDosDias;
    }

    /**
     * @return list<TipoContrato>
     */
    protected function modalidades(): array
    {
        return [TipoContrato::Fijo];
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
        return 'El periodo de prueba de '.$nombre.', '.$cargo.', termina '.$referencia->frase().'.';
    }
}
