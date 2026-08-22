<?php

namespace Database\Factories;

use App\Licencias\TipoDeLicencia;
use App\Models\Licencia;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Licencia>
 */
class LicenciaFactory extends Factory
{
    protected $model = Licencia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'vinculacion_id' => Vinculacion::factory(),
            'tipo' => TipoDeLicencia::Ausencia,
            'fecha_inicio' => $inicio,
            'fecha_fin' => Carbon::instance($inicio)->addDays($this->faker->numberBetween(0, 10)),
        ];
    }

    public function de(Vinculacion $vinculacion): static
    {
        return $this->state(fn () => ['vinculacion_id' => $vinculacion->getKey()]);
    }

    /**
     * Del jueves al martes siguiente, con el domingo dentro: el caso
     * obligatorio del motor, «seis días y seis días de suspensión».
     */
    public function delJuevesAlMartes(int $anio = 2026): static
    {
        return $this->state(fn () => [
            'fecha_inicio' => Carbon::create($anio, 7, 2),
            'fecha_fin' => Carbon::create($anio, 7, 7),
        ]);
    }

    /**
     * Del 16 de marzo al 15 de abril: el caso obligatorio que reduce a la
     * mitad la acumulación de los dos meses.
     */
    public function del16DeMarzoAl15DeAbril(int $anio = 2026): static
    {
        return $this->state(fn () => [
            'fecha_inicio' => Carbon::create($anio, 3, 16),
            'fecha_fin' => Carbon::create($anio, 4, 15),
        ]);
    }
}
