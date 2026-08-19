<?php

namespace Database\Factories;

use App\Contratacion\EventoContractual;
use App\Contratacion\TipoContrato;
use App\Models\PeriodoContractual;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Un tramo del historial del contrato.
 *
 * Por defecto es una prórroga pactada, y no el tramo inicial, a propósito: el
 * inicial lo abre sola la vinculación al crearse, así que una fábrica que lo
 * repitiera crearía dos tramos con el mismo número. Lo que hace falta escribir
 * a mano en una prueba es siempre lo que viene después.
 *
 * El `orden` tampoco se inventa: se toma del historial que ya tiene la
 * vinculación, igual que hace `Vinculacion::registrarPeriodo()`.
 *
 * @extends Factory<PeriodoContractual>
 */
class PeriodoContractualFactory extends Factory
{
    protected $model = PeriodoContractual::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vinculacion_id' => Vinculacion::factory()->aTerminoFijo(),
            'orden' => fn (array $atributos) => $this->proximoOrdenDe($atributos),
            'evento' => EventoContractual::ProrrogaPactada,
            'tipo_contrato' => TipoContrato::Fijo,
            'fecha_inicio' => Carbon::parse('2027-01-01'),
            'fecha_fin' => Carbon::parse('2027-12-31'),
        ];
    }

    public function de(Vinculacion $vinculacion): static
    {
        return $this->state(fn () => ['vinculacion_id' => $vinculacion->getKey()]);
    }

    public function entre(string $inicio, string $fin): static
    {
        return $this->state(fn () => [
            'fecha_inicio' => Carbon::parse($inicio),
            'fecha_fin' => Carbon::parse($fin),
        ]);
    }

    public function automatica(): static
    {
        return $this->state(fn () => ['evento' => EventoContractual::ProrrogaAutomatica]);
    }

    /**
     * El paso a indefinido, que no es una prórroga y por eso no lleva fecha de
     * finalización.
     */
    public function cambioAIndefinido(string $desde = '2028-01-01'): static
    {
        return $this->state(fn () => [
            'evento' => EventoContractual::CambioAIndefinido,
            'tipo_contrato' => TipoContrato::Indefinido,
            'fecha_inicio' => Carbon::parse($desde),
            'fecha_fin' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function proximoOrdenDe(array $atributos): int
    {
        $vinculacion = $atributos['vinculacion_id'] ?? null;

        if (! is_numeric($vinculacion)) {
            return 1;
        }

        return (int) PeriodoContractual::query()
            ->where('vinculacion_id', (int) $vinculacion)
            ->max('orden') + 1;
    }
}
