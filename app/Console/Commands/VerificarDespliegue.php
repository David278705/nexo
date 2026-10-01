<?php

namespace App\Console\Commands;

use App\Legal\DocumentoLegal;
use App\Legal\DocumentosLegales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * La revisión que corre cada contenedor antes de empezar a atender.
 *
 * Existe porque los errores de configuración de producción no se ven: la
 * aplicación arranca, la pantalla carga y nadie nota que la base se conecta
 * como superusuario —y RLS no protege nada— o que las alertas se están
 * escribiendo en un log en vez de mandarse. Aquí se ven, en el registro del
 * despliegue, antes del primer cliente.
 *
 * Dos niveles, a propósito:
 *
 * - **Falla**: lo que expone datos o rompe el aislamiento. El comando termina
 *   en error y el arranque se detiene, para que Railway mantenga la versión
 *   anterior en vez de servir una insegura.
 * - **Aviso**: lo que degrada pero no expone. Se escribe y se sigue, porque el
 *   primer despliegue se hace antes de tener, por ejemplo, el correo listo.
 */
class VerificarDespliegue extends Command
{
    protected $signature = 'despliegue:verificar';

    protected $description = 'Revisa la configuración de producción antes de arrancar (RLS, depuración, correo, registros)';

    private const OK = 'ok';

    private const AVISO = 'aviso';

    private const FALLA = 'falla';

    /** Transportes de correo que no mandan nada de verdad. */
    private const TRANSPORTES_QUE_NO_ENVIAN = ['log', 'array'];

    /** Drivers de log que escriben en el disco del contenedor, que se pierde. */
    private const LOGS_A_ARCHIVO = ['single', 'daily'];

