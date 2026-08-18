<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La función que leen todas las políticas de Row Level Security.
 *
 * Existe para que la expresión de la política sea `tenant_id = empresa_actual()`
 * y no la fórmula completa repetida en cada una de las treinta tablas de
 * negocio. Si mañana cambia la forma de transportar la empresa, cambia aquí y
 * en ningún otro sitio.
 *
 * Detalles que no son adorno:
 *
 * - `current_setting(..., true)` con el segundo argumento en `true` devuelve
 *   nulo si la variable no existe, en lugar de reventar. Sin eso, cualquier
 *   consulta hecha antes de que el middleware fije la empresa daría error.
 * - El `nullif` convierte la cadena vacía —lo que deja `fijarEmpresa(null)`— en
 *   nulo. Y `tenant_id = null` no es cierto para ninguna fila, así que la
 *   política **falla cerrada**: sin empresa en la sesión no se ve nada, igual
 *   que hace el global scope de la capa 1.
 * - `stable` le dice al planificador que dentro de una misma consulta el valor
 *   no cambia, y así puede evaluarla una vez en lugar de por fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $funcion = PoliticaRls::FUNCION;
        $variable = PoliticaRls::VARIABLE;

        DB::statement(<<<SQL
            create or replace function {$funcion}() returns bigint
                language sql
                stable
            as \$\$
                select nullif(current_setting('{$variable}', true), '')::bigint
            \$\$
            SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('drop function if exists '.PoliticaRls::FUNCION.'()');
    }
};
