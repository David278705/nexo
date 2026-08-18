<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company(),
            'nit' => $this->faker->unique()->numerify('9########-#'),
            'telefono' => $this->faker->numerify('60########'),
            'ciudad' => $this->faker->city(),
            'estado' => Tenant::ESTADO_ACTIVA,
            'sabado_laboral' => false,
            'suspensiones_afectan_vacaciones' => true,
            'perfil_sociodemografico_habilitado' => false,
        ];
    }

    public function suspendida(): static
    {
        return $this->state(fn () => ['estado' => Tenant::ESTADO_SUSPENDIDA]);
    }

    public function conSabadoLaboral(): static
    {
        return $this->state(fn () => ['sabado_laboral' => true]);
    }
}
