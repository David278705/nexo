<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Las cinco listas de entidades: EPS, fondos de pensión, ARL, cajas de
 * compensación y bancos.
 *
 * **Está vacío a propósito y no es un olvido.** El contenido de estas cinco
 * listas es la decisión D.5 de `docs/06-decisiones-pendientes.md`, marcada como
 * bloqueante y pendiente de Angela, que es quien sabe cuáles son las opciones
 * que las empresas usan de verdad. Una lista de EPS inventada por el
 * programador es peor que una lista vacía: la vacía se nota, la inventada se
 * usa durante meses y aparece en los datos de todos los clientes.
 *
 * Cuando lleguen, se agregan a `database/data/entidades.php` con la misma forma
 * que `paises.php` y este seeder las carga. Ni la migración ni el modelo
 * cambian: su forma no depende de la respuesta.
 */
class EntidadesSeeder extends Seeder
{
    /**
     * Las cinco tablas, en el orden en que aparecen en el formulario.
     *
     * @var list<string>
     */
    private const TABLAS = [
        'eps',
        'fondos_pension',
        'arls',
        'cajas_compensacion',
        'bancos',
    ];

    public function run(): void
    {
        $archivo = database_path('data/entidades.php');

        if (! file_exists($archivo)) {
            // Queda en el registro y no en la consola: este seeder también
            // corre desde una prueba y desde el despliegue, donde no hay nadie
            // mirando la pantalla.
            Log::warning(
                'Listados maestros sin cargar: falta la decisión D.5 (EPS, fondos, ARL, cajas y bancos).'
            );

            return;
        }

        /** @var array<string, list<array{codigo?: string|null, nombre: string}>> $entidades */
        $entidades = require $archivo;

        $ahora = now();

        foreach (self::TABLAS as $tabla) {
            $filas = [];

            foreach ($entidades[$tabla] ?? [] as $entidad) {
                $filas[] = [
                    'codigo' => $entidad['codigo'] ?? null,
                    'nombre' => $entidad['nombre'],
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            if ($filas === []) {
                continue;
            }

            DB::table($tabla)->upsert($filas, ['nombre'], ['codigo', 'updated_at']);
        }
    }
}
