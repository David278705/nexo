<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Los tres planes de la propuesta de precios (`PROD-24`), aceptada por David el
 * 2026-11-08 junto con el resto de las recomendaciones.
 *
 * Los números y la cuenta detrás están en `docs/salida/propuesta-de-precios.md`:
 * tres tramos por tamaño de empresa, del mercado objetivo del documento 1, 1.3.
 * Los precios son **mensuales y sin IVA**. El usuario adicional ($40.000 al mes)
 * no es un plan: se cobra aparte, y la tabla de planes no lo modela.
 *
 * Solo corre en una base sin planes (`catalogos:inicializar`). Después los planes
 * son del proveedor y se editan en el panel, en Suscripciones.
 */
class PlanesSeeder extends Seeder
{
    /**
     * @var list<array{nombre: string, precio_mensual: string, empleados_incluidos: int, usuarios_incluidos: int}>
     */
    public const PLANES = [
        ['nombre' => 'Micro', 'precio_mensual' => '250000.00', 'empleados_incluidos' => 10, 'usuarios_incluidos' => 1],
        ['nombre' => 'Pequeña', 'precio_mensual' => '450000.00', 'empleados_incluidos' => 50, 'usuarios_incluidos' => 2],
        ['nombre' => 'Mediana', 'precio_mensual' => '750000.00', 'empleados_incluidos' => 200, 'usuarios_incluidos' => 3],
    ];

    public function run(): void
    {
        foreach (self::PLANES as $plan) {
            Plan::query()->firstOrCreate(['nombre' => $plan['nombre']], [...$plan, 'activo' => true]);
        }
    }
}
