<?php

namespace App\Dominio\Alertas\Reglas;

use App\Dominio\Alertas\AlertaPorEnviar;
use App\Dominio\Alertas\ReglaDeAlerta;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Lo que comparten los resúmenes mensuales (documento 1, 9.7, segunda fila).
 *
 * Se diferencian de las nueve alertas individuales en tres cosas, y las tres
 * viven aquí para no escribirlas una vez por resumen:
 *
 * 1. **Salen el último día hábil del mes**, no en una fecha que dependa de cada
 *    empleado.
 * 2. **Van agrupados en un solo correo por empresa**, no uno por persona. Por
 *    eso usan `AlertaPorEnviar::paraLaEmpresa()`, que deja `vinculacion_id` en
 *    nulo: el resumen no es de nadie en particular.
 * 3. **Si no hay a quién listar, no se manda nada.** Un correo que dice «0
 *    contratos vencidos» todos los meses enseña a ignorar el remitente, y con
 *    él se ignoraría el mes en que sí traía algo.
 *
 * ## El día de salida no se corre nunca
 *
 * El último día hábil del mes es hábil por definición, así que el corrimiento
 * al día hábil anterior (documento 1, 9.1) no tiene nada que hacer aquí: la
 * fecha del disparo y la del envío son siempre la misma. Eso también significa
 * que el proceso diario lo evalúa exactamente una vez al mes —las fechas que le
 * tocan a un día son la suya y las de los días **no hábiles** que vienen
 * detrás—, y por eso ningún resumen puede salir dos veces.
 *
 * ## El corte y el mes del que se habla
 *
 * `$corte` es ese último día hábil, y es la fecha con la que cada resumen mira
 * los datos. No siempre coincide con el último día del mes calendario —agosto
 * de 2026 termina en lunes, pero mayo de 2026 termina en domingo y el corte cae
 * el viernes 29—, así que cada resumen dice explícitamente contra qué mira: los
 * contratos vencidos, contra el corte, porque hablan en pasado y tienen que ser
 * ciertos el día que salen; la acumulación de vacaciones, contra el fin del mes
 * calendario, porque su texto dice «completaron **este mes**».
 */
abstract class ResumenMensual implements ReglaDeAlerta
{
    /**
     * El asunto, que en las cuatro plantillas de la sección 5.4 lleva dentro
     * cuántos son.
     */
    abstract protected function asunto(int $cuantos): string;

    /**
     * La frase que abre el correo y explica de qué es el listado.
     */
    abstract protected function encabezado(int $cuantos): string;

    /**
     * Una línea por caso, ya redactada, o vacío si este mes no hay ninguno.
     *
     * @return list<string>
     */
    abstract protected function lineas(Carbon $corte): array;

    /**
     * @return iterable<AlertaPorEnviar>
     */
    public function evaluar(Carbon $fechaDelDisparo, Carbon $fechaDeEnvio): iterable
    {
        if (! $fechaDelDisparo->isSameDay(MotorDeCalculo::ultimoDiaHabilDelMes($fechaDelDisparo))) {
            return;
        }

        $lineas = $this->lineas($fechaDelDisparo);

        if ($lineas === []) {
            return;
        }

        yield AlertaPorEnviar::paraLaEmpresa(
            $this->tipo(),
            $fechaDeEnvio,
            // El día del que habla el mensaje es el corte del resumen. Ninguna
            // de las cuatro plantillas trae una referencia relativa que
            // `ReferenciaTemporal` pueda ajustar —un resumen no dice «mañana»—,
            // así que las fechas que sí importan van escritas enteras dentro de
            // cada línea del listado.
            $fechaDelDisparo,
            $this->asunto(count($lineas)),
            $this->cuerpo($this->encabezado(count($lineas)), $lineas),
        );
    }

    /**
     * Los empleados activos de la empresa en contexto, con lo que los tres
     * resúmenes leen de ellos.
     *
     * No filtra por empresa: cuando el proceso llama a la regla, la empresa ya
     * está en contexto y el global scope hace el resto. «Activos» es la primera
     * regla transversal de la sección 9.1, y en los contratos vencidos hace
     * además de segunda mitad de «sin gestionar»: registrar la terminación deja
     * la vinculación inactiva y la saca del listado sola.
     *
     * Se devuelve la consulta y no el resultado porque el de los contratos
     * vencidos sí puede acotar en SQL —solo mira los de término fijo ya
     * caducados— mientras que los dos de vacaciones tienen que recorrer a todo
     * el mundo: el saldo es una función y no una columna, así que no hay
     * ninguna condición que ponerle a la consulta.
     *
     * @return Builder<Vinculacion>
     */
    protected function activas(): Builder
    {
        return Vinculacion::query()
            ->activas()
            ->with(['persona', 'periodos'])
            ->orderBy('id');
    }

    /**
     * Nombre y cargo delante de cada línea, que es como empiezan las tres.
     */
    protected function quien(Vinculacion $vinculacion): string
    {
        return $vinculacion->persona->nombreCompleto().', '.(string) $vinculacion->getAttribute('cargo');
    }

    /**
     * El encabezado y el listado, uno por línea.
     *
     * `CorreoDeAlerta::parrafosDe()` parte el cuerpo por saltos de línea, así
     * que cada caso llega al correo como su propio párrafo en lugar de salir
     * todo pegado en un bloque.
     *
     * @param  list<string>  $lineas
     */
    private function cuerpo(string $encabezado, array $lineas): string
    {
        return $encabezado."\n".implode("\n", $lineas);
    }
}
