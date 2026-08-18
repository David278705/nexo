<?php

namespace App\Notificaciones;

use App\Autenticacion\Invitaciones;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El correo de primer ingreso: el usuario ya existe y le falta la contraseña.
 */
class InvitacionAlSistema extends Notification
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
        return (new MailMessage)
            ->subject('Te invitaron a '.config('app.name'))
            ->markdown('correos.invitacion', [
                'nombre' => $notifiable->name,
                'empresa' => $notifiable->tenant?->nombre,
                'enlace' => route('invitacion.formulario', ['token' => $this->token]),
                'dias' => Invitaciones::DIAS_DE_VIGENCIA,
            ]);
    }
}
