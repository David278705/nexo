<?php

namespace Database\Factories;

use App\Models\Ciudad;
use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un municipio cualquiera, con su departamento detrás.
 *
 * Sirve a las pruebas de personas: la residencia siempre apunta al catálogo, y
 * sin una ciudad no se puede guardar a nadie.
 *
 * @extends Factory<Ciudad>
 */
class CiudadFactory extends Factory
{
    protected $model = Ciudad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'departamento_id' => Departamento::factory(),
            'nombre' => $this->faker->city(),
            'activo' => true,
        ];
    }

    public function de(Departamento $departamento): static
    {
        return $this->state(fn () => ['departamento_id' => $departamento->getKey()]);
    }
}
