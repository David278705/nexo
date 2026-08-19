<?php

namespace App\Contratacion;

use App\Models\Vinculacion;

/**
 * Abre el periodo inicial en cuanto nace la vinculación.
 *
 * El historial tiene que incluir el periodo inicial —es un requerimiento
 * expreso de la sección 5.5 del documento 1— y ese periodo no es un dato que
 * alguien digite: es el contrato que se acaba de registrar, con su misma
 * modalidad y sus mismas fechas.
 *
 * Se hace aquí y no en el controlador porque son varios los caminos que crean
 * vinculaciones —el formulario de registro, la carga inicial, las pruebas— y
 * cualquiera de ellos podría olvidarlo. Un contrato sin tramo inicial deja el
 * conteo de prórrogas y el tope de cuatro años sin punto de partida, y eso no
 * se nota hasta que la alerta llega tarde.
 *
 * Los tramos siguientes sí los registra quien corresponde: «Renovar» y «Cambiar
 * a término indefinido» son hechos jurídicos que alguien decide (F1-09).
 */
class ObservadorDeVinculacion
{
    public function created(Vinculacion $vinculacion): void
    {
        $vinculacion->registrarPeriodo(
            EventoContractual::Inicial,
            $vinculacion->tipo_contrato,
            $vinculacion->fecha_ingreso,
            $vinculacion->fecha_fin,
        );
    }
}
