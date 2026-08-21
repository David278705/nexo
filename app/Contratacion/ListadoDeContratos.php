<?php

namespace App\Contratacion;

use App\Listados\Listado;
use App\Models\PeriodoContractual;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El listado de contratos (documento 3, sección 5.4) y sus filtros.
 *
 * **La vista por defecto son los que vencen en los próximos 90 días.** No es un
 * filtro cualquiera: la pantalla existe para que nadie se entere de un
 * vencimiento el día después, así que abrirla con los 43 contratos de la
 * empresa sería enseñar mucho y decir nada.
 *
 * Una fila es una vinculación activa, no un tramo del historial: el contrato de
 * una persona es uno solo, aunque lleve tres prórrogas. Las vinculaciones
 * inactivas no aparecen —el contrato de quien ya salió no hay que gestionarlo—,
 * y por eso no hay filtro de estado del empleado.
 *
 * El armazón —la dirección, el orden, la paginación y la consulta única que
 * comparten la pantalla y la exportación— lo pone `Listado`, el mismo de
 * `ListadoDeEmpleados`. Aquí queda solo lo propio: sus tres filtros, sus
 * columnas ordenables y sus filas.
 *
 * @extends Listado<Vinculacion>
 */
class ListadoDeContratos extends Listado
{
    use OrdenaPorEmpleado;

    /**
     * El rango de vencimiento por defecto, en días.
     */
    public const VENCE_POR_DEFECTO = '90';

    /**
     * Los rangos de vencimiento del prototipo, con el texto que se lee.
     *
     * Los tres rangos de días **incluyen a los que ya vencieron**: un contrato
     * vencido sin gestionar es justo lo que más urge de esta pantalla, y
     * esconderlo del rango por defecto sería esconder el problema. Quien quiera
     * verlos solos tiene su propia opción.
     */
    public const RANGOS_DE_VENCIMIENTO = [
        ['valor' => '30', 'texto' => 'Vencen en 30 días'],
        ['valor' => '60', 'texto' => 'Vencen en 60 días'],
        ['valor' => '90', 'texto' => 'Vencen en 90 días'],
        ['valor' => 'vencidos', 'texto' => 'Ya vencidos'],
        ['valor' => self::TODOS, 'texto' => 'Todos los contratos'],
    ];

    /**
     * El filtro de número de prórrogas. El valor es el mínimo que se exige;
     * `0` es exactamente ninguna.
     */
    public const RANGOS_DE_PRORROGAS = [
        ['valor' => '0', 'texto' => 'Sin prórrogas'],
        ['valor' => '1', 'texto' => '1 o más'],
        ['valor' => '3', 'texto' => '3 o más'],
        ['valor' => self::TODOS, 'texto' => 'Cualquier número de prórrogas'],
    ];

    protected function __construct(
        public readonly string $vence,
        public readonly string $contrato,
        public readonly string $prorrogas,
        string $orden,
        string $direccion,
        int $porPagina,
    ) {
        parent::__construct($orden, $direccion, $porPagina);
    }

    /**
     * Lee los filtros de la petición y los deja normalizados.
     */
    public static function desde(Request $peticion): self
    {
        return new self(
            vence: self::filtro($peticion, 'vence', self::valoresDe(self::RANGOS_DE_VENCIMIENTO), self::VENCE_POR_DEFECTO),
            contrato: self::filtro($peticion, 'contrato', TipoContrato::valores()),
            prorrogas: self::filtro($peticion, 'prorrogas', self::valoresDe(self::RANGOS_DE_PRORROGAS)),
            orden: self::ordenPedido($peticion),
            direccion: self::direccionPedida($peticion),
            porPagina: self::porPagina($peticion),
        );
    }

    /**
     * Los filtros puestos, como etiquetas removibles.
     *
     * El rango de vencimiento sale siempre que no sea «todos», incluido su
     * valor por defecto: la pantalla no enseña los contratos indefinidos y eso
     * tiene que verse, o parece que faltan.
     *
     * @return list<array{clave: string, texto: string}>
     */
    public function aplicados(): array
    {
        $etiquetas = [];

        if ($this->vence !== self::TODOS) {
            $etiquetas[] = [
                'clave' => 'vence',
                'texto' => 'Vencimiento: '.self::textoDe(self::RANGOS_DE_VENCIMIENTO, $this->vence),
            ];
        }

        if ($this->contrato !== self::TODOS) {
            $etiquetas[] = [
                'clave' => 'contrato',
                'texto' => 'Contrato: '.TipoContrato::from($this->contrato)->etiqueta(),
            ];
        }

        if ($this->prorrogas !== self::TODOS) {
            $etiquetas[] = [
                'clave' => 'prorrogas',
                'texto' => 'Prórrogas: '.self::textoDe(self::RANGOS_DE_PRORROGAS, $this->prorrogas),
            ];
        }

        return $etiquetas;
    }

