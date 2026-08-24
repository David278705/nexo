<?php

namespace Database\Factories;

use App\Models\RegistroDeCarga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistroDeCarga>
 */
class RegistroDeCargaFactory extends Factory
{
    protected $model = RegistroDeCarga::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plantilla' => 'empleados',
            'archivo' => '1 - Empleados actuales (obligatoria).xlsx',
            'filas' => $this->faker->numberBetween(1, 80),
            // Sin usuario: es la carga por consola, y no obliga a crear un
            // `owner` en cada prueba que solo mira el registro.
            'usuario_id' => null,
            'fecha_corte_saldo' => now()->toDateString(),
        ];
    }

    public function deLaPlantilla(string $clave): static
    {
        return $this->state(fn (): array => [
            'plantilla' => $clave,
            'fecha_corte_saldo' => $clave === 'empleados' ? now()->toDateString() : null,
        ]);
    }
}
