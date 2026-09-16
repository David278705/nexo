<?php

namespace Database\Factories;

use App\Contratacion\TipoDocumento;
use App\Models\Ciudad;
use App\Models\Pais;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una persona colombiana corriente: nacida y residente en el país, con cédula
 * de ciudadanía.
 *
 * La empresa no se pasa nunca: la pone el trait `PerteneceATenant` desde el
 * contexto. Una prueba que quiera crear personas de dos empresas envuelve cada
 * llamada en `ContextoTenant::paraTenant()`, que es como se hace en el resto
 * del sistema.
 *
 * Los estados `nacidaEnElExterior()` y `sinSegundosNombres()` existen porque
 * son los dos casos que más se equivocan: el primero guarda texto libre en vez
 * de identificadores de catálogo, y el segundo comprueba que el nombre completo
 * no salga con dobles espacios.
 *
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    /**
     * La ciudad que comparten las tres ubicaciones de una misma persona.
     *
     * Se recuerda para no crear tres municipios distintos por cada persona de
     * prueba, que además agotarían los nombres únicos del generador.
     */
    private ?Ciudad $ciudad = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ciudad = $this->ciudadDeCatalogo();

        return [
            'tipo_documento' => TipoDocumento::CedulaCiudadania,
            'numero_documento' => $this->faker->unique()->numerify('##########'),
            'fecha_expedicion_documento' => $this->faker->dateTimeBetween('-20 years', '-2 years'),

            'primer_nombre' => $this->faker->firstName(),
            'segundo_nombre' => $this->faker->firstName(),
            'primer_apellido' => $this->faker->lastName(),
            'segundo_apellido' => $this->faker->lastName(),

            'fecha_nacimiento' => $this->faker->dateTimeBetween('-60 years', '-18 years'),

            // `genero` se queda sin valor: sus opciones son la decisión D.5 y
            // una fábrica no es sitio para inventarlas.

            'pais_nacimiento' => $this->colombia(),
            'ciudad_nacimiento_id' => $ciudad->getKey(),

            'pais_expedicion' => $this->colombia(),
            'ciudad_expedicion_id' => $ciudad->getKey(),

            'direccion' => $this->faker->streetAddress(),
            'ciudad_residencia_id' => $ciudad->getKey(),

            'celular' => $this->faker->numerify('3#########'),
            'correo' => $this->faker->unique()->safeEmail(),
        ];
    }

    /**
     * La otra mitad de la regla 5.1.1: fuera de Colombia no hay catálogo, se
     * digitan los dos textos y los identificadores quedan vacíos.
     */
    public function nacidaEnElExterior(string $pais = 'VE'): static
    {
        return $this->state(function () use ($pais): array {
            Pais::query()->firstOrCreate(
                ['codigo' => $pais],
                ['nombre' => 'País del exterior '.$pais]
            );

            return [
                'pais_nacimiento' => $pais,
                'ciudad_nacimiento_id' => null,
                'departamento_nacimiento_exterior' => 'Zulia',
                'ciudad_nacimiento_exterior' => 'Maracaibo',
            ];
        });
    }

    public function sinSegundosNombres(): static
    {
        return $this->state(fn () => [
            'segundo_nombre' => null,
            'segundo_apellido' => null,
        ]);
    }

    public function conDocumento(string $numeroDeDocumento): static
    {
        return $this->state(fn () => ['numero_documento' => $numeroDeDocumento]);
    }

    private function ciudadDeCatalogo(): Ciudad
    {
        return $this->ciudad ??= Ciudad::factory()->create();
    }

    /**
     * Colombia tiene que existir en el catálogo: las tres ubicaciones apuntan a
     * `paises.codigo` con clave foránea.
     */
    private function colombia(): string
    {
        Pais::query()->firstOrCreate(
            ['codigo' => Pais::COLOMBIA],
            ['nombre' => 'Colombia']
        );

        return Pais::COLOMBIA;
    }
}
