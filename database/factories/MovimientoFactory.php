<?php

namespace Database\Factories;

use App\Models\Movimiento;
use App\Models\Vinculacion;
use App\Movimientos\TipoDeMovimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movimiento>
 */
class MovimientoFactory extends Factory
{
    protected $model = Movimiento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cargo = $this->faker->jobTitle();
        $salario = $this->faker->numberBetween(1_500_000, 4_000_000);

        return [
            'vinculacion_id' => Vinculacion::factory(),
            'tipo' => TipoDeMovimiento::AumentoSalarial,
            'cargo_anterior' => $cargo,
            'cargo_nuevo' => $cargo,
            'salario_anterior' => $salario,
            'salario_nuevo' => $salario + 200_000,
            'fecha_efectividad' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function de(Vinculacion $vinculacion): static
    {
        return $this->state(fn () => ['vinculacion_id' => $vinculacion->getKey()]);
    }

    /**
     * El caso del prototipo: la promoción de Carolina Restrepo, que cambia de
     * cargo y de salario a la vez.
     */
    public function promocion(string $cargoAnterior, string $cargoNuevo): static
    {
        return $this->state(fn () => [
            'tipo' => TipoDeMovimiento::Promocion,
            'cargo_anterior' => $cargoAnterior,
            'cargo_nuevo' => $cargoNuevo,
        ]);
    }
}
