<?php

namespace App\Dominio\Calculo;

use App\Models\Vinculacion;
use Carbon\CarbonInterface;

/**
 * El periodo de prueba de una vinculación (artículo 78 del CST).
 *
 * Dos meses en el contrato a término indefinido. En el término fijo inferior a
 * un año, la quinta parte del término pactado, sin exceder nunca esos dos meses.
 * Los tres números viven en `config/reglas_laborales.php`.
 *
 * De aquí salen las cinco alertas de periodo de prueba del capítulo 9, que son
 * la promesa que se vende: avisar antes de que se venza el plazo.
 *
 * Como el resto del motor, F2-01 escribe la firma y los casos; F2-02 la cuenta.
 */
class PeriodoDePrueba
{
    private function __construct(public readonly Vinculacion $vinculacion) {}

    public static function de(Vinculacion $vinculacion): self
    {
        return new self($vinculacion);
    }

    /**
     * Cuántos días dura el periodo de prueba, contando el día de ingreso.
     */
    public function dias(): int
    {
        throw CalculoSinImplementar::en(__METHOD__);
    }

    /**
     * El último día del periodo de prueba.
     */
    public function terminaEl(): CarbonInterface
    {
        throw CalculoSinImplementar::en(__METHOD__);
    }
}
