<?php

namespace Database\Factories;

use App\Dominio\Alertas\EstadoDeAlerta;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Models\AlertaEnviada;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AlertaEnviada>
 */
class AlertaEnviadaFactory extends Factory
{
    protected $model = AlertaEnviada::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = Carbon::instance($this->faker->dateTimeBetween('-2 months', 'now'))->startOfDay();
        $tipo = TipoDeAlerta::PruebaIndefinidoVispera;

        return [
            // Sin vinculación: es el caso de los resúmenes mensuales y el que
            // no obliga a crear una persona y un contrato en cada prueba que
            // solo mira el registro de envíos. Quien la necesite usa `de()`.
            'vinculacion_id' => null,
            'tipo' => $tipo,
            'clave' => $tipo->value.'::'.$fecha->toDateString(),
            'fecha_programada' => $fecha,
            'fecha_del_hecho' => $fecha->copy()->addDay(),
            'asunto' => 'Mañana vence el periodo de prueba',
            'cuerpo' => 'Mañana ('.$fecha->copy()->addDay()->toDateString().') es el último día del periodo de prueba.',
            'destinatarios' => ['gestion.humana@ejemplo.co'],
            'estado' => EstadoDeAlerta::Pendiente,
        ];
    }

    public function de(Vinculacion $vinculacion): static
    {
        return $this->state(fn (array $atributos) => [
            'vinculacion_id' => $vinculacion->getKey(),
            'clave' => $atributos['tipo']->value.':'.$vinculacion->getKey().':'.Carbon::parse($atributos['fecha_programada'])->toDateString(),
        ]);
    }

    public function enviada(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoDeAlerta::Enviada,
            'intentos' => 1,
            'enviada_en' => Carbon::now(),
        ]);
    }
}
