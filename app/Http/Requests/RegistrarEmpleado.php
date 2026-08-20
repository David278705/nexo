<?php

namespace App\Http\Requests;

use App\Contratacion\Genero;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoDocumento;
use App\Contratacion\TipoRiesgo;
use App\Models\Pais;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Las reglas del formulario de registro de empleado (documento 1, 5.1 a 5.3).
 *
 * Está entera en el servidor. El asistente de Vue comprueba lo mismo paso a
 * paso para no dejar avanzar con un campo vacío, pero eso es comodidad: quien
 * envíe el formulario a mano se encuentra con estas reglas igual.
 *
 * Lo que aquí se decide y conviene no perder de vista:
 *
 * - **El estado no se valida porque no se recibe.** Es la regla 5.3: al crear,
 *   el empleado queda activo solo. Si algún día aparece `estado` en esta lista,
 *   algo se rompió.
 * - **El documento no es único.** Suena a error y no lo es: si la persona ya
 *   estuvo vinculada a la empresa se reutiliza su registro (regla 5 del
 *   proyecto). La advertencia de ese reingreso es F1-05.
 * - **La fecha de finalización la manda la modalidad**, no el usuario: la exige
 *   el término fijo y la prohíbe el resto (documento 1, 5.4).
 * - **Los cinco catálogos se validan contra lo que hoy está activo.** Elegir una
 *   EPS liquidada por su identificador desde fuera del formulario no pasa.
 */
