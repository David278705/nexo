<?php

namespace App\Dominio\Alertas\Reglas;

use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * «Contratos de obra o labor activos» (documento 1, 9.6).
 *
 * El listado de todos los contratos de obra o labor que siguen activos en la
 * empresa, para que alguien los revise: si una obra ya terminó y la persona
 * siguió trabajando sin un acuerdo nuevo por escrito, el vínculo mutó a
 * indefinido desde el inicio de la relación sin que nadie lo notara. Esa
 * conversión opera por ley —la nota de la sección 5.4 del documento 1 y su anexo
 * A—, y es todo lo que este correo persigue: visibilidad periódica.
 *
 * ## No hay umbral de antigüedad, y es a propósito
 *
 * Es la decisión D.2, que Angela cerró en agosto de 2026 cambiándole la forma a
 * la alerta en vez de ponerle un número: **no existe criterio legal para decir a
 * partir de cuándo una obra es «prolongada»**, así que cualquier mes de corte
 * habría sido inventado. El listado los trae todos, incluido el que ingresó este
 * mismo mes, porque afirmar que unos importan y otros no es precisamente lo que
 * no se puede sostener.
 *
 * De ahí el nombre: la alerta que se llamaba `ObraOLaborProlongada` describía
 * una alerta que no se construyó nunca.
 *
 * ## La única que no sale todos los meses
 *
 * Un listado completo repetido cada mes se vuelve ruido, y el remitente que
 * manda ruido se ignora también el mes en que trae algo. Por eso es bimestral, y
 * por eso `saleEsteMes()` existe en `ResumenMensual`.
 *
 * ## «Activo» ya significa «sin terminación registrada»
 *
 * No hay que escribir esa condición: registrar la terminación —también la de
 * tipo «terminación de la obra o labor contratada»— deja la vinculación inactiva
 * y `activas()` la saca del listado sola. Es el mismo mecanismo del que vive
 * `ContratosVencidosSinGestionar`, y escribirlo dos veces sería tener dos copias
 * de la misma regla esperando a separarse.
 */
class ObraOLaborActivos extends ResumenMensual
{
    public function tipo(): TipoDeAlerta
    {
        return TipoDeAlerta::ObraOLaborActivos;
    }

    /**
     * Los meses impares: enero, marzo, mayo, julio, septiembre y noviembre.
     *
     * El ancla es una decisión de implementación y no de Angela, que solo pidió
     * «cada dos meses». Hacía falta un ancla para que el disparo sea
     * determinista —y por tanto idempotente— y la paridad del mes calendario lo
     * consigue sin guardar estado en ninguna tabla y sin depender de cuándo se
     * dio de alta cada empresa, que haría que dos clientes recibieran el correo
     * en meses distintos. Diciembre y enero salen seguidos una vez al año, que
     * es el precio de cualquier ancla de doce meses impares.
     */
    protected function saleEsteMes(Carbon $corte): bool
    {
        return $corte->month % 2 === 1;
    }

    protected function asunto(int $cuantos): string
    {
        return 'Contratos de obra o labor activos: '.$cuantos;
    }

    protected function encabezado(int $cuantos): string
    {
        $cuales = $cuantos === 1
            ? 'Este contrato de obra o labor sigue activo y sin terminación registrada. Conviene '
                .'verificar si esta obra ya finalizó: '
            : 'Estos contratos de obra o labor siguen activos y sin terminación registrada. Conviene '
                .'verificar si alguna de estas obras ya finalizó: ';

        return $cuales.'si la obra terminó y la persona siguió trabajando sin un acuerdo nuevo por '
            .'escrito, el contrato se entiende celebrado a término indefinido desde el inicio de la '
            .'relación.';
    }

    /**
     * @return list<string>
     */
    protected function lineas(Carbon $corte): array
    {
        $activos = $this->activas()
            ->where('tipo_contrato', TipoContrato::ObraLabor->value)
            // El que lleva más tiempo primero: es el que más probablemente tiene
            // una obra ya terminada detrás.
            ->reorder('fecha_ingreso')
            ->orderBy('id')
            ->get();

        return $activos->map(fn (Vinculacion $vinculacion): string => $this->linea($vinculacion, $corte))
            ->values()
            ->all();
    }

    private function linea(Vinculacion $vinculacion, Carbon $corte): string
    {
        $ingreso = $vinculacion->fecha_ingreso;

        return $this->quien($vinculacion).' — ingresó el '.Formato::fechaLarga($ingreso)
            .', lleva '.$this->lleva(MotorDeCalculo::mesesCumplidos($ingreso, $corte)).'.';
    }

    /**
     * Cuánto lleva, contado en meses cumplidos y escrito con la misma frase que
     * la alerta de cercanía al tope.
     *
     * Los meses los cuenta el motor y el «1 año y 3 meses» lo escribe
     * `Formato::tiempoTranscurrido()`; aquí solo se resuelve el caso que ese
     * formato no cubre porque ninguna otra alerta lo alcanza: quien ingresó este
     * mismo mes lleva cero meses cumplidos, y «lleva 0 meses» no es algo que
     * nadie diga en voz alta. No es otra cuenta, es la misma leída en palabras.
     */
    private function lleva(int $meses): string
    {
        return $meses === 0 ? 'menos de un mes' : Formato::tiempoTranscurrido($meses);
    }
}
