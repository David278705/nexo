<?php

namespace Database\Factories;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Un departamento cualquiera, para las pruebas que necesitan una ciudad de
 * catálogo y no los 33 de verdad.
 *
 * Los departamentos reales los carga `UbicacionesSeeder` desde
 * `database/data/ubicaciones.php`. Esta fábrica no los sustituye: existe para
 * que una prueba de personas no tenga que sembrar mil cien municipios.
 *
 * @extends Factory<Departamento>
 */
class DepartamentoFactory extends Factory
{
    protected $model = Departamento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $codigo = $this->codigoLibre();

        return [
            'codigo_dane' => $codigo,

            // El nombre sale del código y no del generador de datos falsos: en
            // español no hay lista de departamentos, y uno inventado se
            // confundiría con los 33 de verdad al leer una prueba.
            'nombre' => "Departamento de prueba {$codigo}",
            'activo' => true,
        ];
    }

    /**
     * El primer código DANE de departamento que nadie esté usando.
     *
     * Son dos dígitos, así que solo hay cien: pedírselos al generador de datos
     * falsos con `unique()` acaba agotándolos, porque su memoria dura toda la
     * ejecución de las pruebas y la base de datos se vacía entre una y otra.
     * Preguntar por lo que hay es más aburrido y no se rompe.
     */
    private function codigoLibre(): string
    {
        $usados = Departamento::withTrashed()->pluck('codigo_dane')->all();

        for ($numero = 1; $numero <= 99; $numero++) {
            $codigo = sprintf('%02d', $numero);

            if (! in_array($codigo, $usados, true)) {
                return $codigo;
            }
        }

        throw new RuntimeException('No quedan códigos de departamento libres.');
    }
}
