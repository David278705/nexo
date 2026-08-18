<?php

namespace Database\Factories;

use App\Contratacion\TipoVivienda;
use App\Models\PerfilSociodemografico;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un perfil sociodemográfico.
 *
 * Solo trae los campos cuyas opciones están decididas —tipo de vivienda,
 * estrato y los dos numéricos—. Estado civil, nivel educativo, medio de
 * transporte y tiempo de desplazamiento se quedan vacíos: sus listas son la
 * decisión D.5 y llenarlos aquí sería inventarlas.
 *
 * Eso no deja la fábrica coja, porque **todos los campos del bloque son
 * opcionales por regla**: un perfil a medio llenar es exactamente lo que el
 * sistema tiene que aceptar.
 *
 * @extends Factory<PerfilSociodemografico>
 */
class PerfilSociodemograficoFactory extends Factory
{
    protected $model = PerfilSociodemografico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'persona_id' => Persona::factory(),
            'numero_hijos' => $this->faker->numberBetween(0, 4),
            'personas_a_cargo' => $this->faker->numberBetween(0, 4),
            'tipo_vivienda' => $this->faker->randomElement(TipoVivienda::cases()),
            'estrato' => $this->faker->numberBetween(1, 6),
        ];
    }

    public function de(Persona $persona): static
    {
        return $this->state(fn () => ['persona_id' => $persona->getKey()]);
    }

    /**
     * Sin un solo campo lleno, que es un registro válido.
     */
    public function vacio(): static
    {
        return $this->state(fn () => [
            'numero_hijos' => null,
            'personas_a_cargo' => null,
            'tipo_vivienda' => null,
            'estrato' => null,
        ]);
    }
}
