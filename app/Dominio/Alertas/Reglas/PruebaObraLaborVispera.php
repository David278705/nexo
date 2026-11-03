<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\TipoDeAlerta;

/**
 * «Mañana (fecha) es el último día del periodo de prueba» en la obra o labor con
 * fecha de finalización estimada (`OL-01`).
 *
 * Mismo texto y misma víspera que la del término fijo y la del indefinido, que
 * comparten `ReglaDeVisperaDePrueba`; lo propio es a quién mira.
 */
class PruebaObraLaborVispera extends ReglaDeVisperaDePrueba
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::PruebaObraLaborVispera;
    }

    /**
     * @return list<TipoContrato>
     */
    protected function modalidades(): array
    {
        return [TipoContrato::ObraLabor];
    }
}