    /**
     * Una fila de la tabla y de la exportación, con las columnas del documento
     * 3, sección 5.4.
     *
     * «Inicio» es el del periodo vigente y no la fecha de ingreso: en un
     * contrato con tres prórrogas, el tramo que está corriendo empezó el día en
     * que arrancó la última, y es esa la fecha que se contrasta con el
     * vencimiento de la columna siguiente. La fecha de ingreso sigue estando en
     * la ficha del empleado, que es donde se pregunta por la antigüedad.
     *
     * @return array<string, string|int>
     */
    public static function fila(Vinculacion $vinculacion, ?Carbon $hoy = null): array
    {
        $historial = HistorialDeContrato::con($vinculacion->periodos);
        $vigencia = VigenciaDelContrato::de($historial, $hoy);
        $vigente = $historial->vigente();
        $persona = $vinculacion->persona;

        return [
            'id' => (int) $vinculacion->getKey(),
            'empleado' => $persona->nombreCompleto(),
            'documento' => (string) $persona->numero_documento,
            'cargo' => (string) $vinculacion->getAttribute('cargo'),
            'contrato' => ($historial->tipoDeContratoVigente() ?? $vinculacion->tipo_contrato)->etiqueta(),
            'inicio' => Formato::fecha(
                $vigente instanceof PeriodoContractual ? $vigente->fecha_inicio : $vinculacion->fecha_ingreso
            ),
            'vence' => Formato::fecha($vigencia->fechaDeVencimiento()),
            'prorrogas' => $historial->prorrogasAcumuladas(),
            'preaviso' => Formato::fecha($vigencia->fechaLimiteDePreaviso()),
            'estado' => $vigencia->estado(),
            'tono' => $vigencia->tono(),
        ];
    }

    /**
     * Cuántos contratos vencieron sin que nadie registrara la prórroga ni la
     * terminación.
     *
     * Va sin filtros a propósito: es el aviso de la cabecera, que responde a
     * «cuántos tengo mal», no a «cuántos estoy viendo».
     */
    public static function vencidosSinGestionar(?Carbon $hoy = null): int
    {
        return Vinculacion::query()
            ->activas()
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', ($hoy ?? Carbon::today())->toDateString())
            ->count();
    }

    /**
     * Cuántos contratos vencen dentro del rango, sin contar los ya vencidos.
     *
     * Es el «7 vencen en los próximos 90 días» de la cabecera.
     */
    public static function porVencer(int $dias, ?Carbon $hoy = null): int
    {
        $desde = ($hoy ?? Carbon::today())->copy()->startOfDay();

        return Vinculacion::query()
            ->activas()
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '>=', $desde->toDateString())
            ->whereDate('fecha_fin', '<=', $desde->copy()->addDays($dias)->toDateString())
            ->count();
    }

    /**
     * @return Builder<Vinculacion>
     */
    protected function base(): Builder
    {
        return Vinculacion::query()->activas()->with(['persona', 'periodos']);
    }

    /**
     * @param  Builder<Vinculacion>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        $this->filtrarPorVencimiento($consulta);

        if ($this->contrato !== self::TODOS) {
            $consulta->where('tipo_contrato', $this->contrato);
        }

        $this->filtrarPorProrrogas($consulta);
    }

    /**
     * @return array<string, callable(Builder<Vinculacion>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'empleado' => self::porNombreDeLaPersona(...),
            'contrato' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('tipo_contrato', $sentido),
            'inicio' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('fecha_ingreso', $sentido),
            'prorrogas' => fn (Builder $consulta, string $sentido) => $consulta
                ->orderBy(self::conteoDeProrrogas(), $sentido),
            // Los que no vencen van al final en los dos sentidos: un contrato
            // indefinido no es «el que vence primero» ni «el que vence último».
            'vence' => fn (Builder $consulta, string $sentido) => $consulta
                ->orderByRaw("fecha_fin {$sentido} nulls last"),
        ];
    }

    protected static function ordenPorDefecto(): string
    {
        return 'vence';
    }

    /**
     * @return array<string, string|int>
     */
    protected function filtrosParaLaInterfaz(): array
    {
        return [
            'vence' => $this->vence,
            'contrato' => $this->contrato,
            'prorrogas' => $this->prorrogas,
        ];
    }

    /**
     * El rango de vencimiento.
     *
     * Se filtra por `fecha_fin` de la vinculación, que es el vencimiento
     * vigente: la columna se mueve con cada prórroga junto al historial
     * (`Vinculacion::registrarPeriodo()`). Hacerlo por el último tramo obligaría
     * a una subconsulta por fila para responder lo mismo.
     *
     * @param  Builder<Vinculacion>  $consulta
     */
    private function filtrarPorVencimiento(Builder $consulta): void
    {
        if ($this->vence === self::TODOS) {
            return;
        }

        $hoy = Carbon::today();

        $consulta->whereNotNull('fecha_fin');

        if ($this->vence === 'vencidos') {
            $consulta->whereDate('fecha_fin', '<', $hoy->toDateString());

            return;
        }

        $consulta->whereDate('fecha_fin', '<=', $hoy->copy()->addDays((int) $this->vence)->toDateString());
    }

    /**
     * @param  Builder<Vinculacion>  $consulta
     */
    private function filtrarPorProrrogas(Builder $consulta): void
    {
        if ($this->prorrogas === self::TODOS) {
            return;
        }

        $minimo = (int) $this->prorrogas;

        if ($minimo === 0) {
            $consulta->whereDoesntHave('periodos', self::soloProrrogas(...));

            return;
        }

        $consulta->whereHas('periodos', self::soloProrrogas(...), '>=', $minimo);
    }

    /**
     * Acota una consulta de tramos a las prórrogas.
     *
     * El cambio a indefinido y el periodo inicial no cuentan; quien decide eso
     * es el scope del modelo, para que el conteo de la pantalla y el de
     * `HistorialDeContrato` no puedan discrepar.
     *
     * @param  Builder<PeriodoContractual>  $tramo
     */
    private static function soloProrrogas(Builder $tramo): void
    {
        $tramo->prorrogas();
    }

    /**
     * @return Builder<PeriodoContractual>
     */
    private static function conteoDeProrrogas(): Builder
    {
        return PeriodoContractual::query()
            ->selectRaw('count(*)')
            ->prorrogas()
            ->whereColumn('periodos_contractuales.vinculacion_id', 'vinculaciones.id');
    }
}
