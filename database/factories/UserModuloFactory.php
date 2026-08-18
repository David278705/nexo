<?php

namespace Database\Factories;

use App\Autorizacion\Modulo;
use App\Models\UserModulo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserModulo>
 */
class UserModuloFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modulo' => fake()->randomElement(Modulo::valoresAsignables()),
        ];
    }

    public function de(Modulo $modulo): static
    {
        return $this->state(fn () => ['modulo' => $modulo->value]);
    }
}
