<?php

namespace App\Contratacion;

use App\Models\PerfilSociodemografico;
use App\Models\Persona;
use Illuminate\Validation\Rule;

/**
 * El bloque opcional del documento 1, sección 5.2: sus opciones, sus reglas y
 * su escritura, en un solo sitio.
 *
 * Tres cosas que aquí se deciden y que son toda la tarea F1-06:
 *
 * 1. **Lo enciende la empresa, no el formulario.** El parámetro
 *    `perfil_sociodemografico_habilitado` de `tenants` (documento 1, capítulo 4)
 *    decide si el paso existe y si lo digitado se guarda. Apagado, el bloque no
 *    se pinta y nada de lo que llegue por él se escribe: el asistente sigue
 *    siendo el de cuatro pasos.
 * 2. **Todos sus campos son opcionales aunque el bloque esté encendido.** Es la
 *    regla explícita de 5.2, al revés que el resto del formulario. Por eso las
 *    reglas de validación son `nullable` y el paso del asistente no tiene
 *    campos obligatorios que revisar.
 * 3. **Un perfil vacío no se guarda.** Si la empresa enciende el bloque y quien
 *    registra no llena nada, no se crea la fila: no hay diferencia entre un
 *    perfil en blanco y un perfil que no existe, y la que sí existiría —una fila
 *    de nulos— solo serviría para confundir después. Es también lo que hace que
 *    encender el parámetro con la planta ya cargada deje a los empleados
 *    existentes en blanco sin marcar error: no hay nada que reclamar.
 */
class BloqueSociodemografico
{
    /**
     * El estrato socioeconómico colombiano va del 1 al 6 (documento 1, 5.2).
     */
    public const ESTRATO_MINIMO = 1;

    public const ESTRATO_MAXIMO = 6;

    /**
     * Los ocho campos del bloque, en el orden del documento.
     *
     * @var list<string>
     */
    public const CAMPOS = [
        'estado_civil',
        'nivel_educativo',
        'numero_hijos',
        'personas_a_cargo',
        'tipo_vivienda',
        'estrato',
        'medio_transporte',
        'tiempo_desplazamiento',
    ];

    /**
     * Si la empresa del usuario tiene el bloque encendido.
     */
    public static function habilitado(): bool
    {
        return tenant_actual()?->perfil_sociodemografico_habilitado === true;
    }

    /**
     * Las seis listas del bloque tal como las consume un selector.
     *
     * @return array{estadosCiviles: list<array{valor: string, texto: string}>, nivelesEducativos: list<array{valor: string, texto: string}>, tiposVivienda: list<array{valor: string, texto: string}>, estratos: list<array{valor: int, texto: string}>, mediosTransporte: list<array{valor: string, texto: string}>, tiemposDesplazamiento: list<array{valor: string, texto: string}>}
     */
    public static function opciones(): array
    {
        return [
            'estadosCiviles' => EstadoCivil::paraElegir(),
            'nivelesEducativos' => NivelEducativo::paraElegir(),
            'tiposVivienda' => TipoVivienda::paraElegir(),
            'estratos' => self::estratos(),
            'mediosTransporte' => MedioTransporte::paraElegir(),
            'tiemposDesplazamiento' => TiempoDesplazamiento::paraElegir(),
        ];
    }

    /**
     * @return list<array{valor: int, texto: string}>
     */
    public static function estratos(): array
    {
        return array_map(
            fn (int $estrato): array => ['valor' => $estrato, 'texto' => 'Estrato '.$estrato],
            range(self::ESTRATO_MINIMO, self::ESTRATO_MAXIMO),
        );
    }

    /**
     * Las reglas del bloque dentro del formulario de registro.
     *
     * Ni una sola es `required`, y eso no es un olvido: es la regla de 5.2. Lo
     * que sí se comprueba es que lo que llegue esté en su lista, porque un
     * estrato 9 o un nivel educativo inventado ensucia el diagnóstico de
     * condiciones de salud igual que un dato en blanco lo deja incompleto.
     *
     * Se validan siempre, encendido el bloque o apagado. Que lo apagado no se
     * guarde lo resuelve `guardar()`; rechazar el envío sería castigar a quien
     * cambió el parámetro con el formulario abierto.
     *
     * @return array<string, mixed>
     */
    public static function reglas(): array
    {
        return [
            'perfil' => ['nullable', 'array'],
            'perfil.estado_civil' => ['nullable', Rule::in(EstadoCivil::valores())],
            'perfil.nivel_educativo' => ['nullable', Rule::in(NivelEducativo::valores())],
            'perfil.numero_hijos' => ['nullable', 'integer', 'min:0', 'max:30'],
            'perfil.personas_a_cargo' => ['nullable', 'integer', 'min:0', 'max:30'],
            'perfil.tipo_vivienda' => ['nullable', Rule::in(TipoVivienda::valores())],
            'perfil.estrato' => ['nullable', 'integer', 'min:'.self::ESTRATO_MINIMO, 'max:'.self::ESTRATO_MAXIMO],
            'perfil.medio_transporte' => ['nullable', Rule::in(MedioTransporte::valores())],
            'perfil.tiempo_desplazamiento' => ['nullable', Rule::in(TiempoDesplazamiento::valores())],
        ];
    }

    /**
     * Los nombres con los que el usuario reconoce cada campo.
     *
     * @return array<string, string>
     */
    public static function atributos(): array
    {
        return [
            'perfil.estado_civil' => 'estado civil',
            'perfil.nivel_educativo' => 'nivel educativo',
            'perfil.numero_hijos' => 'número de hijos',
            'perfil.personas_a_cargo' => 'número de personas a cargo',
            'perfil.tipo_vivienda' => 'tipo de vivienda',
            'perfil.estrato' => 'estrato socioeconómico',
            'perfil.medio_transporte' => 'medio de transporte al trabajo',
            'perfil.tiempo_desplazamiento' => 'tiempo de desplazamiento al trabajo',
        ];
    }

    /**
     * Guarda el perfil de la persona, si hay bloque y hay algo que guardar.
     *
     * El perfil cuelga de la persona y no de la vinculación, así que un
     * reingreso actualiza el que ya existía en vez de crear un segundo: el
     * estrato o el nivel educativo no cambian porque a alguien lo recontraten,
     * pero sí pueden haber cambiado en el tiempo que estuvo fuera.
     *
     * @param  array<string, mixed>  $datos  el bloque `perfil` ya validado
     */
    public static function guardar(Persona $persona, array $datos): ?PerfilSociodemografico
    {
        if (! self::habilitado()) {
            return null;
        }

        $valores = self::valores($datos);

        if ($valores === []) {
            return null;
        }

        $perfil = $persona->perfil()->firstOrNew();
        $perfil->fill($valores)->save();

        return $perfil;
    }

    /**
     * Lo que de verdad se digitó: los campos del bloque que traen valor.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function valores(array $datos): array
    {
        $valores = [];

        foreach (self::CAMPOS as $campo) {
            $valor = $datos[$campo] ?? null;

            if ($valor !== null && $valor !== '') {
                $valores[$campo] = $valor;
            }
        }

        return $valores;
    }
}
