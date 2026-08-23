<?php

namespace App\CargaInicial;

use App\Models\Catalogo;
use App\Models\Ciudad;
use App\Models\Departamento;
use App\Models\Pais;

/**
 * Los catálogos globales del sistema, indexados por nombre, para resolver lo
 * que una plantilla trae escrito.
 *
 * El formulario de empleado recibe identificadores porque quien lo llena elige
 * de una lista. Una plantilla de Excel trae **nombres**, y alguien los escribió
 * a mano: «Sura EPS», «Antioquia», «Medellín». Aquí se traducen a los
 * identificadores que guarda la base de datos.
 *
 * Tres cosas que decide esta clase:
 *
 * 1. **Solo lo activo se puede cargar.** Se leen con `paraElegir()`, el mismo
 *    ámbito que llena los selectores del formulario. Una EPS liquidada sigue
 *    leyéndose en los empleados que ya la tenían y no se puede traer a nadie
 *    nuevo con ella, ni por pantalla ni por plantilla.
 * 2. **Se carga cada tabla una sola vez.** Un archivo de doscientos empleados
 *    resuelve mil doscientos nombres de entidad; hacerlo con una consulta por
 *    celda serían mil doscientas consultas para leer cinco tablas que caben en
 *    memoria.
 * 3. **Ningún catálogo de aquí lleva `tenant_id`.** Son los globales del
 *    sistema, iguales para todos los clientes: países, departamentos, ciudades,
 *    EPS, fondos, ARL, cajas y bancos.
 */
final class CatalogosDeCarga
{
    /** @var array<class-string<Catalogo>, array<string, int>> */
    private array $entidades = [];

    /** @var array<string, string>|null */
    private ?array $paises = null;

    /** @var array<string, int>|null */
    private ?array $departamentos = null;

    /** @var array<int, string>|null */
    private ?array $nombresDeDepartamento = null;

    /** @var array<string, list<array{id: int, departamento: int}>>|null */
    private ?array $ciudades = null;

    /**
     * El identificador de una de las cinco entidades, o nulo si ese nombre no
     * está en su catálogo.
     *
     * @param  class-string<Catalogo>  $catalogo
     */
    public function entidad(string $catalogo, string $nombre): ?int
    {
        if (! isset($this->entidades[$catalogo])) {
            $this->entidades[$catalogo] = $catalogo::query()
                ->paraElegir()
                ->get()
                ->mapWithKeys(fn (Catalogo $entidad): array => [
                    TextoDeCelda::comparable((string) $entidad->getAttribute('nombre')) => (int) $entidad->getKey(),
                ])
                ->all();
        }

        return $this->entidades[$catalogo][TextoDeCelda::comparable($nombre)] ?? null;
    }

    /**
     * El código ISO de un país escrito por su nombre, o nulo si no existe.
     */
    public function pais(string $nombre): ?string
    {
        $this->paises ??= Pais::query()
            ->paraElegir()
            ->get(['codigo', 'nombre'])
            ->mapWithKeys(fn (Pais $pais): array => [
                TextoDeCelda::comparable((string) $pais->getAttribute('nombre')) => (string) $pais->getAttribute('codigo'),
            ])
            ->all();

        return $this->paises[TextoDeCelda::comparable($nombre)] ?? null;
    }

    /**
     * El identificador de un departamento por su nombre, o nulo.
     */
    public function departamento(string $nombre): ?int
    {
        $this->cargarDepartamentos();

        return $this->departamentos[TextoDeCelda::comparable($nombre)] ?? null;
    }

    public function nombreDeDepartamento(int $id): ?string
    {
        $this->cargarDepartamentos();

        return $this->nombresDeDepartamento[$id] ?? null;
    }

    /**
     * El municipio con ese nombre dentro de un departamento, o nulo.
     */
    public function ciudadEn(int $departamento, string $nombre): ?int
    {
        foreach ($this->ciudadesLlamadas($nombre) as $ciudad) {
            if ($ciudad['departamento'] === $departamento) {
                return $ciudad['id'];
            }
        }

        return null;
    }

    /**
     * Todos los municipios del país que se llaman así.
     *
     * Son varios más veces de las que parece: en Colombia hay dos Albania, dos
     * Guadalupe y varios nombres repetidos en departamentos distintos. Quien
     * pregunte por un nombre suelto tiene que decidir qué hacer con eso, y por
     * eso este método devuelve la lista y no un identificador.
     *
     * @return list<array{id: int, departamento: int}>
     */
    public function ciudadesLlamadas(string $nombre): array
    {
        $this->ciudades ??= Ciudad::query()
            ->paraElegir()
            ->get(['id', 'departamento_id', 'nombre'])
            ->reduce(function (array $indice, Ciudad $ciudad): array {
                $clave = TextoDeCelda::comparable((string) $ciudad->getAttribute('nombre'));

                $indice[$clave][] = [
                    'id' => (int) $ciudad->getKey(),
                    'departamento' => (int) $ciudad->getAttribute('departamento_id'),
                ];

                return $indice;
            }, []);

        return $this->ciudades[TextoDeCelda::comparable($nombre)] ?? [];
    }

    private function cargarDepartamentos(): void
    {
        if ($this->departamentos !== null) {
            return;
        }

        $departamentos = Departamento::query()->paraElegir()->get(['id', 'nombre']);

        $this->departamentos = $departamentos
            ->mapWithKeys(fn (Departamento $departamento): array => [
                TextoDeCelda::comparable((string) $departamento->getAttribute('nombre')) => (int) $departamento->getKey(),
            ])
            ->all();

        $this->nombresDeDepartamento = $departamentos
            ->mapWithKeys(fn (Departamento $departamento): array => [
                (int) $departamento->getKey() => (string) $departamento->getAttribute('nombre'),
            ])
            ->all();
    }
}
