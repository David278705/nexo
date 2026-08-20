<?php

namespace App\Contratacion;

use App\Models\Pais;
use App\Models\Persona;
use App\Models\Vinculacion;
use Illuminate\Support\Facades\DB;

/**
 * El registro de un empleado: la escritura del asistente de cuatro pasos
 * (documento 1, secciones 5.1 a 5.3).
 *
 * Hace dos cosas y las hace juntas o no las hace:
 *
 * 1. **Busca la persona por su documento antes de crearla.** Es la regla 5 del
 *    proyecto: la persona y la vinculación son entidades separadas y una
 *    persona puede tener varias vinculaciones. Si el documento ya existe en la
 *    empresa se reutiliza esa persona —y se actualizan sus datos personales con
 *    lo que se acaba de digitar, porque una persona tiene una sola versión de
 *    su nombre y su dirección—, no se crea una segunda.
 * 2. **Crea la vinculación.** Sin tocar el estado: nace activo por el valor por
 *    defecto de la columna, que es la regla 5.3. Aquí no se escribe la palabra
 *    «activo» ni una sola vez, y esa ausencia es la prueba de que nadie lo
 *    digita.
 *
 * El periodo inicial del historial no se crea aquí: lo abre solo
 * `ObservadorDeVinculacion`.
 *
 * Las tres ubicaciones se guardan siguiendo la regla 5.1.1: si el país es
 * Colombia va el identificador del catálogo y los textos quedan vacíos; si es
 * otro, al revés. Nunca las dos cosas, que es lo que obligaría meses después a
 * adivinar qué hay dentro de cada columna.
 */
class RegistroDeEmpleado
{
    /**
     * @param  array<string, mixed>  $datos  ya validados por `RegistrarEmpleado`
     */
    public function ejecutar(array $datos): Vinculacion
    {
        return DB::transaction(function () use ($datos): Vinculacion {
            $persona = $this->personaDe($datos);

            return $persona->vinculaciones()->create($this->vinculacion($datos));
        });
    }

    /**
     * La persona que ya existía con ese documento o una nueva.
     *
     * @param  array<string, mixed>  $datos
     */
    private function personaDe(array $datos): Persona
    {
        $atributos = $this->persona($datos);

        $existente = Persona::query()
            ->conDocumento((string) $datos['numero_documento'])
            ->first();

        if ($existente instanceof Persona) {
            $existente->fill($atributos)->save();

            return $existente;
        }

        return Persona::query()->create($atributos);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function persona(array $datos): array
    {
        /** @var array{pais: string, departamento: string, ciudad: string} $nacimiento */
        $nacimiento = $datos['nacimiento'];
        /** @var array{pais: string, departamento: string, ciudad: string} $expedicion */
        $expedicion = $datos['expedicion'];
        /** @var array{departamento: string, ciudad: string} $residencia */
        $residencia = $datos['residencia'];

        return [
            'tipo_documento' => $datos['tipo_documento'],
            'numero_documento' => $datos['numero_documento'],
            'fecha_expedicion_documento' => $datos['fecha_expedicion_documento'],

            'primer_nombre' => $datos['primer_nombre'],
            'segundo_nombre' => $datos['segundo_nombre'] ?? null,
            'primer_apellido' => $datos['primer_apellido'],
            'segundo_apellido' => $datos['segundo_apellido'] ?? null,

            'fecha_nacimiento' => $datos['fecha_nacimiento'],
            'genero' => $datos['genero'],

            ...$this->ubicacion('nacimiento', $nacimiento),
            ...$this->ubicacion('expedicion', $expedicion),

            'direccion' => $datos['direccion'],
            // La residencia es siempre en Colombia, así que siempre del
            // catálogo: no tiene par de columnas de texto libre.
            'ciudad_residencia_id' => (int) $residencia['ciudad'],
        ];
    }

    /**
     * Una de las dos ubicaciones con doble camino, según la regla 5.1.1.
     *
     * @param  array{pais: string, departamento: string, ciudad: string}  $ubicacion
     * @return array<string, int|string|null>
     */
    private function ubicacion(string $campo, array $ubicacion): array
    {
        $enColombia = $ubicacion['pais'] === Pais::COLOMBIA;

        return [
            'pais_'.$campo => $ubicacion['pais'],
            'ciudad_'.$campo.'_id' => $enColombia ? (int) $ubicacion['ciudad'] : null,
            'departamento_'.$campo.'_exterior' => $enColombia ? null : $ubicacion['departamento'],
            'ciudad_'.$campo.'_exterior' => $enColombia ? null : $ubicacion['ciudad'],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function vinculacion(array $datos): array
    {
        $tipoContrato = TipoContrato::from((string) $datos['tipo_contrato']);

        return [
            'cargo' => $datos['cargo'],
            'tipo_contrato' => $tipoContrato,
            'fecha_ingreso' => $datos['fecha_ingreso'],
            // Solo el término fijo la tiene (documento 1, 5.4). Que la
            // modalidad decida y no el formulario evita el contrato indefinido
            // con fecha de vencimiento, que dispararía alertas de renovación
            // sobre un contrato que no se renueva.
            'fecha_fin' => $tipoContrato->exigeFechaFin() ? $datos['fecha_fin'] : null,

            'salario' => $datos['salario'],
            'bonificacion' => $datos['bonificacion'],

            'eps_id' => $datos['eps_id'],
            'fondo_pension_id' => $datos['fondo_pension_id'],
            'arl_id' => $datos['arl_id'],
            'tipo_riesgo' => $datos['tipo_riesgo'],
            'caja_compensacion_id' => $datos['caja_compensacion_id'],
            'banco_id' => $datos['banco_id'],
        ];
    }
}
