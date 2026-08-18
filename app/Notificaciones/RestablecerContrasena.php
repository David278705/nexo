<?php

namespace App\Notificaciones;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El correo con el enlace para restablecer la contraseña.
 *
 * Reemplaza a la notificación de Laravel para que el texto esté en español y
 * diga la caducidad, que la sección 5.1 del documento de diseño fija en una
 * hora. La caducidad de verdad la impone el broker con
 * `config('auth.passwords.users.expire')`; aquí solo se cuenta.
 */
class RestablecerContrasena extends Notification
{
    public function __construct(private readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutos = (int) config('auth.passwords.users.expire');

        $enlace = route('acceso.restablecer.formulario', [
            'token' => $this->token,
            'correo' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Restablece tu contraseña')
            ->markdown('correos.restablecer-contrasena', [
                'enlace' => $enlace,
                'minutos' => $minutos,
            ]);
    }
}
