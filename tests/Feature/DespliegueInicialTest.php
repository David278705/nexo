<?php

use App\Models\User;
use App\Notificaciones\InvitacionAlSistema;
use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Lo que necesita el primer despliegue en Railway: la lista de correos
 * permitidos mientras se prueba (`PROD-29`) y las cuentas iniciales del
 * equipo (`PROD-30`).
 */
describe('los destinatarios permitidos (PROD-29)', function () {
    function enviarA(array $destinatarios): ?SentMessage
    {
        return Mail::raw('Prueba', function (Message $mensaje) use ($destinatarios) {
            $mensaje->to($destinatarios)->subject('Prueba');
        });
    }

    it('sin lista, el correo sale a quien sea', function () {
        config(['mail.default' => 'array', 'mail.destinatarios_permitidos' => '']);

        expect(enviarA(['cualquiera@ejemplo.co']))->not->toBeNull();
    });

    it('con lista, deja solo los permitidos', function () {
        config(['mail.default' => 'array', 'mail.destinatarios_permitidos' => 'david@nexo.co, ANGELA@nexo.co']);

        $enviado = enviarA(['david@nexo.co', 'cliente@ejemplo.co', 'angela@nexo.co']);

        $para = array_map(fn ($d) => $d->getAddress(), $enviado->getOriginalMessage()->getTo());
        expect($para)->toBe(['david@nexo.co', 'angela@nexo.co']);
    });

    it('con lista, no envía nada si ningún destinatario está permitido', function () {
        config(['mail.default' => 'array', 'mail.destinatarios_permitidos' => 'david@nexo.co']);

        expect(enviarA(['cliente@ejemplo.co']))->toBeNull();
    });
});

describe('el equipo inicial (PROD-30)', function () {
    it('invita a las personas de la lista que no tienen cuenta', function () {
        Notification::fake();
        config(['mail.default' => 'smtp', 'panel.equipo_inicial' => 'david@nexo.co|David Lozano;angela@nexo.co|Angela Lozano']);

        $this->artisan('equipo:asegurar-inicial')->assertSuccessful();

        $david = User::query()->where('email', 'david@nexo.co')->sole();
        expect($david->esOwner())->toBeTrue()
            ->and($david->name)->toBe('David Lozano')
            ->and(User::query()->where('email', 'angela@nexo.co')->exists())->toBeTrue();
        Notification::assertSentTo($david, InvitacionAlSistema::class);
    });

    it('no vuelve a invitar en el siguiente arranque', function () {
        Notification::fake();
        config(['mail.default' => 'smtp', 'panel.equipo_inicial' => 'david@nexo.co|David Lozano']);

        $this->artisan('equipo:asegurar-inicial');
        $this->artisan('equipo:asegurar-inicial')->expectsOutputToContain('ya tiene cuenta');

        Notification::assertSentTimes(InvitacionAlSistema::class, 1);
        expect(User::query()->where('email', 'david@nexo.co')->count())->toBe(1);
    });

    it('sin lista no hace nada', function () {
        config(['panel.equipo_inicial' => '']);

        $this->artisan('equipo:asegurar-inicial')->assertSuccessful();

        expect(User::query()->count())->toBe(0);
    });
});
