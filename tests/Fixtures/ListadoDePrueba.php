<?php

namespace Tests\Fixtures;

use App\Listados\Listado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Un listado cualquiera montado sobre el armazón.
 *
 * Es la prueba de que agregar el sexto listado del sistema no vuelve a copiar
 * `opcion()`, `porPagina()`, `pagina()`, `total()` ni `tieneFiltros()`: **aquí
 * abajo no hay ninguno de los cinco**. Solo están sus dos filtros, sus dos
 * columnas ordenables y con qué se abre la pantalla.
 *
 * @extends Listado<RegistroListable>
 */
class ListadoDePrueba extends Listado
{
    /** @var list<string> */
    public const ESTADOS = ['abierto', 'cerrado'];

    protected function __construct(
        public readonly string $buscar,
        public readonly string $estado,
        string $orden,
        string $direccion,
        int $porPagina,
    ) {
        parent::__construct($orden, $direccion, $porPagina);
    }

    public static function desde(Request $peticion): self
    {
        return new self(
            buscar: self::texto($peticion, 'buscar'),
            estado: self::filtro($peticion, 'estado', self::ESTADOS, 'abierto'),
            orden: self::ordenPedido($peticion),
            direccion: self::direccionPedida($peticion),
            porPagina: self::porPagina($peticion),
        );
    }

    /**
     * @return list<array{clave: string, texto: string}>
     */
    public function aplicados(): array
    {
        $etiquetas = [];

        if ($this->buscar !== '') {
            $etiquetas[] = ['clave' => 'buscar', 'texto' => 'Búsqueda: '.$this->buscar];
        }

        if ($this->estado !== self::TODOS) {
            $etiquetas[] = ['clave' => 'estado', 'texto' => 'Estado: '.$this->estado];
        }

        return $etiquetas;
    }

    /**
     * @return Builder<RegistroListable>
     */
    protected function base(): Builder
    {
        return RegistroListable::query();
    }

    /**
     * @param  Builder<RegistroListable>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        if ($this->estado !== self::TODOS) {
            $consulta->where('estado', $this->estado);
        }

        if ($this->buscar !== '') {
            $consulta->where('titulo', 'ilike', '%'.$this->buscar.'%');
        }
    }

    /**
     * @return array<string, callable(Builder<RegistroListable>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'titulo' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('titulo', $sentido),
            'estado' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('estado', $sentido),
        ];
    }

    protected static function ordenPorDefecto(): string
    {
        return 'titulo';
    }

    /**
     * @return array<string, string|int>
     */
    protected function filtrosParaLaInterfaz(): array
    {
        return [
            'buscar' => $this->buscar,
            'estado' => $this->estado,
        ];
    }
}
