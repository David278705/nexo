<?php

namespace Tests\Fixtures;

use App\Dominio\Alertas\AlertaPorEnviar;
use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\ReglaDeAlerta;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;

/**
 * Una regla de disparo cualquiera, para probar la infraestructura sin esperar
 * a que existan las reglas de verdad.
 *
 * Es el equivalente de `RegistroDePrueba` en el aislamiento y de
 * `ListadoDePrueba` en los listados: lo que F3-01 construye es el armazón —el
 * recorrido de empresas, la idempotencia, la cola y la plantilla—, y el armazón
 * se prueba con una regla de mentira. Las once de verdad llegan en F3-03, F3-04
 * y F3-05 y no cambian nada de esto.
 *
 * Dispara sobre **todas las vinculaciones activas de la empresa en contexto**
 * cuando la fecha del disparo es la que se le pidió. No filtra por empresa a
 * mano: si el proceso no fijara el contexto, esta regla devolvería empleados de
 * todas y las pruebas de aislamiento se pondrían rojas, que es justo lo que
 * tienen que hacer.
 */
class ReglaDePrueba implements ReglaDeAlerta
{
    /**
     * Las fechas de disparo con las que la regla fue llamada, para poder
     * comprobar qué días evalúa el proceso.
     *
     * @var list<string>
     */
    public array $fechasEvaluadas = [];

    /**
     * `$dispara` es la fecha en la que la regla encuentra a alguien, o `*` para
     * que encuentre siempre —lo que hace falta para comprobar que la clave de
     * un día no estorba la del siguiente—.
     */
    public function __construct(
        private readonly string $dispara,
        private readonly TipoDeAlerta $tipo = TipoDeAlerta::PruebaIndefinidoVispera,
    ) {}

    public function tipo(): TipoDeAlerta
    {
        return $this->tipo;
    }

    /**
     * @return iterable<AlertaPorEnviar>
     */
    public function evaluar(Carbon $fechaDelDisparo, Carbon $fechaDeEnvio): iterable
    {
        $this->fechasEvaluadas[] = $fechaDelDisparo->toDateString();

        if ($this->dispara !== '*' && $fechaDelDisparo->toDateString() !== $this->dispara) {
            return [];
        }

        $empresa = app(ContextoTenant::class)->tenant()?->getAttribute('nombre');

        return Vinculacion::query()
            ->activas()
            ->get()
            ->map(fn (Vinculacion $vinculacion): AlertaPorEnviar => AlertaPorEnviar::paraEmpleado(
                $this->tipo,
                $vinculacion,
                $fechaDeEnvio,
                $fechaDelDisparo,
                'Aviso de prueba de '.$empresa,
                // La frase la escribe `ReferenciaTemporal`, igual que las once
                // de verdad: así el corrimiento al día hábil anterior (F3-02)
                // se comprueba de punta a punta y no solo en el unitario.
                ReferenciaTemporal::hasta($fechaDeEnvio, $fechaDelDisparo)->fraseCapitalizada()
                    .' pasa algo en '.$empresa.'.',
            ))
            ->all();
    }
}
