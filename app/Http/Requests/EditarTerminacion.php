<?php

namespace App\Http\Requests;

/**
 * Las reglas de la corrección de una salida (REV-03).
 *
 * Son las mismas de `RegistrarTerminacion` **menos el empleado**, y por eso
 * hereda: la lista dependiente de motivos del documento 1, 7.3 se comprueba
 * igual al registrar que al corregir, y ese `withValidator()` no se puede
 * copiar sin que las dos versiones se separen a la primera corrección.
 *
 * ## Por qué el empleado no se valida ni se puede cambiar
 *
 * Por dos razones, y la segunda no es cosmética: la comprobación heredada exige
 * empleado **activo**, y el empleado de una terminación registrada está siempre
 * inactivo —lo dejó así la propia terminación—. Aplicada aquí, ninguna salida
 * sería corregible nunca. Que el registro la exija tiene sentido: es la guardia
 * contra la doble salida. Que la corrección no la exija tiene el mismo sentido:
 * la salida ya existe y lo que se está arreglando es lo que dice.
 */
class EditarTerminacion extends RegistrarTerminacion
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = parent::rules();

        unset($reglas['vinculacion_id']);

        return $reglas;
    }
}
