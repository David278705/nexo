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
 * **La obra o labor se calcula como el término fijo, sobre la fecha estimada**
 * (`OL-01`, decidido por David el 2026-11-02). Si la empresa no la registró, no
 * hay periodo de prueba: `dias()` da cero, que es lo mismo que ya pasa con un
 * fijo de menos de cinco días, y todo lo que lee este cálculo ya sabe saltarlo.
 * Es un periodo **sugerido**: el sistema no puede saber si la fecha estimada se
 * ajusta a la obra real, y por eso `esEstimado()` existe para que cada pantalla
 * y cada correo lo digan.
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
        if ($this->esObraOLabor() && ! $this->vinculacion->fecha_fin_estimada instanceof Carbon) {
            return 0;
        }

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
     * Si hay un periodo de prueba que calcular.
     */
    public function aplica(): bool
    {
        return $this->dias() >= 1;
    }

    /**
     * Si el periodo sale de una fecha que estimó la empresa y no de un plazo
     * pactado: solo la obra o labor con fecha estimada.
     */
    public function esEstimado(): bool
    {
        return $this->esObraOLabor() && $this->aplica();
    }

    /**
     * Los días del contrato sobre los que se toma la quinta parte, o nulo si
     * la modalidad no tiene esa fracción.
     *
     * El término fijo usa su vencimiento pactado; la obra o labor, su fecha
     * estimada. En el indefinido no hay fracción y el periodo de prueba es el
     * general de dos meses.
     */
    private function duracionPactadaEnDias(): ?int
    {
        $fin = match ($this->vinculacion->tipo_contrato) {
            TipoContrato::Fijo => $this->vinculacion->fecha_fin,
            TipoContrato::ObraLabor => $this->vinculacion->fecha_fin_estimada,
            default => null,
        };

        if (! $fin instanceof Carbon) {
            return null;
        }

        return MotorDeCalculo::diasCorridos($this->ingreso(), $fin);
    }

    private function esObraOLabor(): bool
    {
        return $this->vinculacion->tipo_contrato === TipoContrato::ObraLabor;
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
