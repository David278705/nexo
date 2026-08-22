<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\TipoDeAlerta;

/**
 * «Mañana (fecha) es el último día del periodo de prueba» en el contrato a
 * término fijo (documento 1, 9.3, segunda fila).
 *
 * Mismo texto que la del indefinido y misma víspera; lo que cambia es que aquí
 * el final del periodo de prueba no son dos meses fijos sino la quinta parte
 * del plazo pactado, que ya calcula `PeriodoDePrueba`.
 */
class PruebaFijoVispera extends ReglaDeVisperaDePrueba
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::PruebaFijoVispera;
    }

    /**
     * @return list<TipoContrato>
     */
    protected function modalidades(): array
    {
        return [TipoContrato::Fijo];
    }
}
