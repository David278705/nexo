<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company(),
            'nit' => $this->faker->unique()->numerify('9########-#'),
            'telefono' => $this->faker->numerify('60########'),
            // Sin ciudad: es una referencia al catálogo de municipios y crear
            // uno por cada empresa de prueba costaría dos inserciones más en
            // cada una de las que no la miran. Quien la necesite la pone.
            'ciudad_id' => null,
            'estado' => Tenant::ESTADO_ACTIVA,
            'sabado_laboral' => false,
            'suspensiones_afectan_vacaciones' => true,
            'perfil_sociodemografico_habilitado' => false,
        ];
    }

    public function suspendida(): static
    {
        return $this->state(fn () => ['estado' => Tenant::ESTADO_SUSPENDIDA]);
    }

    public function enPrueba(): static
    {
        return $this->state(fn () => ['estado' => Tenant::ESTADO_EN_PRUEBA]);
    }

    /**
     * Una empresa con su plan pactado, que es como nacen todas las creadas
     * desde el panel. Sin él, el listado dice «Sin plan pactado».
     */
    public function conPlan(string $nombre = 'Pequeña', int $empleados = 50, int $usuarios = 2): static
    {
        return $this->state(fn () => [
            'plan_nombre' => $nombre,
            'plan_empleados_incluidos' => $empleados,
            'plan_usuarios_incluidos' => $usuarios,
            'plan_inicia_el' => '2026-08-01',
        ]);
    }

    /**
     * Hace cuántos días entró alguien de la empresa por última vez.
     *
     * Es lo único de lo que depende el indicador de salud de uso del listado del
     * panel, así que las pruebas de esas tres franjas se escriben con esto.
     */
    public function conUltimoIngresoHace(int $dias): static
    {
        return $this->state(fn () => [
            'ultimo_acceso_en' => Carbon::now()->startOfDay()->subDays($dias),
        ]);
    }

    public function conSabadoLaboral(): static
    {
        return $this->state(fn () => ['sabado_laboral' => true]);
    }

    /**
     * El ambiente de demostración (documento 4, capítulo 10, F5-03). Solo una
     * empresa de todas las que existan puede llevar esta bandera: el índice
     * único parcial de la migración lo impide si una prueba intenta crear dos.
     */
    public function ambienteDeDemo(): static
    {
        return $this->state(fn () => [
            'es_ambiente_demo' => true,
            'estado' => Tenant::ESTADO_EN_PRUEBA,
        ]);
    }
}
