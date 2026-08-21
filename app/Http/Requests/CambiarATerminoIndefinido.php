<?php

namespace App\Http\Requests;

use App\Contratacion\CambioAIndefinido;
use App\Models\Vinculacion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Las reglas de la acción «Cambiar a término indefinido» (documento 1, 5.5).
 *
 * No hay ninguna sobre los datos porque no se pide ninguno: el modal es una
 * confirmación, no un formulario. La única regla es de existencia de la acción y
 * está en `authorize()`: **solo un término fijo se convierte**. En un indefinido
 * el cambio no significa nada, y en obra o labor el paso a indefinido se hace
 * por otrosí fuera del sistema, así que enviar la petición a mano responde 403 y
 * no un error de validación de un campo que no existe.
 *
 * El cambio inverso tampoco existe, y no hace falta prohibirlo: no hay ninguna
 * ruta que lo haga.
 */
class CambiarATerminoIndefinido extends FormRequest
{
    public function authorize(): bool
    {
        return CambioAIndefinido::de($this->contrato())->disponible();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * El contrato de la dirección, resuelto por el enrutador.
     *
     * Pasa por el global scope del trait, así que el contrato de otra empresa ya
     * respondió 404 antes de llegar aquí.
     */
    private function contrato(): Vinculacion
    {
        $contrato = $this->route('contrato');

        return $contrato instanceof Vinculacion ? $contrato : new Vinculacion;
    }
}
