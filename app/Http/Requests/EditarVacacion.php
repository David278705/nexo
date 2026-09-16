<?php

namespace App\Http\Requests;

use App\Models\Vacacion;

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

    /**
     * El empleado no viaja en el formulario de edición: sale de la fila que
     * la ruta ya resolvió por `{vacacion}`.
     */
    protected function vinculacionIdParaTraslape(): int
    {
        return $this->vacacionEnLaRuta()->vinculacion_id;
    }

    /**
     * La propia fila no se compara contra sí misma: si no cambiaron las
     * fechas, o si las nuevas siguen sin pisar nada más, guardar tiene que
     * poder repetir las mismas fechas de hoy.
     */
    protected function idAExcluirDelTraslape(): ?int
    {
        return $this->vacacionEnLaRuta()->id;
    }

    private function vacacionEnLaRuta(): Vacacion
    {
        $vacacion = $this->route('vacacion');

        assert($vacacion instanceof Vacacion);

        return $vacacion;
    }
}
