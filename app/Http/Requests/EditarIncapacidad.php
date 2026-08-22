<?php

namespace App\Http\Requests;

/**
 * Las reglas de la edición de una incapacidad (documento 1, 6.2).
 *
 * Son las mismas de `RegistrarIncapacidad` menos el empleado, por los dos
 * motivos que explica `EditarVacacion`: editar es corregir el registro, no
 * moverlo de persona, y cualquier novedad se puede corregir sin importar su
 * antigüedad ni si quien la tuvo sigue vinculado.
 */
class EditarIncapacidad extends RegistrarIncapacidad
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
