<?php

namespace Database\Factories;

use App\Models\Vacacion;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Vacacion>
 */
class VacacionFactory extends Factory
{
    protected $model = Vacacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'vinculacion_id' => Vinculacion::factory(),
            'fecha_inicio' => $inicio,
            'fecha_fin' => Carbon::instance($inicio)->addDays($this->faker->numberBetween(0, 10)),
        ];
    }

    public function de(Vinculacion $vinculacion): static
    {
        return $this->state(fn () => ['vinculacion_id' => $vinculacion->getKey()]);
    }

    /**
     * Del lunes 27 al viernes 31 de julio de un año dado, sin festivos en el
     * medio: cinco días hábiles si el sábado no es laboral.
     */
    public function del27al31DeJulio(int $anio = 2026): static
    {
        return $this->state(fn () => [
            'fecha_inicio' => Carbon::create($anio, 7, 27),
            'fecha_fin' => Carbon::create($anio, 7, 31),
        ]);
    }
}
