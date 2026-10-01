<?php

namespace Database\Factories;

use App\Models\AceptacionLegal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AceptacionLegal>
 */
class AceptacionLegalFactory extends Factory
{
    protected $model = AceptacionLegal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // El usuario es de la empresa en contexto: la factory se usa dentro
            // de `paraTenant()`, igual que la de cualquier tabla de negocio.
            'user_id' => fn () => User::factory()->create(['tenant_id' => tenant_actual_id()])->id,
            'documento' => 'terminos',
            'version' => '1',
            'ip' => '203.0.113.9',
            'agente' => 'Mozilla/5.0',
            'aceptado_en' => Carbon::now(),
        ];
    }
}
