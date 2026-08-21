<?php

namespace App\Dominio\Calculo;

use App\Contratacion\TipoContrato;
use App\Models\Vinculacion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * El periodo de prueba de una vinculación (artículo 78 del CST).
 *
 * Dos meses en el contrato a término indefinido. En el término fijo inferior a
 * un año, la quinta parte del término pactado, sin exceder nunca esos dos meses.
 * Los tres números viven en `config/reglas_laborales.php`.
 *
 * De aquí salen las cinco alertas de periodo de prueba del capítulo 9, que son
 * la promesa que se vende: avisar antes de que se venza el plazo.
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
        $pactado = $this->duracionPactadaEnDias();

        if ($pactado === null) {
            return $this->diasDe('periodo_prueba_indefinido_meses');
        }

        // La quinta parte se trunca y no se redondea: la ley dice que el periodo
        // de prueba «no puede exceder» esa fracción, así que medio día de más ya
        // la excede. En un contrato de menos de cinco días da cero, que es lo
        // correcto: no hay periodo de prueba que quepa.
        $quintaParte = intdiv($pactado, (int) config('reglas_laborales.periodo_prueba_fijo_divisor'));

        return min($quintaParte, $this->diasDe('periodo_prueba_fijo_tope_meses'));
    }

    /**
     * El último día del periodo de prueba.
     */
    public function terminaEl(): CarbonInterface
    {
        return $this->ingreso()->addDays($this->dias() - 1);
    }

    /**
     * Los días pactados del contrato, o nulo si la modalidad no pacta plazo.
     *
     * Solo el término fijo lo tiene. En el indefinido y en la obra o labor no
     * hay fracción que calcular y el periodo de prueba es el general de dos
     * meses.
     */
    private function duracionPactadaEnDias(): ?int
    {
        $fin = $this->vinculacion->fecha_fin;

        if ($this->vinculacion->tipo_contrato !== TipoContrato::Fijo || ! $fin instanceof Carbon) {
            return null;
        }

        return MotorDeCalculo::diasCorridos($this->ingreso(), $fin);
    }

    /**
     * Cuántos días corridos son los meses de esa constante legal contados desde
     * el ingreso.
     *
     * Son dos constantes distintas con el mismo valor y se leen por separado a
     * propósito: una es el periodo de prueba del indefinido y la otra el máximo
     * absoluto que la quinta parte del fijo no puede pasar. Que hoy coincidan no
     * las vuelve la misma, y una reforma podría mover solo una.
     */
    private function diasDe(string $constante): int
    {
        $meses = (int) config('reglas_laborales.'.$constante);

        return MotorDeCalculo::diasCorridos(
            $this->ingreso(),
            MotorDeCalculo::cumpleMesesEl($this->ingreso(), $meses),
        );
    }

    private function ingreso(): Carbon
    {
        return $this->vinculacion->fecha_ingreso->copy()->startOfDay();
    }
}
