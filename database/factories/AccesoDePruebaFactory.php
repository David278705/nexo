<?php

namespace Database\Factories;

use App\Models\AccesoDePrueba;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccesoDePrueba>
 */
class AccesoDePruebaFactory extends Factory
{
    protected $model = AccesoDePrueba::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dias = $this->faker->numberBetween(7, 15);

        return [
            'tenant_id' => Tenant::factory()->ambienteDeDemo(),
            'nombre_prospecto' => $this->faker->company(),
            'token' => hash('sha256', Str::random(64)),
            'dias_de_vigencia' => $dias,
            'generado_en' => Carbon::now(),
            'expira_en' => Carbon::now()->addDays($dias),
        ];
    }

    public function vencido(): static
    {
        return $this->state(fn () => [
            'generado_en' => Carbon::now()->subDays(20),
            'expira_en' => Carbon::now()->subDay(),
        ]);
    }

    public function revocado(): static
    {
        return $this->state(fn () => ['revocado_en' => Carbon::now()]);
    }
}
