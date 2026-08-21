<?php

namespace Database\Factories;

use App\Incapacidades\OrigenIncapacidad;
use App\Models\Eps;
use App\Models\Incapacidad;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Incapacidad>
 */
class IncapacidadFactory extends Factory
{
    protected $model = Incapacidad::class;

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
            'codigo_diagnostico' => $this->faker->bothify('?##.#'),
            'es_prorroga' => false,
            'eps_id' => fn () => Eps::query()->create([
                'nombre' => $this->faker->unique()->company(),
                'activo' => true,
            ])->id,
            'origen' => OrigenIncapacidad::Comun,
        ];
    }

    public function de(Vinculacion $vinculacion): static
    {
        return $this->state(fn () => ['vinculacion_id' => $vinculacion->getKey()]);
    }

    /**
     * Del 14 al 16 de julio de un año dado: el caso obligatorio de la sección
     * 6.3, «son tres días».
     */
    public function del14al16DeJulio(int $anio = 2026): static
    {
        return $this->state(fn () => [
            'fecha_inicio' => Carbon::create($anio, 7, 14),
            'fecha_fin' => Carbon::create($anio, 7, 16),
        ]);
    }

    public function deOrigenLaboral(): static
    {
        return $this->state(fn () => ['origen' => OrigenIncapacidad::Laboral]);
    }
}