class RegistrarEmpleado extends FormRequest
{
    /**
     * El acceso al módulo lo verifica el middleware `modulo:empleados` de la
     * ruta, que es el mismo que protege el listado.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identificacion(),
            ...$this->ubicacion('nacimiento'),
            ...$this->ubicacion('expedicion'),
            ...$this->residencia(),
            ...$this->seguridadSocial(),
            ...$this->contrato(),
        ];
    }

    /**
     * Los nombres con los que el usuario reconoce cada campo, para que el
     * mensaje diga «El primer nombre es obligatorio» y no «El campo
     * primer_nombre es obligatorio».
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo_documento' => 'tipo de documento',
            'numero_documento' => 'número de documento',
            'fecha_expedicion_documento' => 'fecha de expedición del documento',
            'primer_nombre' => 'primer nombre',
            'segundo_nombre' => 'segundo nombre',
            'primer_apellido' => 'primer apellido',
            'segundo_apellido' => 'segundo apellido',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'genero' => 'género',

            'nacimiento.pais' => 'país de nacimiento',
            'nacimiento.departamento' => 'departamento de nacimiento',
            'nacimiento.ciudad' => 'ciudad de nacimiento',

            'expedicion.pais' => 'país de expedición del documento',
            'expedicion.departamento' => 'departamento de expedición del documento',
            'expedicion.ciudad' => 'lugar de expedición del documento',

            'direccion' => 'dirección',
            'residencia.departamento' => 'departamento de residencia',
            'residencia.ciudad' => 'ciudad de residencia',

            'eps_id' => 'EPS',
            'fondo_pension_id' => 'fondo de pensión',
            'arl_id' => 'ARL',
            'tipo_riesgo' => 'tipo de riesgo',
            'caja_compensacion_id' => 'caja de compensación',
            'banco_id' => 'entidad bancaria',

            'cargo' => 'cargo',
            'tipo_contrato' => 'tipo de contrato',
            'fecha_ingreso' => 'fecha de ingreso',
            'fecha_fin' => 'fecha de finalización',
            'salario' => 'salario mensual',
            'bonificacion' => 'bonificación',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_fin.required' => 'El contrato a término fijo necesita fecha de finalización.',
            'fecha_fin.prohibited' => 'Solo el contrato a término fijo lleva fecha de finalización.',
            'fecha_fin.after' => 'La fecha de finalización tiene que ser posterior a la de ingreso.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function identificacion(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(TipoDocumento::valores())],

            // Se valida como texto y no como número: el pasaporte trae letras y
            // un `numeric` convertiría «000123» en 123, que es otro documento.
            'numero_documento' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],

            'fecha_expedicion_documento' => ['required', 'date', 'before_or_equal:today'],

            'primer_nombre' => ['required', 'string', 'max:60'],
            'segundo_nombre' => ['nullable', 'string', 'max:60'],
            'primer_apellido' => ['required', 'string', 'max:60'],
            'segundo_apellido' => ['nullable', 'string', 'max:60'],

            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'genero' => ['required', Rule::in(Genero::valores())],
        ];
    }

    /**
     * La regla 5.1.1 en forma de reglas: en Colombia la ciudad es un
     * identificador del catálogo y el departamento se deduce de ella; fuera de
     * Colombia los dos son texto libre y el sistema no puede comprobarlos.
     *
     * @return array<string, mixed>
     */
    private function ubicacion(string $campo): array
    {
        $enColombia = $this->input($campo.'.pais') === Pais::COLOMBIA;

        return [
            $campo.'.pais' => ['required', 'string', Rule::exists('paises', 'codigo')->where('activo', true)->whereNull('deleted_at')],
            $campo.'.departamento' => $enColombia
                ? ['required', $this->existeDepartamento()]
                : ['required', 'string', 'max:120'],
            $campo.'.ciudad' => $enColombia
                ? ['required', $this->existeCiudad($this->input($campo.'.departamento'))]
                : ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function residencia(): array
    {
        return [
            'direccion' => ['required', 'string', 'max:160'],

            // Sin país: la residencia es siempre en Colombia porque el sistema
            // solo gestiona contratos laborales colombianos (documento 1, 5.1).
            'residencia.departamento' => ['required', $this->existeDepartamento()],
            'residencia.ciudad' => ['required', $this->existeCiudad($this->input('residencia.departamento'))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seguridadSocial(): array
    {
        return [
            'eps_id' => ['required', $this->deCatalogo('eps')],
            'fondo_pension_id' => ['required', $this->deCatalogo('fondos_pension')],
            'arl_id' => ['required', $this->deCatalogo('arls')],
            'tipo_riesgo' => ['required', Rule::in(TipoRiesgo::valores())],
            'caja_compensacion_id' => ['required', $this->deCatalogo('cajas_compensacion')],
            'banco_id' => ['required', $this->deCatalogo('bancos')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contrato(): array
    {
        $esFijo = $this->input('tipo_contrato') === TipoContrato::Fijo->value;

        return [
            'cargo' => ['required', 'string', 'max:120'],
            'tipo_contrato' => ['required', Rule::in(TipoContrato::valores())],
            'fecha_ingreso' => ['required', 'date'],
            'fecha_fin' => $esFijo
                ? ['required', 'date', 'after:fecha_ingreso']
                : ['nullable', 'prohibited'],

            // Dinero en decimal de precisión fija: dos decimales y sin negativos.
            'salario' => ['required', 'numeric', 'min:0', 'decimal:0,2'],

            // Obligatoria, pero puede quedar en cero (documento 1, 5.1).
            'bonificacion' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    private function existeDepartamento(): mixed
    {
        return Rule::exists('departamentos', 'id')->where('activo', true)->whereNull('deleted_at');
    }

    /**
     * La ciudad tiene que ser del departamento elegido.
     *
     * En pantalla el encadenamiento ya lo garantiza, pero la lista de ciudades
     * viaja al navegador entera: sin esta comprobación, un envío hecho a mano
     * podría dejar a alguien residiendo en un municipio de otro departamento y
     * el dato saldría mal en todos los reportes.
     *
     * La condición solo se agrega si el departamento es un identificador: con
     * un valor cualquiera, la propia regla del departamento es la que falla.
     */
    private function existeCiudad(mixed $departamento): mixed
    {
        $regla = Rule::exists('ciudades', 'id')->where('activo', true)->whereNull('deleted_at');

        return is_numeric($departamento)
            ? $regla->where('departamento_id', (int) $departamento)
            : $regla;
    }

    private function deCatalogo(string $tabla): mixed
    {
        return Rule::exists($tabla, 'id')->where('activo', true)->whereNull('deleted_at');
    }
}
