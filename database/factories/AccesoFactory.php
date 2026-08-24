<?php

namespace Database\Factories;

use App\Autenticacion\ResultadoDeAcceso;
use App\Models\Acceso;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Acceso>
 */
class AccesoFactory extends Factory
{
    protected $model = Acceso::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'correo' => $this->faker->safeEmail(),
            'resultado' => ResultadoDeAcceso::Exitoso->value,
            'ip' => $this->faker->ipv4(),
            'agente' => 'Pest',
        ];
    }

    public function fallido(): static
    {
        return $this->state(fn (): array => ['resultado' => ResultadoDeAcceso::Fallido->value]);
    }

    /**
     * Cuándo ocurrió. Va en `afterCreating()` y no en la definición porque
     * `created_at` no es un campo asignable en masa —un acceso no lo escribe
     * nadie más que el propio inicio de sesión— y esto es solo para poder
     * probar comparaciones entre ventanas de tiempo.
     */
    public function el(Carbon $fecha): static
    {
        return $this->afterCreating(fn (Acceso $acceso) => $acceso->forceFill(['created_at' => $fecha])->save());
    }
}
