<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * Un plan cualquiera para las pruebas.
     *
     * El precio es de mentira a propósito y no hay que leerlo como una
     * propuesta: la tabla real es la decisión A.1 del documento 6, que sigue
     * aplazada, y el catálogo de producción nace vacío.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->randomElement(['Micro', 'Pequeña', 'Mediana', 'Grande'])
                .' '.$this->faker->unique()->numerify('##'),
            'precio_mensual' => 380000,
            'empleados_incluidos' => 50,
            'usuarios_incluidos' => 2,
            'activo' => true,
        ];
    }

    public function desactivado(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
