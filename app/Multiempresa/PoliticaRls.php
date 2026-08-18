<?php

namespace App\Multiempresa;

use Illuminate\Support\Facades\DB;

/**
 * Capa 2 del aislamiento entre empresas: Row Level Security de PostgreSQL.
 *
 * La capa 1 —el global scope de `PerteneceATenant`— se puede saltar sin querer:
 * una consulta con el query builder, un `DB::select` en un reporte, un comando
 * de consola. En todos esos casos el filtro del ORM no aplica y la base de
 * datos es lo único que queda en medio.
 *
 * Aquí viven las dos mitades del mecanismo, juntas a propósito:
 *
 * 1. `activarEn()` escribe la política en la tabla. Lo llama cada migración que
 *    crea una tabla de negocio, en la misma migración, nunca después.
 * 2. `fijarEmpresa()` pone el identificador en la variable de sesión que la
 *    política consulta. Lo llama `ContextoTenant`, así que en la práctica lo
 *    hace el middleware al resolver la empresa de la petición.
 *
 * La expresión de la política no se repite en ninguna migración: se resuelve
 * con la función `empresa_actual()` de PostgreSQL, creada por su propia
 * migración.
 *
 * ## Advertencia sobre el rol de conexión
 *
 * PostgreSQL **no aplica RLS a los superusuarios ni a los roles con
 * `BYPASSRLS`**. El usuario con el que la aplicación se conecta en producción
 * tiene que ser un rol normal, sin esos atributos. Si la aplicación se conecta
 * como `postgres`, esta capa entera queda decorativa.
 *
 * Por eso las políticas se declaran además con `FORCE ROW LEVEL SECURITY`: sin
 * `FORCE`, el dueño de la tabla también se salta la política, y el dueño suele
 * ser justamente el rol que corrió las migraciones.
 */
class PoliticaRls
{
    /**
     * El nombre de la variable de sesión. Lleva prefijo porque PostgreSQL solo
     * admite parámetros personalizados con la forma `prefijo.nombre`.
     */
    public const VARIABLE = 'nexo.tenant_id';

    /**
     * La función SQL que traduce la variable de sesión a un identificador.
     */
    public const FUNCION = 'empresa_actual';

    /**
     * Activa RLS en una tabla de negocio y le escribe su política.
     *
     * Se llama desde la migración que crea la tabla:
     *
     *     PoliticaRls::activarEn('vinculaciones');
     *
     * La política es una sola y cubre lectura y escritura: `USING` decide qué
     * filas se ven, `WITH CHECK` decide cuáles se pueden escribir. Sin la
     * segunda mitad se podría insertar una fila en otra empresa aunque después
     * no se pudiera leer.
     */
    public static function activarEn(string $tabla): void
    {
        if (! self::esPostgres()) {
            return;
        }

        $columna = AlcanceTenant::COLUMNA;
        $politica = self::nombreDeLaPolitica($tabla);
        $funcion = self::FUNCION;

        DB::statement("alter table {$tabla} enable row level security");
        DB::statement("alter table {$tabla} force row level security");

        DB::statement(<<<SQL
            create policy {$politica} on {$tabla}
                using ({$columna} = {$funcion}())
                with check ({$columna} = {$funcion}())
            SQL);
    }

    /**
     * Deshace lo anterior. La usan los `down()` de las migraciones.
     */
    public static function desactivarEn(string $tabla): void
    {
        if (! self::esPostgres()) {
            return;
        }

        $politica = self::nombreDeLaPolitica($tabla);

        DB::statement("drop policy if exists {$politica} on {$tabla}");
        DB::statement("alter table {$tabla} disable row level security");
    }

    public static function nombreDeLaPolitica(string $tabla): string
    {
        return "{$tabla}_aislamiento_por_empresa";
    }

    /**
     * Fija la empresa de la sesión de base de datos.
     *
     * `null` la deja vacía, que con la política escrita como está significa no
     * ver ninguna fila. Limpiarla importa tanto como fijarla: una conexión
     * reutilizada que arrastre la empresa anterior es exactamente la fuga que
     * esta capa existe para impedir.
     *
     * El tercer argumento de `set_config` en `false` la hace de sesión y no de
     * transacción: si fuera de transacción, se perdería en cada commit y la
     * política dejaría de encontrarla a mitad de la petición.
     */
    public static function fijarEmpresa(?int $id): void
    {
        if (! self::esPostgres()) {
            return;
        }

        DB::statement('select set_config(?, ?, false)', [
            self::VARIABLE,
            (string) $id,
        ]);
    }

    /**
     * Lo que la base de datos cree que es la empresa en curso.
     *
     * Es para pruebas y diagnóstico: el código de la aplicación pregunta por la
     * empresa a `ContextoTenant`, no a PostgreSQL.
     */
    public static function empresaFijada(): ?int
    {
        if (! self::esPostgres()) {
            return null;
        }

        /** @var object{empresa: int|string|null}|null $fila */
        $fila = DB::selectOne('select '.self::FUNCION.'() as empresa');

        return $fila?->empresa === null ? null : (int) $fila->empresa;
    }

    private static function esPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