    public function handle(): int
    {
        $revisiones = [
            $this->llaveDeLaAplicacion(),
            $this->depuracion(),
            $this->rolDeLaBase(),
            $this->direccionPublica(),
            $this->transporteDeCorreo(),
            $this->remitente(),
            $this->registros(),
            $this->monitoreo(),
            $this->discoDeCertificados(),
            $this->documentosLegales(),
        ];

        foreach ($revisiones as [$nivel, $mensaje]) {
            match ($nivel) {
                self::OK => $this->line("  ✔ {$mensaje}"),
                self::AVISO => $this->warn("  ! {$mensaje}"),
                default => $this->error("  ✘ {$mensaje}"),
            };
        }

        $fallas = count(array_filter($revisiones, fn (array $revision) => $revision[0] === self::FALLA));

        if ($fallas > 0) {
            $this->newLine();
            $this->error("{$fallas} problema(s) que impiden arrancar con seguridad. Ver docs/08-despliegue.md.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array{string, string} */
    private function llaveDeLaAplicacion(): array
    {
        return (string) config('app.key') === ''
            ? [self::FALLA, 'APP_KEY está vacía: sin ella no hay sesiones ni datos cifrados. Genera una con `php artisan key:generate --show`.']
            : [self::OK, 'APP_KEY definida.'];
    }

    /** @return array{string, string} */
    private function depuracion(): array
    {
        return app()->isProduction() && config('app.debug') === true
            ? [self::FALLA, 'APP_DEBUG está encendido en producción: cualquier error le mostraría al usuario el código y la configuración.']
            : [self::OK, 'Depuración apagada en producción.'];
    }

    /**
     * PostgreSQL no le aplica RLS a un superusuario ni a un rol con
     * `BYPASSRLS`. Es la revisión que justifica este comando entero.
     *
     * @return array{string, string}
     */
    private function rolDeLaBase(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [self::AVISO, 'La base no es PostgreSQL: no hay RLS que comprobar.'];
        }

        try {
            $rol = DB::selectOne('select current_user as nombre, rolsuper, rolbypassrls from pg_roles where rolname = current_user');
        } catch (Throwable $error) {
            return [self::FALLA, 'No se pudo consultar la base: '.$error->getMessage()];
        }

        if ($rol === null) {
            return [self::FALLA, 'No se encontró el rol de la conexión en pg_roles.'];
        }

        if ($rol->rolsuper || $rol->rolbypassrls) {
            return [self::FALLA, "La base se conecta como «{$rol->nombre}», que se salta RLS: el aislamiento entre empresas no protege nada. Conecta DB_URL con el rol de la aplicación (`despliegue:preparar-base`)."];
        }

        return [self::OK, "La base se conecta como «{$rol->nombre}», un rol al que RLS sí alcanza."];
    }

    /** @return array{string, string} */
    private function direccionPublica(): array
    {
        $url = (string) config('app.url');

        return Str::startsWith($url, 'https://')
            ? [self::OK, "APP_URL usa HTTPS ({$url})."]
            : [self::AVISO, "APP_URL no usa https:// ({$url}). Los enlaces de los correos —invitaciones, alertas— saldrían con esa dirección."];
    }

    /** @return array{string, string} */
    private function transporteDeCorreo(): array
    {
        $transporte = (string) config('mail.default');

        return in_array($transporte, self::TRANSPORTES_QUE_NO_ENVIAN, true)
            ? [self::AVISO, "MAIL_MAILER={$transporte}: ninguna alerta ni invitación sale de verdad."]
            : [self::OK, "Correo por «{$transporte}»."];
    }

    /** @return array{string, string} */
    private function remitente(): array
    {
        $remitente = (string) config('mail.from.address');

        return $remitente === '' || Str::endsWith($remitente, ['@example.com', '@example.org'])
            ? [self::AVISO, "MAIL_FROM_ADDRESS es «{$remitente}»: un correo desde un dominio que no es el tuyo termina en spam o rechazado."]
            : [self::OK, "Los correos salen desde {$remitente}."];
    }

    /**
     * El disco del contenedor se borra en cada despliegue: un log escrito ahí
     * no lo ve nadie y desaparece. Tiene que ir a `stderr`, que es lo que
     * Railway recoge y enseña.
     *
     * @return array{string, string}
     */
    private function registros(): array
    {
        $canal = (string) config('logging.default');
        $configuracion = (array) config("logging.channels.{$canal}");

        $canales = ($configuracion['driver'] ?? null) === 'stack'
            ? (array) ($configuracion['channels'] ?? [])
            : [$canal];

        $aArchivo = array_filter(
            $canales,
            fn (mixed $nombre) => in_array(config("logging.channels.{$nombre}.driver"), self::LOGS_A_ARCHIVO, true),
        );

        return $aArchivo === []
            ? [self::OK, 'Los registros no se escriben en el disco del contenedor.']
            : [self::AVISO, 'Los registros van a un archivo del contenedor ('.implode(', ', $aArchivo).'), que se borra en cada despliegue. Usa LOG_STACK=stderr.'];
    }

    /** @return array{string, string} */
    private function monitoreo(): array
    {
        return (string) config('sentry.dsn') === ''
            ? [self::AVISO, 'SENTRY_LARAVEL_DSN vacío: los errores de producción no llegan a ninguna parte.']
            : [self::OK, 'Sentry configurado.'];
    }

    /** @return array{string, string} */
    private function discoDeCertificados(): array
    {
        $disco = (string) config('filesystems.certificados');
        $driver = config("filesystems.disks.{$disco}.driver");

        return $driver === 'local'
            ? [self::AVISO, "Los certificados se guardan en el disco «{$disco}» del contenedor: hace falta un volumen montado en storage/app o se pierden en cada despliegue."]
            : [self::OK, "Los certificados se guardan en «{$disco}» ({$driver})."];
    }

    /**
     * Un documento legal en borrador se puede aceptar, pero lo que se acepta
     * no es el contrato: es un aviso de que el contrato falta. Avisa y no
     * detiene, porque el primer despliegue suele hacerse antes de tener los
     * textos revisados por el abogado.
     *
     * @return array{string, string}
     */
    private function documentosLegales(): array
    {
        $borradores = app(DocumentosLegales::class)->borradores();

        return $borradores === []
            ? [self::OK, 'Los documentos legales tienen su texto definitivo.']
            : [self::AVISO, 'Documentos legales todavía en borrador: '
                .implode(', ', array_map(fn (DocumentoLegal $d) => $d->titulo, $borradores))
                .'. Las empresas aceptarían un aviso, no el contrato.'];
    }
}
