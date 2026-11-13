<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El origen de cada vinculación y el tipo de las prórrogas que trajo la carga
 * inicial (`PROD-33`, decidido por Angela el 2026-11-11).
 *
 * 1. `vinculaciones.origen`: «registro» o «carga_inicial». Las que ya existen
 *    salen de `fecha_corte_saldo`, que solo la llena la carga inicial.
 * 2. Las prórrogas que escribió la carga inicial pasan de «pactada» a
 *    «automática»: no hay cómo verificar que constaran por escrito. Se
 *    reconocen porque nacieron con la vinculación, en la misma carga; las que
 *    se registraron después en Nexo conservan el tipo que eligió el usuario.
 *
 * Las tablas tienen RLS forzada, así que la conversión se hace empresa por
 * empresa con su contexto fijado. Es una corrección de datos de una sola vez
 * y no pasa por el observador de auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinculaciones', function (Blueprint $tabla) {
            $tabla->string('origen', 20)->default('registro');
        });

        foreach (DB::table('tenants')->pluck('id') as $empresa) {
            PoliticaRls::fijarEmpresa((int) $empresa);

            DB::table('vinculaciones')
                ->whereNotNull('fecha_corte_saldo')
                ->update(['origen' => 'carga_inicial']);

            $cargadas = DB::table('vinculaciones')
                ->where('origen', 'carga_inicial')
                ->get(['id', 'created_at']);

            foreach ($cargadas as $vinculacion) {
                DB::table('periodos_contractuales')
                    ->where('vinculacion_id', $vinculacion->id)
                    ->where('evento', 'prorroga_pactada')
                    ->where('created_at', '<=', date('Y-m-d H:i:s', strtotime((string) $vinculacion->created_at) + 300))
                    ->update(['evento' => 'prorroga_automatica']);
            }
        }

        PoliticaRls::fijarEmpresa(null);
    }

    public function down(): void
    {
        Schema::table('vinculaciones', function (Blueprint $tabla) {
            $tabla->dropColumn('origen');
        });
    }
};
