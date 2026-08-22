<?php

namespace App\Jobs;

use App\Models\AlertaEnviada;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Notificaciones\CorreoDeAlerta;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda por correo una alerta ya registrada.
 *
 * ## Por qué viajan dos identificadores y no un modelo
 *
 * Es el error clásico del aislamiento en segundo plano. `SerializesModels`
 * guardaría la clave y volvería a buscar el modelo al ejecutarse, pero el
 * trabajador de la cola arranca **sin ninguna empresa en contexto**: el global
 * scope de `PerteneceATenant` filtraría por una empresa que no existe y la
 * alerta no aparecería. Por eso el job serializa los dos números —la alerta y
 * su empresa—, restablece el contexto y solo entonces busca.
 *
 * `ContextoTenant::paraTenant()` es lo que garantiza la otra mitad: el contexto
 * se limpia al terminar aunque el envío falle, así que el siguiente job de la
 * misma cola no hereda la empresa del anterior.
 *
 * ## Reintentos
 *
 * Tres intentos con espera creciente, que es lo que pide la sección 5.5 para
 * los fallos del proveedor de correo. Los intentos se cuentan en la fila antes
 * de mandar, no después: si el proceso muere a mitad del envío, el número tiene
 * que reflejar que se intentó.
 *
 * El estado solo pasa a `fallida` cuando la cola agota los tres. Mientras haya
 * reintento por delante la alerta sigue pendiente, que es la verdad.
 */
class EnviarAlerta implements ShouldQueue
{
    use Queueable;

    /**
     * Los tres intentos de la sección 5.5.
     */
    public int $tries = 3;

    public function __construct(
        public readonly int $alertaId,
        public readonly int $empresaId,
    ) {}

    /**
     * La espera creciente entre intentos, en segundos: un minuto, cinco y
     * quince. Un proveedor de correo caído no se recupera en tres segundos.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ContextoTenant $contexto): void
    {
        $empresa = Tenant::find($this->empresaId);

        if (! $empresa instanceof Tenant) {
            return;
        }

        $contexto->paraTenant($empresa, function () use ($empresa): void {
            $alerta = AlertaEnviada::find($this->alertaId);

            // Ya enviada, o borrada: no hay nada que hacer. Es lo que hace que
            // reencolar un job a mano tampoco duplique el correo.
            if (! $alerta instanceof AlertaEnviada || ! $alerta->estaPendiente()) {
                return;
            }

            $alerta->contarIntento();

            Mail::to($alerta->destinatarios)->send(new CorreoDeAlerta($alerta, $empresa));

            $alerta->marcarEnviada();
        });
    }

    /**
     * Se llama una sola vez, cuando ya no queda reintento.
     */
    public function failed(Throwable $error): void
    {
        $empresa = Tenant::find($this->empresaId);

        if (! $empresa instanceof Tenant) {
            return;
        }

        app(ContextoTenant::class)->paraTenant($empresa, function () use ($error): void {
            AlertaEnviada::find($this->alertaId)?->marcarFallida($error);
        });
    }
}
