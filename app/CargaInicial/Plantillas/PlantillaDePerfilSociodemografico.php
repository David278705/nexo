<?php

namespace App\CargaInicial\Plantillas;

use App\CargaInicial\CatalogosDeCarga;
use App\CargaInicial\Columna;
use App\CargaInicial\ErrorDeCarga;
use App\CargaInicial\FilaTraducida;
use App\CargaInicial\PlantaDeLaEmpresa;
use App\CargaInicial\Plantilla;
use App\Contratacion\BloqueSociodemografico;
use App\Contratacion\EstadoCivil;
use App\Contratacion\MedioTransporte;
use App\Contratacion\NivelEducativo;
use App\Contratacion\TiempoDesplazamiento;
use App\Contratacion\TipoVivienda;
use App\Models\Persona;
use Illuminate\Support\Carbon;

/**
 * La plantilla 2, «Perfil sociodemográfico» (documento 1, 5.2).
 *
 * Cuelga de la persona y no de la vinculación, así que aquí basta con que el
 * documento exista en la planta; no hace falta que su vinculación siga activa.
 *
 * **Todo es opcional menos el documento**, igual que en el formulario: son las
 * reglas de `BloqueSociodemografico`, sin copiar ninguna. Una fila con el
 * documento y nada más es una fila válida, y eso es deliberado: no hay
 * diferencia entre un perfil en blanco y un perfil que no existe.
 *
 * **Tres de sus listas están escritas en la plantilla con otras palabras que en
 * el sistema**, y por eso llevan sinónimos: la plantilla dice «Primaria» donde
 * el sistema dice «Básica Primaria», «Moto» donde dice «Motocicleta» y «De 30
 * min a 1 hora» donde dice «Entre 30 y 60 minutos». Cada sinónimo señala una
 * sola opción y ninguno adivina.
 *
 * El que falta es «Menos de 30 min», que la plantilla ofrece y el sistema no
 * tiene: en el sistema esa media hora son dos rangos —hasta 15 minutos y de 15
 * a 30—, así que traducirlo obligaría a escoger uno por el cliente. Se rechaza
 * con el mensaje que enumera los cinco rangos reales, y la pregunta de si la
 * plantilla debe cambiarse está en `ESTADO.md`.
 */
class PlantillaDePerfilSociodemografico extends Plantilla
{
    public function clave(): string
    {
        return 'perfil';
    }

    public function nombre(): string
    {
        return 'Perfil sociodemográfico';
    }

    public function hoja(): string
    {
        return 'Perfil sociodemografico';
    }

    public function columnas(): array
    {
        return [
            Columna::texto('Número de documento', 'numero_documento'),
            Columna::lista('Estado civil', 'perfil.estado_civil', EstadoCivil::paraElegir()),
            Columna::lista('Nivel educativo alcanzado', 'perfil.nivel_educativo', NivelEducativo::paraElegir(), [
                'Primaria' => 'basica_primaria',
                'Bachillerato' => 'bachiller',
                'Técnico' => 'tecnico_tecnologo',
                'Tecnólogo' => 'tecnico_tecnologo',
                'Posgrado' => 'posgraduado',
            ]),
            Columna::entero('Número de hijos', 'perfil.numero_hijos'),
            Columna::entero('Número de personas a cargo', 'perfil.personas_a_cargo'),
            Columna::lista('Tipo de vivienda', 'perfil.tipo_vivienda', TipoVivienda::paraElegir()),
            Columna::entero('Estrato socioeconómico', 'perfil.estrato'),
            Columna::lista('Medio de transporte al trabajo', 'perfil.medio_transporte', MedioTransporte::paraElegir(), [
                'Moto' => 'motocicleta',
                'Carro particular' => 'vehiculo_particular',
            ]),
            Columna::lista('Tiempo de desplazamiento', 'perfil.tiempo_desplazamiento', TiempoDesplazamiento::paraElegir(), [
                'De 30 min a 1 hora' => 'entre_30_y_60_minutos',
                'De 1 a 2 horas' => 'entre_1_y_2_horas',
                'Más de 2 horas' => 'mas_de_2_horas',
            ]),
        ];
    }

    public function completar(FilaTraducida $fila, CatalogosDeCarga $catalogos, PlantaDeLaEmpresa $planta): void
    {
        $documento = $fila->valor('numero_documento');

        if (! is_string($documento)) {
            return;
        }

        if ($planta->persona($documento) === null) {
            $fila->error(
                'Número de documento',
                "No hay ninguna persona con el documento {$documento}. Cárguela primero en la plantilla «Empleados actuales».",
                'numero_documento',
            );
        }
    }

    /**
     * El bloque está apagado: no hay nada que cargar y hay que decirlo.
     *
     * `BloqueSociodemografico::guardar()` no escribe nada cuando la empresa no
     * tiene el parámetro encendido, y con razón. Pero una carga que informa
     * «32 filas cargadas» y no escribe ninguna es peor que una que se niega: el
     * proveedor se iría de la reunión creyendo que el perfil quedó puesto.
     *
     * Se avisa en la fila 1 porque no es de ninguna fila: es de la empresa.
     */
    public function revisarElConjunto(array $filas, PlantaDeLaEmpresa $planta): array
    {
        if ($filas === [] || BloqueSociodemografico::habilitado()) {
            return [];
        }

        return [new ErrorDeCarga(
            1,
            null,
            'Esta empresa no tiene habilitado el perfil sociodemográfico, así que nada de este archivo se guardaría. '.
            'Actívelo en los parámetros de la empresa y vuelva a pasarlo.',
        )];
    }

    /**
     * El perfil se guarda por donde lo guarda el asistente: decide
     * `BloqueSociodemografico`, que es quien sabe si la empresa lo tiene
     * encendido y quien no crea la fila cuando no hay nada que poner.
     *
     * Cuelga de la persona y no de la vinculación (documento 1, 5.2), así que
     * aquí se busca por documento y no por empleado activo.
     */
    public function escribir(array $valores, Carbon $fechaDeLaCarga): void
    {
        $persona = Persona::query()
            ->conDocumento((string) $valores['numero_documento'])
            ->firstOrFail();

        /** @var array<string, mixed> $perfil */
        $perfil = $valores['perfil'] ?? [];

        BloqueSociodemografico::guardar($persona, $perfil);
    }

    public function reglas(FilaTraducida $fila): array
    {
        return [
            'numero_documento' => ['required', 'string'],
            ...BloqueSociodemografico::reglas(),
        ];
    }

    public function atributos(): array
    {
        return [
            'numero_documento' => 'número de documento',
            ...BloqueSociodemografico::atributos(),
        ];
    }
}
