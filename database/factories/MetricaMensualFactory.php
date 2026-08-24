<?php

namespace Database\Factories;

use App\Models\MetricaMensual;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<MetricaMensual>
 */
class MetricaMensualFactory extends Factory
{
    protected $model = MetricaMensual::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mes' => Carbon::now()->subMonthNoOverflow()->format('Y-m'),
            'empresas_activas' => $this->faker->numberBetween(1, 20),
            'ingreso_recurrente' => $this->faker->numberBetween(500000, 5000000),
            'capturado_en' => Carbon::now(),
        ];
    }
}
