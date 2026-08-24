<?php

namespace Database\Factories;

use App\Models\ErrorDeAplicacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ErrorDeAplicacion>
 */
class ErrorDeAplicacionFactory extends Factory
{
    protected $model = ErrorDeAplicacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ocurrido_en' => Carbon::now(),
            'clase' => \RuntimeException::class,
            'mensaje' => $this->faker->sentence(),
            'archivo' => 'app/Dominio/Calculo/MotorDeCalculo.php',
            'linea' => $this->faker->numberBetween(1, 300),
            'contexto' => 'web',
        ];
    }
}
