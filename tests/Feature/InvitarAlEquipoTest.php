<?php

use App\Autorizacion\Rol;
use App\Models\Invitacion;
use App\Models\Tenant;
use App\Models\User;
use App\Notificaciones\InvitacionAlSistema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

/**
 * `equipo:invitar`, la única forma de crear al primer `owner` en un servidor
 * recién desplegado.
 */
function invitarAlEquipo(string $correo, string $nombre = 'Persona del equipo'): array
{
    $codigo = Artisan::call('equipo:invitar', ['correo' => $correo, 'nombre' => $nombre]);

    return [$codigo, Artisan::output()];
}

function tokenDelEnlace(string $salida): string
{
    preg_match('#/invitacion/([A-Za-z0-9]+)#', $salida, $coincidencia);

    return $coincidencia[1] ?? '';
}

it('crea un owner sin empresa y el enlace que imprime lo deja en su panel', function () {
    [$codigo, $salida] = invitarAlEquipo('David@Nexo.co ', 'David');

    $owner = User::query()->where('email', 'david@nexo.co')->firstOrFail();

    expect($codigo)->toBe(0)
        ->and($owner->rol)->toBe(Rol::Owner)
        ->and($owner->tenant_id)->toBeNull();

    // El camino entero: el enlace impreso es el que abre la cuenta.
    $this->post('/invitacion/'.tokenDelEnlace($salida), [
        'contrasena' => 'Contrasena9nueva',
        'contrasena_confirmation' => 'Contrasena9nueva',
    ])->assertRedirect('/proveedor');

    $this->assertAuthenticatedAs($owner);
});

it('manda el correo cuando hay un transporte que de verdad envía', function () {
    Notification::fake();
    config(['mail.default' => 'smtp']);

    [$codigo, $salida] = invitarAlEquipo('angela@nexo.co');

    Notification::assertSentTo(User::query()->where('email', 'angela@nexo.co')->firstOrFail(), InvitacionAlSistema::class);

    expect($codigo)->toBe(0)
        ->and($salida)->toContain('Invitación enviada');
});

it('no dice que envió nada cuando el correo no sale de la máquina, y aun así da el enlace', function () {
    Notification::fake();
    config(['mail.default' => 'log']);

    [$codigo, $salida] = invitarAlEquipo('david@nexo.co');

    Notification::assertNothingSent();

    expect($codigo)->toBe(0)
        ->and($salida)->toContain('MAIL_MAILER=log')
        ->and($salida)->not->toContain('Invitación enviada')
        ->and(tokenDelEnlace($salida))->not->toBe('');
});

it('a un owner que ya existe le emite una invitación nueva y vence la anterior, sin duplicarlo', function () {
    invitarAlEquipo('david@nexo.co');
    invitarAlEquipo('david@nexo.co');

    $owner = User::query()->where('email', 'david@nexo.co')->firstOrFail();
    $invitaciones = Invitacion::query()->where('user_id', $owner->getKey())->get();

    expect(User::query()->where('email', 'david@nexo.co')->count())->toBe(1)
        ->and($invitaciones)->toHaveCount(2)
        ->and($invitaciones->filter(fn (Invitacion $i) => $i->expira_en->isFuture()))->toHaveCount(1);
});

it('no convierte en owner a un usuario de una empresa cliente', function () {
    $empresa = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $empresa->getKey(), 'email' => 'ana@empresa.co']);

    [$codigo, $salida] = invitarAlEquipo('ana@empresa.co');

    expect($codigo)->toBe(1)
        ->and($salida)->toContain('ya es usuario de una empresa cliente')
        ->and(User::query()->where('email', 'ana@empresa.co')->firstOrFail()->rol)->not->toBe(Rol::Owner);
});

it('rechaza un correo mal escrito sin crear nada', function () {
    [$codigo] = invitarAlEquipo('esto-no-es-un-correo');

    expect($codigo)->toBe(1)
        ->and(User::query()->where('rol', Rol::Owner)->count())->toBe(0);
});
