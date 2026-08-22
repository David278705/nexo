<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\HistorialDeContrato;
use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * «Contratos vencidos sin gestionar» (documento 1, 9.4, última fila).
 *
 * El resumen del mes con los contratos a término fijo que pasaron de su fecha
 * de finalización sin que se registrara ni la prórroga ni la terminación. Es la
 * red de seguridad de las cuatro alertas individuales del término fijo: si
 * nadie hizo caso al aviso de los 32 días ni al de los 30, el contrato aparece
 * aquí todos los meses hasta que alguien lo resuelva.
 *
 * ## «Sin gestionar» no se pregunta, se cumple por construcción
 *
 * Es la misma lectura de `ReglaDePreavisoDelFijo` y por el mismo motivo:
 *
 * - **Terminación**: registrarla deja la vinculación inactiva y `activas()` la
 *   saca del listado.
 * - **Prórroga**: registrarla mueve el vencimiento vigente hacia adelante, así
 *   que el contrato deja de estar vencido.
 *
 * Preguntarlo con una condición aparte sería escribir dos veces la misma regla,
 * y la segunda copia se separaría el día en que alguien corrija una prórroga.
 *
 * ## Solo el término fijo
 *
 * El indefinido no tiene fecha de finalización que superar, y el de obra o
 * labor tampoco: no hay fecha previsible (documento 1, 9.6), y por eso ese
 * tiene su propio resumen de gestión y no entra en este.
 *
 * ## Se mira contra el corte, no contra el fin del mes calendario
 *
 * El texto habla en pasado —«superaron su fecha de finalización»— y tiene que
 * ser cierto el día en que el correo sale. Un contrato que vence el 30 de mayo
 * cuando el corte cayó el viernes 29 todavía no ha superado nada: aparece en el
 * resumen de junio, que es el primero que puede decirlo sin mentir.
 */
class ContratosVencidosSinGestionar extends ResumenMensual
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::ContratosVencidosSinGestionar;
    }

    protected function asunto(int $cuantos): string
    {
        return 'Contratos vencidos sin registrar: '.$cuantos;
    }

    protected function encabezado(int $cuantos): string
    {
        return $cuantos === 1
            ? 'Este contrato superó su fecha de finalización y no se registró ni su prórroga ni su terminación.'
            : 'Estos contratos superaron su fecha de finalización y no se registró ni su prórroga ni su terminación.';
    }

    /**
     * @return list<string>
     */
    protected function lineas(Carbon $corte): array
    {
        $vencidos = [];

        foreach ($this->candidatas($corte) as $vinculacion) {
            $vencimiento = HistorialDeContrato::con($vinculacion->periodos)->fechaDeVencimiento();

            if (! $vencimiento instanceof Carbon) {
                continue;
            }

            $vencimiento = Carbon::parse($vencimiento->toDateString());

            if (! $vencimiento->lessThan($corte)) {
                continue;
            }

            $vencidos[] = [$vencimiento, $this->linea($vinculacion, $vencimiento, $corte)];
        }

        // El más vencido primero: es el que lleva más tiempo esperando y el que
        // más riesgo acumula.
        usort($vencidos, fn (array $uno, array $otro): int => $uno[0]->getTimestamp() <=> $otro[0]->getTimestamp());

        return array_map(fn (array $vencido): string => $vencido[1], $vencidos);
    }

    private function linea(Vinculacion $vinculacion, Carbon $vencimiento, Carbon $corte): string
    {
        $dias = MotorDeCalculo::diasTranscurridos($vencimiento, $corte);

        return $this->quien($vinculacion).' — venció el '.Formato::fechaLarga($vencimiento)
            .', hace '.Formato::dias($dias).'.';
    }

    /**
     * Los contratos a término fijo activos que ya pasaron de su fecha.
     *
     * La condición sobre `fecha_fin` es la del vencimiento vigente: la mueve
     * `Vinculacion::registrarPeriodo()` en la misma transacción que escribe el
     * tramo, así que la columna y el historial no pueden desalinearse. Aun así
     * quien decide es el historial, que es el dueño de la respuesta; la consulta
     * solo evita traerse a toda la planta.
     *
     * @return Collection<int, Vinculacion>
     */
    private function candidatas(Carbon $corte): Collection
    {
        return $this->activas()
            ->where('tipo_contrato', TipoContrato::Fijo->value)
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', $corte)
            ->get();
    }
}
