<?php

namespace Database\Factories;

use App\Models\Terminacion;
use App\Models\Vinculacion;
use App\Terminaciones\TipoDeSalida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Terminacion>
 */
class TerminacionFactory extends Factory
{
    protected $model = Terminacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vinculacion_id' => Vinculacion::factory(),
            'tipo_salida' => TipoDeSalida::Renuncia,
            'motivo' => 'No informó el motivo',
            'fecha_salida' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'observaciones' => null,
        ];
    }

    public function de(Vinculacion $vinculacion): static
    {
        return $this->state(fn () => ['vinculacion_id' => $vinculacion->getKey()]);
    }

    /**
     * Una salida de un tipo concreto, con un motivo que sea de ese tipo: el
     * primero de su lista si no se pide otro.
     */
    public function deTipo(TipoDeSalida $tipo, ?string $motivo = null): static
    {
        return $this->state(fn () => [
            'tipo_salida' => $tipo,
            'motivo' => $motivo ?? $tipo->motivos()[0],
        ]);
    }
}
