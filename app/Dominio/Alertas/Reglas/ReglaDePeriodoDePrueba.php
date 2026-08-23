<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\AlertaPorEnviar;
use App\Dominio\Alertas\ReferenciaTemporal;
use App\Dominio\Alertas\ReglaDeAlerta;
use App\Dominio\Calculo\PeriodoDePrueba;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Lo que comparten las cinco alertas del periodo de prueba (documento 1, 9.2 y 9.3).
 *
 * Las cinco hacen lo mismo salvo tres cosas: sobre qué modalidades de contrato
 * miran, qué día disparan y qué dicen. Todo lo demás —quién es candidato, que
 * el empleado esté activo, de dónde sale la fecha del periodo de prueba y cómo
 * se arma la alerta— se escribe una vez aquí. Escrito cinco veces, la que se
 * olvidara de `activas()` mandaría correos sobre gente que ya se fue.
 *
 * ## La fecha del hecho es siempre el último día del periodo de prueba
 *
 * Es el `{fecha}` de las cinco plantillas de la sección 5.4: la que va entre
 * paréntesis en cuatro de ellas y la que la primera escribe entera. Sale de
 * `PeriodoDePrueba::terminaEl()`, que ya sabe que en el indefinido son dos
 * meses y en el fijo corto una quinta parte del plazo pactado. Ninguna regla
 * vuelve a contar días.
 *
 * ## Por qué se filtra por ventana y se confirma en PHP
 *
 * El día de disparo no se puede escribir en SQL: depende de la modalidad, del
 * plazo pactado y de la aritmética de meses sin desbordamiento. Así que la
 * consulta se queda con los ingresos de los últimos meses —la ventana más
 * ancha en la que puede caer cualquiera de las cinco, que como mucho miran dos
 * meses hacia atrás— y la fecha exacta se confirma después con el motor de
 * cálculo. La ventana es una optimización; **quien decide es la comparación**,
 * y por eso sobra margen.
 */
abstract class ReglaDePeriodoDePrueba implements ReglaDeAlerta
{
    /**
     * Cuánto hacia atrás se buscan ingresos.
     *
     * El periodo de prueba más largo son dos meses, así que tres bastan y
     * sobran. Es holgura a propósito: una ventana ajustada al milímetro se
     * rompe el día en que alguien cambie una constante legal, y lo haría en
     * silencio.
     */
    private const MESES_DE_LA_VENTANA = 3;

    /**
     * Las modalidades que reciben las tres alertas de la sección 9.2.
     *
     * Es solo el indefinido. La obra o labor pacta el mismo periodo de prueba
     * de dos meses, pero la sección 9.6 dice ahora, tras la revisión de Angela
     * de agosto de 2026, que **no recibe ninguna de las tres**: no hay fecha de
     * vencimiento previsible en esta modalidad, y estas alertas hablan del fin
     * del periodo de prueba, no de un plazo del contrato. Antes de esa revisión
     * el documento decía lo contrario y por eso obra o labor estaba aquí; la
     * corrección es REV-05.
     *
     * @var list<TipoContrato>
     */
    protected const PRUEBA_GENERAL = [TipoContrato::Indefinido];

    /**
     * Las modalidades de contrato a las que aplica esta alerta.
     *
     * @return list<TipoContrato>
     */
    abstract protected function modalidades(): array;

    /**
     * El día en que la alerta tocaría, a partir del ingreso y del último día
     * del periodo de prueba.
     */
    abstract protected function disparaEl(Carbon $ingreso, Carbon $termina): Carbon;

    abstract protected function asunto(string $nombre, ReferenciaTemporal $referencia): string;

    abstract protected function cuerpo(string $nombre, string $cargo, ReferenciaTemporal $referencia): string;

    /**
     * @return iterable<AlertaPorEnviar>
     */
    public function evaluar(Carbon $fechaDelDisparo, Carbon $fechaDeEnvio): iterable
    {
        foreach ($this->candidatas($fechaDelDisparo) as $vinculacion) {
            $prueba = PeriodoDePrueba::de($vinculacion);

            // Un contrato fijo de menos de cinco días no tiene periodo de
            // prueba que quepa. No hay nada de qué avisar.
            if ($prueba->dias() < 1) {
                continue;
            }

            $ingreso = Carbon::parse($vinculacion->fecha_ingreso->toDateString());
            $termina = Carbon::parse($prueba->terminaEl()->toDateString());
            $disparo = $this->disparaEl($ingreso, $termina);

            // Un periodo de prueba de uno o dos días deja el aviso de «dos días
            // antes» en la víspera del ingreso, cuando todavía no había a quién
            // avisar. Esa no se manda.
            if ($disparo->lt($ingreso) || ! $disparo->isSameDay($fechaDelDisparo)) {
                continue;
            }

            $referencia = ReferenciaTemporal::hasta($fechaDeEnvio, $termina);
            $nombre = $vinculacion->persona->nombreCompleto();
            $cargo = (string) $vinculacion->getAttribute('cargo');

            yield AlertaPorEnviar::paraEmpleado(
                $this->tipo(),
                $vinculacion,
                $fechaDeEnvio,
                $termina,
                $this->asunto($nombre, $referencia),
                $this->cuerpo($nombre, $cargo, $referencia),
            );
        }
    }

    /**
     * El asunto de las dos alertas que son una cuenta atrás —la de los 15 días
     * del indefinido y la de los dos días del fijo—.
     *
     * Los dos asuntos de la sección 5.4 traen el número escrito a mano
     * —«quedan 15 días», «quedan 2 días»— y ese número deja de ser cierto en
     * cuanto el corrimiento adelanta el envío. Aquí lo escribe
     * `ReferenciaTemporal`, que es la que sabe cuántos días faltan de verdad,
     * y con la fecha entre paréntesis que pide la sección 9.1.
     */
    protected function asuntoDeCuentaAtras(string $nombre, ReferenciaTemporal $referencia): string
    {
        return 'Periodo de prueba de '.$nombre.': termina '.$referencia->frase();
    }

    /**
     * Los empleados activos que podrían tocarle a esta fecha.
     *
     * No filtra por empresa: cuando el proceso la llama, la empresa ya está en
     * contexto y el global scope hace el resto.
     *
     * @return Collection<int, Vinculacion>
     */
    private function candidatas(Carbon $fechaDelDisparo): Collection
    {
        return Vinculacion::query()
            ->activas()
            ->with('persona')
            ->whereIn('tipo_contrato', array_map(
                fn (TipoContrato $modalidad): string => $modalidad->value,
                $this->modalidades(),
            ))
            ->whereDate('fecha_ingreso', '<=', $fechaDelDisparo)
            ->whereDate('fecha_ingreso', '>=', $fechaDelDisparo->copy()->subMonths(self::MESES_DE_LA_VENTANA))
            ->get();
    }
}
