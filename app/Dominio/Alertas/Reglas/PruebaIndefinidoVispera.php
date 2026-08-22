<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\TipoDeAlerta;

/**
 * «Mañana (fecha) es el último día del periodo de prueba» en el contrato a
 * término indefinido (documento 1, 9.2, tercera fila).
 *
 * El texto lo pone `ReglaDeVisperaDePrueba`, que lo comparte con la del término
 * fijo. Lo suyo es a quién mira: las modalidades cuyo periodo de prueba es el
 * general de dos meses.
 */
class PruebaIndefinidoVispera extends ReglaDeVisperaDePrueba
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::PruebaIndefinidoVispera;
    }

    /**
     * @return list<TipoContrato>
     */
    protected function modalidades(): array
    {
        return self::PRUEBA_GENERAL;
    }
}
