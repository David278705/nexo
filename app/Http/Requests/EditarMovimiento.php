<?php

namespace App\Http\Requests;

/**
 * Las reglas de la corrección de un aumento o una promoción (REV-03).
 *
 * Son las mismas de `RegistrarMovimiento` **menos el empleado**, y por eso
 * hereda en vez de copiarlas: el día que cambie el formato del dinero o la
 * obligatoriedad del cargo en una promoción, cambia para el registro y para la
 * corrección a la vez. Es el mismo patrón de `EditarVacacion`.
 *
 * ## Por qué el empleado no se valida ni se puede cambiar
 *
 * Porque el movimiento ya cuelga de una vinculación y corregirlo es arreglar
 * sus cifras, no moverlo de persona: para eso se elimina —que devuelve al
 * empleado como estaba— y se registra de nuevo sobre el otro.
 *
 * Y porque exigir aquí un empleado activo, como sí hace el registro, impediría
 * corregir el aumento de alguien que después se fue de la empresa. Quien puede
 * corregirlo no es cualquiera: la ruta pasa por `modulo:movimientos`.
 *
 * ## Lo que esta clase no comprueba
 *
 * Que el movimiento sea el más reciente del empleado. No es una regla de
 * formato del formulario sino del histórico —la foto de lo anterior encadena
 * cada fila con la siguiente—, y se comprueba en el controlador, en el mismo
 * sitio para editar y para eliminar.
 */
class EditarMovimiento extends RegistrarMovimiento
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
