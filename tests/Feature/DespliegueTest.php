<?php

use App\Legal\DocumentosLegales;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Los dos comandos que corren al arrancar un contenedor de producción:
 * `despliegue:preparar-base` y `despliegue:verificar`.
 *
 * Los roles de PostgreSQL son del servidor entero, no de una base, así que no
 * los deshace el rollback de `RefreshDatabase`. El rol que crean estas pruebas
 * se borra a mano al final, por una conexión aparte que no está dentro de la
 * transacción de la prueba.
 */
const ROL_DESPLIEGUE_PRUEBA = 'nexo_rol_despliegue_prueba';

const CLAVE_DESPLIEGUE_PRUEBA = 'clave-de-prueba-123';

/**
 * La conexión de las pruebas tal como venía, antes de que ninguna prueba le
 * cambie el usuario. Se guarda la primera vez, en el `beforeEach`.
 *
 * @return array<string, mixed>
 */
function configuracionDelSuperusuario(): array
{
    static $original = null;

    return $original ??= config('database.connections.pgsql');
}

/**
 * Una conexión con el superusuario de las pruebas, por fuera de la
 * transacción: lo que se escribe por ella no se deshace al terminar.
 */
function conexionDeSuperusuario(string $nombre): Connection
{
    config(["database.connections.{$nombre}" => configuracionDelSuperusuario()]);

    return DB::connection($nombre);
}

/**
 * La URL del superusuario de las pruebas, armada con la configuración real
 * para no escribir credenciales en la prueba.
 */
function urlDeSuperusuario(): string
{
    $c = configuracionDelSuperusuario();

    return "pgsql://{$c['username']}:{$c['password']}@{$c['host']}:{$c['port']}/{$c['database']}";
}

/**
 * Hace que la conexión de la aplicación «diga» ser el rol de prueba, sin
 * reconectar la que ya está abierta: el comando solo lee la configuración.
 */
function aplicacionConectadaComo(string $rol, string $clave): void
{
    config([
        'database.connections.pgsql.username' => $rol,
        'database.connections.pgsql.password' => $clave,
        'despliegue.url_administracion' => urlDeSuperusuario(),
    ]);
}

/**
 * Corre algo con un rol corriente, como lo haría la aplicación en producción.
 * Mismo mecanismo que `RlsTest`: `set role` dentro de un savepoint.
 */
function comoRolCorrienteDeDespliegue(Closure $callback): mixed
{
    DB::statement("do \$\$ begin if not exists (select from pg_roles where rolname = 'nexo_rol_verificacion_prueba') then create role nexo_rol_verificacion_prueba nologin; end if; end \$\$");

    return DB::transaction(function () use ($callback) {
        DB::statement('set role nexo_rol_verificacion_prueba');
        $resultado = $callback();
        DB::statement('reset role');

        return $resultado;
    });
}

beforeEach(function () {
    configuracionDelSuperusuario();
});

afterEach(function () {
    $limpieza = conexionDeSuperusuario('limpieza_de_roles');
    $existe = $limpieza->selectOne('select 1 as hay from pg_roles where rolname = ?', [ROL_DESPLIEGUE_PRUEBA]);

    if ($existe !== null) {
        $limpieza->statement('drop owned by '.ROL_DESPLIEGUE_PRUEBA);
        $limpieza->statement('drop role '.ROL_DESPLIEGUE_PRUEBA);
    }

    DB::purge('limpieza_de_roles');
});

