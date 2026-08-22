<?php

namespace App\Http\Requests;

/**
 * Las reglas de la edición de un periodo de vacaciones (documento 1, 6.2).
 *
 * Son las mismas de `RegistrarVacacion` **menos el empleado**, y por eso
 * hereda en vez de copiarlas: el día que cambie una regla de fechas, cambia
 * para el registro y para la corrección a la vez.
 *
 * ## Por qué el empleado no se valida ni se puede cambiar
 *
 * Porque la novedad ya cuelga de una vinculación y editarla es corregir sus
 * fechas, no moverla de persona: para eso se elimina y se registra de nuevo,
 * que además deja las dos líneas en la auditoría. Y porque el documento 1, 6.2
 * permite editar **cualquier** registro sin límite de antigüedad, incluidos
 * los de alguien que ya salió de la empresa; exigir aquí un empleado activo
 * —como sí exige el registro, con razón— dejaría sin corregir precisamente las
 * novedades más viejas.
 */
class EditarVacacion extends RegistrarVacacion
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
