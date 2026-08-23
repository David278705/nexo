<?php

namespace App\Console\Commands;

use App\Dominio\Alertas\AlertaPorEnviar;
use App\Dominio\Alertas\RegistroDeAlertas;
use App\Jobs\EnviarAlerta;
use App\Models\AlertaEnviada;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Notificaciones\ProveedorDeCorreo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * El correo de prueba de entregabilidad (F3-06, documento 2, sección 5.5).
 *
 * Es lo que se corre después de apuntar el dominio al proveedor transaccional y
 * antes de dar por buena la configuración de un cliente. Responde de una sola
 * vez las dos preguntas de la sección:
 *
 * 1. **¿Llega a bandeja de entrada?** El correo sale por el mismo camino que
 *    las alertas de verdad —`EnviarAlerta` y `CorreoDeAlerta`, no un envío
 *    aparte—, así que si llega este, llegan aquellas. Probarlo con un `Mail::raw`
 *    escrito para la ocasión no probaría nada del correo que sí importa.
 * 2. **¿Queda registrado?** Deja su fila en `alertas_enviadas` con su asunto,
 *    su destinatario, su estado y sus intentos, que es el «registro de envíos
 *    consultable» de la misma sección, y se ve en el panel de alertas.
 *
 * Va **en el contexto de una empresa** y no suelto porque `alertas_enviadas` es
 * una tabla de negocio: sin empresa no hay fila que escribir, y el correo lleva
 * el nombre de la empresa en el pie como cualquier alerta suya.
 *
 * Se envía de forma síncrona, no por la cola. El sentido del comando es mirar
 * el resultado ahí mismo, y con la cola en base de datos habría que arrancar
 * además un trabajador para saber si funcionó.
 */
class ProbarEntregabilidad extends Command
{
    protected $signature = 'alertas:prueba
        {empresa : El identificador o el NIT de la empresa desde la que se manda}
        {correo : La dirección donde se quiere recibir el correo de prueba}';

    protected $description = 'Manda un correo de prueba por el proveedor transaccional y lo deja en el registro de envíos';

    public function handle(
        ContextoTenant $contexto,
        RegistroDeAlertas $registro,
        ProveedorDeCorreo $proveedor,
    ): int {
        $correo = (string) $this->argument('correo');

        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('«'.$correo.'» no es una dirección de correo.');

            return self::FAILURE;
        }

        $empresa = self::empresa((string) $this->argument('empresa'));

        if (! $empresa instanceof Tenant) {
            $this->error('No hay ninguna empresa con ese identificador ni con ese NIT.');

            return self::FAILURE;
        }

        // Advierte pero no bloquea: en local el correo va al log a propósito y
        // el comando tiene que servir igual para comprobar el registro.
        $advertencia = $proveedor->advertencia();

        if ($advertencia !== null) {
            $this->warn($advertencia);
        }

        $momento = Carbon::now();
        $alerta = AlertaPorEnviar::deEntregabilidad(
            $momento,
            'Prueba de entregabilidad del sistema de alertas',
            self::cuerpo($empresa, $momento),
        );

        return $contexto->paraTenant($empresa, function () use ($alerta, $correo, $empresa, $registro, $proveedor): int {
            $registrada = $registro->registrar($alerta, [$correo]);

            if (! $registrada instanceof AlertaEnviada) {
                $this->error('No se pudo registrar el correo de prueba.');

                return self::FAILURE;
            }

            try {
                EnviarAlerta::dispatchSync((int) $registrada->getKey(), (int) $empresa->getKey());
            } catch (Throwable $error) {
                $registrada->marcarFallida($error);

                $this->error('El proveedor «'.$proveedor->nombre().'» rechazó el envío: '.$error->getMessage());
                $this->line('Queda registrado como fallido, con '.$registrada->fresh()?->intentos.' intento(s).');

                return self::FAILURE;
            }

            $this->info('Correo de prueba enviado a '.$correo.' por «'.$proveedor->nombre().'».');
            $this->line('Registrado en el panel de alertas de '.$empresa->getAttribute('nombre').'.');

            return self::SUCCESS;
        });
    }

    /**
     * La empresa, buscada por identificador o por NIT.
     *
     * Por las dos cosas porque las dos son las que se tienen a mano: el NIT es
     * lo que se sabe de memoria del cliente al que se le está configurando el
     * dominio, y el identificador es lo que enseña la ficha del panel.
     */
    private static function empresa(string $referencia): ?Tenant
    {
        if (ctype_digit($referencia)) {
            $porId = Tenant::query()->find((int) $referencia);

            if ($porId instanceof Tenant) {
                return $porId;
            }
        }

        return Tenant::query()->where('nit', $referencia)->first();
    }

    /**
     * El texto del correo, que dice para qué sirve y qué hay que mirar.
     *
     * Quien lo recibe puede no ser quien lo mandó: la dirección de prueba suele
     * ser la del cliente. Un correo de prueba sin explicación es un correo raro
     * de un remitente nuevo, que es justo lo que se le enseña a marcar como
     * no deseado.
     */
    private static function cuerpo(Tenant $empresa, Carbon $momento): string
    {
        return implode("\n", [
            'Este es un correo de prueba del sistema de alertas de '.$empresa->getAttribute('nombre').'.',
            'Se envió el '.$momento->format('d/m/Y').' a las '.$momento->format('H:i').' para comprobar que los avisos llegan a la bandeja de entrada y no a la carpeta de correo no deseado.',
            'Si lo encontraste en no deseado, márcalo como correo legítimo: de eso depende que te lleguen los avisos de vencimiento de contratos y de periodos de prueba.',
            'No hay que responder ni hacer nada más.',
        ]);
    }
}
