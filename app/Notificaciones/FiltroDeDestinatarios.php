<?php

namespace App\Notificaciones;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Los únicos correos a los que el sistema puede escribir mientras está en pruebas
 * (`PROD-29`).
 *
 * Con `MAIL_DESTINATARIOS_PERMITIDOS` definida —una lista separada por comas—,
 * cada correo que sale se queda solo con los destinatarios de esa lista; si no
 * queda ninguno, no se envía y se deja una línea en el log. Sin la variable, todo
 * sale normal: es lo que se usa cuando el sistema ya atiende clientes de verdad.
 *
 * Sirve para dos cosas a la vez: que un cliente de prueba con correos inventados
 * —o reales— no reciba nada mientras el sistema se prueba, y que el proveedor de
 * correo no rechace envíos a direcciones que todavía no puede atender.
 */
final class FiltroDeDestinatarios
{
    public function handle(MessageSending $evento): bool
    {
        $permitidos = self::permitidos();

        if ($permitidos === []) {
            return true;
        }

        $mensaje = $evento->message;
        $quedan = 0;

        foreach (['To', 'Cc', 'Bcc'] as $campo) {
            /** @var list<Address> $direcciones */
            $direcciones = $mensaje->{'get'.$campo}();
            $filtradas = array_values(array_filter(
                $direcciones,
                fn (Address $direccion): bool => in_array(mb_strtolower($direccion->getAddress()), $permitidos, true),
            ));
            $mensaje->{mb_strtolower($campo)}(...$filtradas);
            $quedan += count($filtradas);
        }

        if ($quedan === 0) {
            Log::info('Correo no enviado: ningún destinatario está en MAIL_DESTINATARIOS_PERMITIDOS.', [
                'asunto' => $mensaje->getSubject(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function permitidos(): array
    {
        $lista = (string) config('mail.destinatarios_permitidos', '');

        return array_values(array_filter(array_map(
            fn (string $correo): string => mb_strtolower(trim($correo)),
            explode(',', $lista),
        )));
    }
}
