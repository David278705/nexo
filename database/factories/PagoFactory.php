<?php

namespace Database\Factories;

use App\Models\Pago;
use App\Models\Suscripcion;
use App\Proveedor\EstadoDeFactura;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'suscripcion_id' => Suscripcion::factory(),
            'monto' => 380000,
            'fecha' => Carbon::today(),
            'estado' => EstadoDeFactura::Pagada->value,
            'numero_factura' => $this->faker->unique()->numerify('FE-####'),
        ];
    }

    public function anulada(): static
    {
        return $this->state(fn () => ['estado' => EstadoDeFactura::Anulada->value]);
    }
}
