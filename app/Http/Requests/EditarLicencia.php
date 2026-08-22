<?php

namespace App\Http\Requests;

/**
 * Las reglas de la edición de una licencia, ausencia o suspensión
 * (documento 1, 6.2).
 *
 * Son las mismas de `RegistrarLicencia` menos el empleado, por los dos motivos
 * que explica `EditarVacacion`. El tipo sí se puede corregir: las tres
 * situaciones se cuentan igual y quien clasificó mal una ausencia como
 * suspensión tiene que poder arreglarlo sin borrar la novedad.
 */
class EditarLicencia extends RegistrarLicencia
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
