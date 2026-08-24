<?php

namespace App\Http\Requests;

use App\CargaInicial\CatalogoDePlantillas;
use App\CargaInicial\Plantilla;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Lo que hay que traer para simular o para ejecutar una carga inicial: **a qué
 * empresa, cuál de las seis plantillas y el archivo**.
 *
 * Es el mismo `FormRequest` para los dos pasos del documento 2, 6.1 —validar sin
 * escribir y confirmar— porque los dos reciben exactamente lo mismo. La
 * diferencia entre ellos no está en lo que se manda, sino en si se escribe, y
 * eso lo decide la ruta.
 *
 * **El archivo se manda otra vez al confirmar y no es un descuido.** El Excel no
 * se guarda en ninguna parte (documento 1, 13.1: «solo se usa para cargar, no se
 * almacena ni se conserva»), así que no hay nada a lo que referirse entre un
 * paso y el otro. Lo que se escribe es lo que se acaba de volver a validar, que
 * además es la única forma de que el informe no pueda mentir.
 */
class CargarPlantilla extends FormRequest
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
            'empresa' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'plantilla' => ['required', Rule::in((new CatalogoDePlantillas)->claves())],
            /*
             * Se comprueba la extensión y no el tipo MIME: un `.xlsx` es un zip
             * por dentro y el olfateo del sistema lo dice, así que `mimes:xlsx`
             * rechazaría archivos correctos según la máquina donde corra. La
             * comprobación de verdad es que el lector lo abra, y si no puede lo
             * dice con sus palabras.
             */
            'archivo' => ['required', 'file', 'extensions:xlsx,xls', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'empresa.required' => 'Elija a qué empresa se va a cargar el archivo.',
            'plantilla.required' => 'Elija cuál de las seis plantillas es el archivo.',
            'plantilla.in' => 'Esa plantilla no existe. Son las seis de la carpeta de plantillas.',
            'archivo.required' => 'Suba la plantilla diligenciada.',
            'archivo.extensions' => 'El archivo debe ser el de Excel que se le entregó al cliente (.xlsx).',
            'archivo.max' => 'El archivo pesa más de 10 MB. Una plantilla diligenciada no llega a eso: '
                .'revise que no tenga imágenes ni hojas de más.',
        ];
    }

    public function empresa(): Tenant
    {
        return Tenant::query()->findOrFail($this->integer('empresa'));
    }

    public function plantilla(): Plantilla
    {
        return (new CatalogoDePlantillas)->de((string) $this->input('plantilla'));
    }

    public function archivo(): UploadedFile
    {
        /** @var UploadedFile $archivo */
        $archivo = $this->file('archivo');

        return $archivo;
    }

    /**
     * Dónde está el archivo mientras dura la petición.
     *
     * Es el temporal que abre PHP para cualquier subida y no se copia a ningún
     * sitio: el Excel no se conserva (documento 1, 13.1).
     */
    public function rutaDelArchivo(): string
    {
        return (string) $this->archivo()->getRealPath();
    }
}
