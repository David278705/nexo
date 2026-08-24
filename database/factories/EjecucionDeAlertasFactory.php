<?php

namespace Database\Factories;

use App\Models\EjecucionDeAlertas;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<EjecucionDeAlertas>
 */
class EjecucionDeAlertasFactory extends Factory
{
    protected $model = EjecucionDeAlertas::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => Carbon::today(),
            'ejecutada_en' => Carbon::now(),
            'duracion_ms' => $this->faker->numberBetween(500, 5000),
            'empresas' => $this->faker->numberBetween(1, 20),
            'alertas_nuevas' => $this->faker->numberBetween(0, 30),
            'alertas_repetidas' => 0,
            'sin_destinatarios' => 0,
            'saldos_refrescados' => $this->faker->numberBetween(0, 100),
            'empresas_fallidas' => 0,
            'detalle_fallidas' => [],
            'exitosa' => true,
        ];
    }

    public function conFallos(): static
    {
        return $this->state(fn (): array => [
            'empresas_fallidas' => 1,
            'detalle_fallidas' => [['empresa' => 'Textiles Aurora Ltda.', 'motivo' => 'Fecha de ingreso inválida.']],
            'exitosa' => false,
        ]);
    }
}
