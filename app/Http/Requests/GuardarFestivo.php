<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Las reglas de agregar un festivo al calendario (documento 4, capítulo 8).
 *
 * Dos campos y ninguno más: **la fecha y el nombre de la celebración**. El año
 * no se pregunta, se deduce de la fecha; preguntarlo dejaría escribir un festivo
 * de julio archivado en el año anterior, y ese error no se nota hasta que un
 * conteo de días hábiles sale mal meses después.
 *
 * La fecha es única entre los festivos vigentes. Cuando dos celebraciones caen
 * el mismo día —el 30 de junio de 2025, San Pedro y el Sagrado Corazón— van en
 * una sola fila con los dos nombres: al motor de cálculo le importa si el día es
 * festivo, no cuántas fiestas hay en él.
 */
class GuardarFestivo extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof User && $usuario->esOwner();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'fecha' => [
                'required',
                'date',
                // Ignora a los quitados: un festivo con borrado lógico sigue
                // ocupando su fecha, y volver a cargarlo es revivirlo, que es
                // lo que hace `Festivo::registrar()`.
                Rule::unique('festivos', 'fecha')->whereNull('deleted_at'),
            ],
            'nombre' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.unique' => 'Ese día ya está en el calendario. Si son dos celebraciones el mismo '
                .'día, corrija el nombre del festivo que ya existe y escriba las dos.',
            'nombre.required' => 'Escriba de qué festivo se trata. El nombre es lo que permite '
                .'revisar el calendario contra el oficial.',
        ];
    }

    public function fecha(): Carbon
    {
        return Carbon::parse((string) $this->input('fecha'))->startOfDay();
    }

    public function nombre(): string
    {
        return trim((string) $this->input('nombre'));
    }
}
