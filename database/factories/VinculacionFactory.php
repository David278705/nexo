<?php

namespace Database\Factories;

use App\Contratacion\TipoContrato;
use App\Models\Persona;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Una vinculación a término indefinido, que es la modalidad por defecto de la
 * ley desde la reforma de 2025.
 *
 * El estado no se pasa nunca, ni siquiera aquí: nace activo solo, que es la
 * regla 5.3. Una fábrica que lo pusiera a mano taparía justo lo que hay que
 * comprobar.
 *
 * @extends Factory<Vinculacion>
 */
class VinculacionFactory extends Factory
{
    protected $model = Vinculacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'persona_id' => Persona::factory(),
            'cargo' => $this->faker->jobTitle(),
            'tipo_contrato' => TipoContrato::Indefinido,
            'fecha_ingreso' => $this->faker->dateTimeBetween('-5 years', '-1 month'),
            'fecha_fin' => null,
            'salario' => $this->faker->numberBetween(1_400_000, 12_000_000),
            'bonificacion' => 0,
        ];
    }

    public function de(Persona $persona): static
    {
        return $this->state(fn () => ['persona_id' => $persona->getKey()]);
    }

    /**
     * A término fijo. Lleva fecha de finalización porque es la única modalidad
     * que la exige (documento 1, 5.4).
     */
    public function aTerminoFijo(string $ingreso = '2026-01-01', string $fin = '2026-12-31'): static
    {
        return $this->state(fn () => [
            'tipo_contrato' => TipoContrato::Fijo,
            'fecha_ingreso' => Carbon::parse($ingreso),
            'fecha_fin' => Carbon::parse($fin),
        ]);
    }

    public function deObraOLabor(): static
    {
        return $this->state(fn () => [
            'tipo_contrato' => TipoContrato::ObraLabor,
            'fecha_fin' => null,
        ]);
    }

    /**
     * Obra o labor con fecha de finalización estimada (`OL-01`), que es lo
     * único que le da periodo de prueba.
     */
    public function deObraOLaborEstimadaHasta(string $fecha): static
    {
        return $this->deObraOLabor()->state(fn () => ['fecha_fin_estimada' => Carbon::parse($fecha)]);
    }

    public function conIngreso(string $fecha): static
    {
        return $this->state(fn () => ['fecha_ingreso' => Carbon::parse($fecha)]);
    }

    /**
     * Ya terminada. La usa lo que necesita una vinculación anterior, empezando
     * por la advertencia de reingreso de F1-05.
     */
    public function terminada(): static
    {
        return $this->afterCreating(function (Vinculacion $vinculacion): void {
            $vinculacion->desactivar();
            $vinculacion->save();
        });
    }
}
