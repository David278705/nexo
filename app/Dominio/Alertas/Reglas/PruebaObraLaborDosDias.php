<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\TipoDeAlerta;

/**
 * «El periodo de prueba de esta persona termina en dos días (fecha)» en la obra
 * o labor con fecha de finalización estimada (`OL-01`).
 *
 * Es el aviso del término fijo, con el mismo día y el mismo texto: lo que cambia
 * es el tipo, para que el registro de envíos no diga «término fijo» sobre una
 * obra o labor. La obra sin fecha estimada no tiene periodo de prueba y la
 * salta la clase base, porque `PeriodoDePrueba::dias()` le da cero.
 */
class PruebaObraLaborDosDias extends PruebaFijoDosDias
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::PruebaObraLaborDosDias;
    }

    /**
     * @return list<TipoContrato>
     */
    protected function modalidades(): array
    {
        return [TipoContrato::ObraLabor];
    }
}
