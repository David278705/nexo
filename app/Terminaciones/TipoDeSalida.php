<?php

namespace App\Terminaciones;

/**
 * Los siete tipos de salida del documento 1, 7.2, con sus motivos dependientes
 * (7.3) y la categoría de rotación que deducen (7.4).
 *
 * Los tres datos viven juntos porque son el mismo hecho visto de tres maneras:
 * de qué tipo es la salida decide qué motivos tienen sentido, si el motivo se
 * autocompleta y en qué grupo del indicador de rotación cae. Separarlos
 * permitiría que un tipo quedara con los motivos de otro.
 *
 * **Los motivos se guardan como su texto**, no como un código. Son 35 frases
 * que ya están escritas en el documento y en el prototipo, y ninguna es una
 * regla de negocio que el sistema tenga que interpretar: lo único que hace con
 * ellas es enseñarlas y agruparlas en el reporte. La convención del proyecto
 * pide enumerados legibles, y no hay nada más legible que la frase misma.
 */
enum TipoDeSalida: string
{
    case Renuncia = 'renuncia';

    case MutuoAcuerdo = 'mutuo_acuerdo';

    case PeriodoDePrueba = 'periodo_de_prueba';

    case DespidoConJustaCausa = 'despido_con_justa_causa';

    case SinJustaCausa = 'sin_justa_causa';

    case ExpiracionDelPlazo = 'expiracion_del_plazo';

    case ObraTerminada = 'obra_terminada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Renuncia => 'Renuncia',
            self::MutuoAcuerdo => 'Terminación de contrato por mutuo acuerdo',
            self::PeriodoDePrueba => 'Terminación en periodo de prueba',
            self::DespidoConJustaCausa => 'Despido con justa causa',
            self::SinJustaCausa => 'Terminación sin justa causa',
            self::ExpiracionDelPlazo => 'Expiración del plazo fijo pactado',
            self::ObraTerminada => 'Terminación de la obra o labor contratada',
        };
    }

    /**
     * Los motivos que tienen sentido para este tipo de salida (documento 1,
     * 7.3), en el orden en que los enumera el documento.
     *
     * @return list<string>
     */
    public function motivos(): array
    {
        return match ($this) {
            self::Renuncia => [
                'Mejor oferta laboral — salario',
                'Mejor oferta laboral — desarrollo profesional',
                'Inconformidad con el salario o los beneficios',
                'Clima laboral o relación con el jefe inmediato',
                'Motivos personales o familiares',
                'Motivos de salud',
                'Estudios',
                'Cambio de ciudad o de país',
                'Distancia o tiempo de desplazamiento',
                'Emprendimiento propio',
                'No informó el motivo',
            ],
            self::MutuoAcuerdo => [
                'Reestructuración organizacional',
                'Acuerdo económico de retiro',
                'Cambio en las condiciones del cargo',
                'Motivos personales del trabajador',
                'Otro',
            ],
            self::PeriodoDePrueba => [
                'No cumple el perfil técnico del cargo',
                'Desempeño insuficiente durante la inducción',
                'Falta de adaptación al equipo o a la cultura',
                'Incumplimiento de horarios o asistencia',
                'Otro',
            ],
            self::DespidoConJustaCausa => [
                'Problemas disciplinarios',
                'Bajo desempeño',
                'Incumplimiento de las obligaciones del cargo',
                'Reconocimiento de pensión',
                'Otra justa causa legal',
            ],
            self::SinJustaCausa => [
                'Reestructuración o reducción de personal',
                'Cierre de un área o de un proyecto',
                'Situación económica de la empresa',
                'Desempeño no satisfactorio sin proceso disciplinario',
                'Duplicidad de funciones',
                'Decisión de la dirección',
                'Otro',
            ],
            self::ExpiracionDelPlazo => ['Expiración del plazo fijo pactado'],
            self::ObraTerminada => ['Finalización de la obra contratada'],
        };
    }

    /**
     * El motivo que el sistema completa solo, o nulo si hay que elegirlo.
     *
     * Son los dos tipos del documento 1, 7.3 que no tienen submotivos: en la
     * expiración del plazo «el motivo es el mismo tipo de salida» y en la obra
     * o labor hay uno solo. En los dos, el campo del formulario queda
     * deshabilitado y **el servidor ignora cualquier motivo que llegue**.
     */
    public function motivoAutomatico(): ?string
    {
        $motivos = $this->motivos();

        return count($motivos) === 1 ? $motivos[0] : null;
    }

    /**
     * El motivo con el que queda la terminación.
     *
     * Cuando el tipo lo autocompleta, se ignora lo que haya llegado: el campo
     * se enseña deshabilitado, y el servidor no se fía de que siga siéndolo.
     */
    public function motivoResultante(?string $pedido): string
    {
        return $this->motivoAutomatico() ?? trim((string) $pedido);
    }

    /**
     * La categoría macro del indicador de rotación (documento 1, 7.4).
     *
     * El mutuo acuerdo va en involuntaria por criterio de Angela, que el propio
     * documento explica: la motivación primera casi siempre es de la empresa, y
     * contarlo como voluntario inflaría el indicador y taparía un problema real.
     */
    public function categoriaDeRotacion(): CategoriaDeRotacion
    {
        return match ($this) {
            self::Renuncia => CategoriaDeRotacion::Voluntaria,
            self::MutuoAcuerdo,
            self::PeriodoDePrueba,
            self::DespidoConJustaCausa,
            self::SinJustaCausa => CategoriaDeRotacion::Involuntaria,
            self::ExpiracionDelPlazo, self::ObraTerminada => CategoriaDeRotacion::FinNatural,
        };
    }

    /**
     * Las opciones del selector, tanto del filtro como del formulario.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $tipo): array => ['valor' => $tipo->value, 'texto' => $tipo->etiqueta()],
            self::cases(),
        );
    }

    /**
     * Los motivos de cada tipo, tal como los necesita el formulario para
     * recargar la lista dependiente al cambiar el tipo de salida.
     *
     * Van con el motivo automático aparte, porque de él dependen las dos cosas
     * que la pantalla hace distinto: rellenar el campo y deshabilitarlo.
     *
     * @return array<string, array{motivos: list<string>, automatico: string|null}>
     */
    public static function motivosPorTipo(): array
    {
        $mapa = [];

        foreach (self::cases() as $tipo) {
            $mapa[$tipo->value] = [
                'motivos' => $tipo->motivos(),
                'automatico' => $tipo->motivoAutomatico(),
            ];
        }

        return $mapa;
    }

    /**
     * Todos los motivos de todos los tipos, sin repetir, para el filtro del
     * listado (documento 1, 11.3).
     *
     * «Otro» aparece en tres tipos distintos y aquí figura una sola vez: quien
     * filtra por «Otro» está preguntando por las salidas cuyo motivo nadie
     * precisó, sin importar de qué tipo fueran.
     *
     * @return list<string>
     */
    public static function todosLosMotivos(): array
    {
        $motivos = [];

        foreach (self::cases() as $tipo) {
            foreach ($tipo->motivos() as $motivo) {
                if (! in_array($motivo, $motivos, true)) {
                    $motivos[] = $motivo;
                }
            }
        }

        return $motivos;
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $tipo): string => $tipo->value, self::cases());
    }
}
