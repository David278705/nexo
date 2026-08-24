<?php

namespace App\CargaInicial;

use RuntimeException;

/**
 * La carga no se ejecuta, y no porque el archivo sea ilegible sino porque
 * ejecutarla ahora estaría mal.
 *
 * Son dos motivos y los dos son negativas del sistema, no equivocaciones del
 * cliente al llenar una celda:
 *
 * 1. **El archivo todavía tiene errores.** El paso 4 del documento 2, 6.1 dice
 *    que se corrige y se repite; confirmar con errores encima es lo que esta
 *    excepción impide. La simulación viaja dentro para que quien la reciba
 *    pueda volver a enseñar el informe sin repetirla.
 * 2. **La plantilla de empleados no se ha procesado.** Es la dependencia del
 *    documento 2, 6.3: los históricos se vinculan por número de documento y sin
 *    planta no hay a quién colgárselos.
 *
 * Es una sola excepción con dos constructores y no dos clases porque quien la
 * recibe hace lo mismo con las dos: enseñar el motivo y no escribir nada.
 */
final class CargaRechazada extends RuntimeException
{
    private function __construct(
        string $mensaje,
        public readonly ?ResultadoDeSimulacion $simulacion = null,
    ) {
        parent::__construct($mensaje);
    }

    public static function porErrores(ResultadoDeSimulacion $simulacion): self
    {
        return new self(
            'El archivo tiene '.$simulacion->filasConError().' fila(s) con error. '.
            'No se guardó nada: corrija el archivo con el cliente y vuelva a pasarlo.',
            $simulacion,
        );
    }

    public static function sinPlantaCargada(Plantilla $plantilla): self
    {
        return new self(
            "No se puede cargar «{$plantilla->nombre()}» todavía: la plantilla «Empleados actuales» ".
            'no se ha procesado en esta empresa. Los históricos se vinculan por número de documento '.
            'con la planta ya cargada.'
        );
    }
}