describe('despliegue:preparar-base', function () {
    it('crea un rol que puede entrar, que no es superusuario y al que RLS sí alcanza', function () {
        $configuracionOriginal = configuracionDelSuperusuario();
        aplicacionConectadaComo(ROL_DESPLIEGUE_PRUEBA, CLAVE_DESPLIEGUE_PRUEBA);

        expect(Artisan::call('despliegue:preparar-base'))->toBe(0);

        // La prueba de verdad: conectarse con ese rol y su clave, y preguntarle
        // a PostgreSQL qué es.
        config(['database.connections.como_rol_nuevo' => array_merge($configuracionOriginal, [
            'username' => ROL_DESPLIEGUE_PRUEBA,
            'password' => CLAVE_DESPLIEGUE_PRUEBA,
        ])]);
        $rol = DB::connection('como_rol_nuevo')
            ->selectOne('select current_user as nombre, rolsuper, rolbypassrls, rolcreatedb, rolcreaterole from pg_roles where rolname = current_user');
        $puedeCrearEnPublic = DB::connection('como_rol_nuevo')
            ->selectOne("select has_schema_privilege('public', 'CREATE') as puede");
        DB::purge('como_rol_nuevo');

        expect($rol->nombre)->toBe(ROL_DESPLIEGUE_PRUEBA)
            ->and($rol->rolsuper)->toBeFalse()
            ->and($rol->rolbypassrls)->toBeFalse()
            ->and($rol->rolcreatedb)->toBeFalse()
            ->and($rol->rolcreaterole)->toBeFalse()
            ->and($puedeCrearEnPublic->puede)->toBeTrue();
    });

    it('es idempotente y le quita a un rol existente los privilegios que no debe tener', function () {
        aplicacionConectadaComo(ROL_DESPLIEGUE_PRUEBA, CLAVE_DESPLIEGUE_PRUEBA);
        Artisan::call('despliegue:preparar-base');

        // Alguien le dio BYPASSRLS a mano: la siguiente corrida lo quita.
        conexionDeSuperusuario('mano')->statement('alter role '.ROL_DESPLIEGUE_PRUEBA.' bypassrls');
        DB::purge('mano');

        expect(Artisan::call('despliegue:preparar-base'))->toBe(0);

        $rol = DB::selectOne('select rolbypassrls from pg_roles where rolname = ?', [ROL_DESPLIEGUE_PRUEBA]);

        expect($rol->rolbypassrls)->toBeFalse();
    });

    it('no hace nada si no hay URL de administración', function () {
        config(['despliegue.url_administracion' => null]);

        expect(Artisan::call('despliegue:preparar-base'))->toBe(0)
            ->and(Artisan::output())->toContain('DB_URL_ADMIN no está definida');
    });

    it('se niega si la aplicación usa el mismo usuario que la administración', function () {
        config(['despliegue.url_administracion' => urlDeSuperusuario()]);

        expect(Artisan::call('despliegue:preparar-base'))->toBe(1)
            ->and(Artisan::output())->toContain('usan el mismo usuario');
    });

    it('rechaza un nombre de rol que no se puede escribir sin comillas', function () {
        aplicacionConectadaComo('Rol-Con-Guiones', CLAVE_DESPLIEGUE_PRUEBA);

        expect(Artisan::call('despliegue:preparar-base'))->toBe(1)
            ->and(Artisan::output())->toContain('no es un nombre de rol válido');
    });
});

describe('despliegue:verificar', function () {
    it('no deja arrancar si la base se conecta como superusuario', function () {
        // Las pruebas se conectan como `postgres`, que es superusuario: es
        // exactamente el caso que hay que impedir en producción.
        expect(Artisan::call('despliegue:verificar'))->toBe(1)
            ->and(Artisan::output())->toContain('se salta RLS');
    });

    it('deja arrancar con un rol corriente', function () {
        $codigo = comoRolCorrienteDeDespliegue(fn () => Artisan::call('despliegue:verificar'));

        expect($codigo)->toBe(0)
            ->and(Artisan::output())->toContain('un rol al que RLS sí alcanza');
    });

    it('no deja arrancar con la depuración encendida en producción', function () {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        $codigo = comoRolCorrienteDeDespliegue(fn () => Artisan::call('despliegue:verificar'));

        expect($codigo)->toBe(1)
            ->and(Artisan::output())->toContain('APP_DEBUG está encendido en producción');
    });

    it('no deja arrancar sin APP_KEY', function () {
        config(['app.key' => '']);

        $codigo = comoRolCorrienteDeDespliegue(fn () => Artisan::call('despliegue:verificar'));

        expect($codigo)->toBe(1)
            ->and(Artisan::output())->toContain('APP_KEY está vacía');
    });

    it('avisa del correo y del remitente sin configurar, pero deja arrancar', function () {
        config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);

        $codigo = comoRolCorrienteDeDespliegue(fn () => Artisan::call('despliegue:verificar'));
        $salida = Artisan::output();

        expect($codigo)->toBe(0)
            ->and($salida)->toContain('MAIL_MAILER=log')
            ->and($salida)->toContain('hello@example.com');
    });

    it('avisa de los registros escritos en el disco del contenedor', function () {
        config(['logging.default' => 'stack', 'logging.channels.stack.channels' => ['single']]);

        comoRolCorrienteDeDespliegue(fn () => Artisan::call('despliegue:verificar'));

        expect(Artisan::output())->toContain('Usa LOG_STACK=stderr');
    });

    it('recuerda el volumen cuando los certificados viven en el disco local', function () {
        config(['filesystems.certificados' => 'local']);

        comoRolCorrienteDeDespliegue(fn () => Artisan::call('despliegue:verificar'));

        expect(Artisan::output())->toContain('hace falta un volumen montado');
    });

    it('avisa de los documentos legales que siguen en borrador, pero deja arrancar', function () {
        $carpeta = sys_get_temp_dir().'/nexo-legal-borrador-'.uniqid();
        mkdir($carpeta);
        file_put_contents("{$carpeta}/terminos.md", "---\ntitulo: Términos del servicio\nversion: 1\nestado: borrador\nexige_aceptacion: si\n---\n\nPendiente.\n");
        app()->instance(DocumentosLegales::class, new DocumentosLegales($carpeta));

        $codigo = comoRolCorrienteDeDespliegue(fn () => Artisan::call('despliegue:verificar'));

        expect($codigo)->toBe(0)
            ->and(Artisan::output())->toContain('Documentos legales todavía en borrador: Términos del servicio');
    });
});
