<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\HistorialDeContrato;
use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\AlertaPorEnviar;
use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\ReglaDeAlerta;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Lo que comparten las dos alertas del preaviso del término fijo
 * (documento 1, 9.4, primeras dos filas).
 *
 * Las dos cuelgan de la misma fecha —**la fecha límite del preaviso**, que son
 * treinta días antes del vencimiento vigente— y solo se diferencian en cuántos
 * días antes de ella avisan y en qué dicen. La de «plazo para definir la
 * renovación» avisa dos días antes de esa fecha; la del «efecto del silencio»,
 * el mismo día.
 *
 * ## Ningún número está escrito a mano
 *
 * El «32 días antes» del documento no existe en el código: es la fecha límite
 * del preaviso —`HistorialDeContrato::fechaLimiteDePreaviso()`, que lee
 * `preaviso_dias` de la configuración— menos los dos días que el propio texto
 * anuncia, «en dos días vence el plazo legal de 30 días». Escribir 32 sería
 * guardar dos veces el mismo número legal: el día en que el preaviso deje de
 * ser de treinta días, la alerta de los 32 quedaría mintiendo.
 *
 * ## «Si no se ha registrado prórroga ni terminación»
 *
 * Es la condición que la sección 9.4 le pone al efecto del silencio, y las dos
 * alertas la cumplen por construcción, sin preguntar nada aparte:
 *
 * - **Terminación**: registrarla deja la vinculación inactiva, y `activas()` la
 *   saca de las candidatas. Es además la regla transversal de 9.1.
 * - **Prórroga**: registrarla mueve el vencimiento vigente, y con él la fecha
 *   límite del preaviso. El disparo del plazo viejo deja de existir y aparece
 *   el del plazo nuevo, que es justo lo que corresponde: cada tramo tiene su
 *   propio preaviso.
 *
 * Preguntarlo con una condición aparte sería escribir la misma regla dos veces,
 * y la segunda copia se separaría el día que alguien corrija una prórroga.
 */
abstract class ReglaDePreavisoDelFijo implements ReglaDeAlerta
{
    /**
     * Cuánto hacia adelante se buscan vencimientos.
     *
     * El disparo más temprano de las dos cae a treinta y dos días del
     * vencimiento. Sesenta es holgura a propósito, igual que en las alertas del
     * periodo de prueba: una ventana ajustada al milímetro se rompe en silencio
     * el día en que alguien cambie los días de preaviso. Quien decide es la
     * comparación, no la consulta.
     */
    private const DIAS_DE_LA_VENTANA = 60;

    /**
     * Cuántos días antes de la fecha límite del preaviso avisa esta alerta.
     */
    abstract protected function diasAntesDelPreaviso(): int;

    abstract protected function asunto(string $nombre): string;

    abstract protected function cuerpo(
        string $nombre,
        string $cargo,
        Carbon $vencimiento,
        ReferenciaTemporal $referencia,
    ): string;

    /**
     * @return iterable<AlertaPorEnviar>
     */
    public function evaluar(Carbon $fechaDelDisparo, Carbon $fechaDeEnvio): iterable
    {
        foreach ($this->candidatas($fechaDelDisparo) as $vinculacion) {
            $historial = HistorialDeContrato::con($vinculacion->periodos);

            $vencimiento = $historial->fechaDeVencimiento();
            $limite = $historial->fechaLimiteDePreaviso();

            if (! $vencimiento instanceof Carbon || ! $limite instanceof Carbon) {
                continue;
            }

            $limite = Carbon::parse($limite->toDateString());
            $ingreso = Carbon::parse($vinculacion->fecha_ingreso->toDateString());
            $disparo = $limite->copy()->subDays($this->diasAntesDelPreaviso());

            // Un contrato pactado por menos de un mes deja el plazo del preaviso
            // en una fecha anterior a la contratación, cuando todavía no había a
            // quién avisar ni de qué. Esa alerta no se manda.
            if ($disparo->lt($ingreso) || ! $disparo->isSameDay($fechaDelDisparo)) {
                continue;
            }

            $nombre = $vinculacion->persona->nombreCompleto();
            $cargo = (string) $vinculacion->getAttribute('cargo');

            yield AlertaPorEnviar::paraEmpleado(
                $this->tipo(),
                $vinculacion,
                $fechaDeEnvio,
                $limite,
                $this->asunto($nombre),
                $this->cuerpo(
                    $nombre,
                    $cargo,
                    Carbon::parse($vencimiento->toDateString()),
                    ReferenciaTemporal::hasta($fechaDeEnvio, $limite),
                ),
            );
        }
    }

    /**
     * Los días de preaviso que la ley exige, que son los que el texto de las dos
     * alertas nombra (artículo 46 del CST, modificado por la Ley 2466 de 2025).
     */
    protected function diasDePreaviso(): int
    {
        return (int) config('reglas_laborales.preaviso_dias');
    }

    /**
     * Los contratos a término fijo activos cuyo vencimiento podría tocarle a
     * esta fecha.
     *
     * No filtra por empresa: cuando el proceso la llama, la empresa ya está en
     * contexto y el global scope hace el resto. Los tramos vienen cargados
     * porque de ellos sale el vencimiento vigente, y una consulta por candidata
     * sería una por empleado a término fijo del país.
     *
     * @return Collection<int, Vinculacion>
     */
    private function candidatas(Carbon $fechaDelDisparo): Collection
    {
        return Vinculacion::query()
            ->activas()
            ->with(['persona', 'periodos'])
            ->where('tipo_contrato', TipoContrato::Fijo->value)
            ->whereDate('fecha_fin', '>=', $fechaDelDisparo)
            ->whereDate('fecha_fin', '<=', $fechaDelDisparo->copy()->addDays(self::DIAS_DE_LA_VENTANA))
            ->get();
    }
}
