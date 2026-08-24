<?php

namespace App\CargaInicial;

use App\Models\RegistroDeCarga;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Presentacion\Formato;

/**
 * El historial de cargas que enseña el panel: «qué archivo, cuántas filas, quién
 * y cuándo» (documento 4, capítulo 5).
 *
 * **Recorre las empresas una por una y no hace una consulta global**, que es lo
 * que la regla de multiempresa exige de cualquier proceso que atraviese
 * clientes. Aquí además no es solo disciplina: `cargas_iniciales` tiene RLS, así
 * que una consulta sin empresa en contexto no devolvería ninguna fila por mucho
 * que se le quitara el global scope. El `owner` cruza empresas entrando en cada
 * una, no saltándose la puerta.
 *
 * El coste es una consulta por empresa. Es asumible y es a propósito: el panel
 * de un proveedor de PYMES tiene decenas de clientes, no millones, y la
 * alternativa —una tabla del panel fuera del aislamiento— pondría datos de un
 * cliente donde las políticas no llegan.
 */
class HistorialDeCargas
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    /**
     * Las últimas cargas de todas las empresas, de la más reciente a la más
     * antigua.
     *
     * @return list<array{id: int, fecha: string, empresa: string, plantilla: string, filas: int, quien: string}>
     */
    public function ultimas(int $cuantas = 25): array
    {
        $catalogo = new CatalogoDePlantillas;
        $registros = [];

        foreach (Tenant::query()->orderBy('nombre')->get() as $empresa) {
            foreach ($this->deLaEmpresa($empresa, $cuantas) as $registro) {
                $registros[] = [$registro, (string) $empresa->getAttribute('nombre')];
            }
        }

        // Las empresas se recorren por nombre y cada una entrega lo suyo en
        // orden; el historial se lee por fecha, así que se reordena al juntarlas.
        usort(
            $registros,
            fn (array $uno, array $otro): int => $otro[0]->created_at <=> $uno[0]->created_at,
        );

        return array_map(
            fn (array $par): array => [
                'id' => (int) $par[0]->getKey(),
                'fecha' => Formato::fecha($par[0]->created_at),
                'empresa' => $par[1],
                'plantilla' => $catalogo->nombreDe($par[0]->plantilla),
                'filas' => $par[0]->filas,
                'quien' => $par[0]->quien(),
            ],
            array_slice($registros, 0, $cuantas),
        );
    }

    /**
     * Las cargas ya ejecutadas de una empresa, indexadas por clave de plantilla.
     *
     * Es lo que la pantalla necesita para decir, antes de subir nada, qué le
     * falta a esta empresa y qué ya entró.
     *
     * @return array<string, array{fecha: string, filas: int, quien: string}>
     */
    public function porPlantilla(Tenant $empresa): array
    {
        $cargas = [];

        foreach ($this->deLaEmpresa($empresa, 100) as $registro) {
            $cargas[$registro->plantilla] ??= [
                'fecha' => Formato::fecha($registro->created_at),
                'filas' => $registro->filas,
                'quien' => $registro->quien(),
            ];
        }

        return $cargas;
    }

    /**
     * @return list<RegistroDeCarga>
     */
    private function deLaEmpresa(Tenant $empresa, int $cuantas): array
    {
        return $this->contexto->paraTenant($empresa, fn (): array => RegistroDeCarga::query()
            ->with('usuario')
            ->latest()
            ->limit($cuantas)
            ->get()
            ->all());
    }
}
