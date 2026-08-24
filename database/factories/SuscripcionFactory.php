<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Proveedor\EstadoDeSuscripcion;
use App\Proveedor\MedioDePago;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Suscripcion>
 */
class SuscripcionFactory extends Factory
{
    protected $model = Suscripcion::class;

    /**
     * Una suscripción al día: el próximo cobro está lo bastante lejos como para
     * no caer en «por vencer», que es lo que hace falta para que las pruebas de
     * cada franja se escriban con `conCobroEl()` y no dependan del día en que se
     * ejecuten.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'inicia_el' => Carbon::today()->subMonths(3),
            'plan_aplica_desde' => Carbon::today()->subMonths(3),
            'proxima_fecha_cobro' => Carbon::today()->addMonth(),
            'medio_de_pago' => MedioDePago::Transferencia->value,
            'estado' => EstadoDeSuscripcion::Activa->value,
        ];
    }

    /**
     * Cuántos días de mora lleva. Es lo único de lo que depende el indicador de
     * pago y el aviso de la escalera.
     */
    public function conMoraDe(int $dias): static
    {
        return $this->state(fn () => [
            'proxima_fecha_cobro' => Carbon::today()->subDays($dias),
        ]);
    }

    public function conCobroEn(int $dias): static
    {
        return $this->state(fn () => [
            'proxima_fecha_cobro' => Carbon::today()->addDays($dias),
        ]);
    }

    public function suspendida(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoDeSuscripcion::Suspendida->value,
            'suspendida_en' => Carbon::now(),
            'motivo_de_suspension' => 'Mora de más de treinta días, sin acuerdo de pago.',
        ]);
    }
}
