<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;

/**
 * Crea el rol de PostgreSQL con el que se conecta la aplicación, para que RLS
 * proteja de verdad.
 *
 * **El problema que resuelve:** PostgreSQL nunca le aplica RLS a un
 * superusuario, ni con `FORCE ROW LEVEL SECURITY`. Railway entrega la base con
 * un único usuario, `postgres`, que es superusuario. Si la aplicación se
 * conectara con él, la segunda capa del aislamiento entre empresas —la última
 * línea de defensa, regla 1 del proyecto— quedaría decorativa. `RlsTest` lo
 * prueba cambiando a un rol corriente con `set role`; esto crea ese rol en el
 * servidor de verdad.
 *
 * **Cómo:** se conecta con `DB_URL_ADMIN` (el superusuario) y crea el rol cuyo
 * nombre y clave ya vienen en `DB_URL`, que es la conexión de la aplicación.
 * Así hay una sola fuente para la clave y no dos que se puedan desincronizar.
 * El rol queda sin superusuario, sin `BYPASSRLS` y sin permisos para crear
 * bases ni roles; puede crear tablas en `public`, y como es él quien corre las
 * migraciones, es el dueño de todo lo que crea. `FORCE ROW LEVEL SECURITY`
 * existe justamente para que tampoco el dueño se salte las políticas.
 *
 * Es idempotente: correrlo en cada despliegue no cambia nada si el rol ya está
 * bien, y si la clave de `DB_URL` cambió, la actualiza.
 */
class PrepararBase extends Command
{
    protected $signature = 'despliegue:preparar-base';

    protected $description = 'Crea o ajusta el rol sin privilegios con el que se conecta la aplicación (RLS)';

    /**
     * Un identificador de PostgreSQL sin comillas. Se valida antes de
     * interpolarlo en el SQL: los nombres de rol no admiten parámetros.
     */
    private const NOMBRE_DE_ROL_VALIDO = '/^[a-z_][a-z0-9_]{0,62}$/';

    public function handle(): int
    {
        $urlAdministracion = (string) config('despliegue.url_administracion');

        if ($urlAdministracion === '') {
            $this->line('DB_URL_ADMIN no está definida: no se crea ningún rol. El de la aplicación tiene que existir ya.');

            return self::SUCCESS;
        }

        $nombreConexion = (string) config('database.default');
        $aplicacion = $this->configuracionDe($nombreConexion);

        if (($aplicacion['driver'] ?? null) !== 'pgsql') {
            $this->line('La base no es PostgreSQL: RLS no aplica y no hay rol que crear.');

            return self::SUCCESS;
        }

        $rol = (string) ($aplicacion['username'] ?? '');
        $clave = (string) ($aplicacion['password'] ?? '');
        $base = (string) ($aplicacion['database'] ?? '');

        if (preg_match(self::NOMBRE_DE_ROL_VALIDO, $rol) !== 1) {
            $this->error("El usuario de DB_URL («{$rol}») no es un nombre de rol válido: solo minúsculas, números y guion bajo.");

            return self::FAILURE;
        }

        if ($clave === '') {
            $this->error('DB_URL no trae clave: el rol de la aplicación necesita una para poder conectarse.');

            return self::FAILURE;
        }

        $administracion = $this->conexionDeAdministracion($nombreConexion, $urlAdministracion);

        if ($administracion->getConfig('username') === $rol) {
            $this->error("DB_URL y DB_URL_ADMIN usan el mismo usuario («{$rol}»). DB_URL tiene que ser un rol propio de la aplicación, no el superusuario.");

            return self::FAILURE;
        }

        $this->crearOAjustarRol($administracion, $rol, $clave, $base);

        $this->info("Rol «{$rol}» listo: sin superusuario y sin BYPASSRLS. RLS lo alcanza.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function configuracionDe(string $nombreConexion): array
    {
        /** @var array<string, mixed> $configuracion */
        $configuracion = (array) config("database.connections.{$nombreConexion}");

        return (new ConfigurationUrlParser)->parseConfiguration($configuracion);
    }

    /**
     * Una conexión aparte, con la misma configuración que la de la aplicación
     * salvo la URL. Vive solo mientras corre este comando.
     */
    private function conexionDeAdministracion(string $nombreConexion, string $url): Connection
    {
        config([
            'database.connections.despliegue_administracion' => array_merge(
                (array) config("database.connections.{$nombreConexion}"),
                ['url' => $url],
            ),
        ]);

        return DB::connection('despliegue_administracion');
    }

    private function crearOAjustarRol(Connection $conexion, string $rol, string $clave, string $base): void
    {
        $claveSql = $conexion->getPdo()->quote($clave);

        $conexion->statement(<<<SQL
            do \$\$ begin
                if not exists (select from pg_roles where rolname = '{$rol}') then
                    create role "{$rol}" login;
                end if;
            end \$\$
            SQL);

        // Se ajusta siempre, también si ya existía: deja la clave igual a la de
        // DB_URL y quita cualquier privilegio que alguien le hubiera dado a mano.
        $conexion->statement(
            "alter role \"{$rol}\" with login password {$claveSql} nosuperuser nobypassrls nocreatedb nocreaterole"
        );

        // El nombre de la base no se valida como el del rol —es el que Railway
        // haya puesto—, así que se cita como identificador, doblando comillas.
        $baseSql = '"'.str_replace('"', '""', $base).'"';

        $conexion->statement("grant connect on database {$baseSql} to \"{$rol}\"");

        // PostgreSQL 15 le quitó a todos el permiso de crear en `public`; el rol
        // lo necesita porque es él quien corre las migraciones.
        $conexion->statement("grant usage, create on schema public to \"{$rol}\"");
    }
}
