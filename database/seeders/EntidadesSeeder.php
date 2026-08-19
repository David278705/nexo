<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Las cinco listas de entidades: EPS, fondos de pensión, ARL, cajas de
 * compensación y bancos.
 *
 * El contenido de estas cinco listas era la decisión D.5 de
 * `docs/06-decisiones-pendientes.md`, y llegó en
 * `plantillas/Listas_Desplegables_Formulario_Empleados.xlsx`. Está copiado en
 * `database/data/entidades.php`, que es lo que lee este seeder. Ni la migración
 * ni el modelo cambiaron: su forma nunca dependió de la respuesta.
 *
 * Sigue leyendo el archivo con `file_exists` en vez de darlo por sentado: si
 * alguien lo borra, el resto de los catálogos —geografía y festivos— tiene que
 * poder cargarse igual, y el aviso queda en el registro.
 *
 * **Actualiza en vez de duplicar.** Correrlo dos veces deja las mismas filas, y
 * un nombre que cambie en el archivo se refleja sin crear una entidad nueva:
 * `nombre` es la clave. Una EPS que desaparezca del archivo **no** se borra de
 * la tabla, se queda para que los empleados que ya la tenían la sigan leyendo;
 * desactivarla es una acción del panel del proveedor (F1B-04).
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
