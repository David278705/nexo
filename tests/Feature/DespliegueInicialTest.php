<?php

use App\Models\Tenant;
use App\Models\User;
use App\Notificaciones\InvitacionAlSistema;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DesarrolloSeeder;
use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Hash;
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

describe('las cuentas de prueba en un servidor (PROD-31)', function () {
    beforeEach(function () {
        $this->seed(CatalogosSeeder::class);
    });

    it('fuera de local no corre sin una clave propia', function () {
        app()->detectEnvironment(fn () => 'production');
        config(['panel.clave_cuentas_de_prueba' => '']);

        expect(fn () => app(DesarrolloSeeder::class)->run())
            ->toThrow(RuntimeException::class, 'NEXO_CLAVE_CUENTAS_DE_PRUEBA');
    });

    it('con clave propia crea las dos cuentas con esa clave, no con password', function () {
        app()->detectEnvironment(fn () => 'production');
        config(['panel.clave_cuentas_de_prueba' => 'una-clave-larga-y-propia']);

        app(DesarrolloSeeder::class)->run();

        foreach (['owner@nexo.local', 'admin@empresademo.local'] as $correo) {
            $usuario = User::query()->where('email', $correo)->sole();
            expect(Hash::check('una-clave-larga-y-propia', $usuario->password))->toBeTrue()
                ->and(Hash::check('password', $usuario->password))->toBeFalse();
        }
    });

    it('volver a correrlo no duplica nada', function () {
        app()->detectEnvironment(fn () => 'production');
        config(['panel.clave_cuentas_de_prueba' => 'una-clave-larga-y-propia']);

        app(DesarrolloSeeder::class)->run();
        app(DesarrolloSeeder::class)->run();

        expect(User::query()->where('email', 'owner@nexo.local')->count())->toBe(1);
    });
});

describe('retirar la empresa de pruebas (PROD-32)', function () {
    beforeEach(function () {
        $this->seed(CatalogosSeeder::class);
        app()->detectEnvironment(fn () => 'production');
        config(['panel.clave_cuentas_de_prueba' => 'una-clave-larga-y-propia']);
        app(DesarrolloSeeder::class)->run();
    });

    it('le hace borrado lógico a la empresa y a su usuario, y deja al proveedor', function () {
        $this->artisan('cuentas-de-prueba:retirar-empresa')->assertSuccessful();

        $empresa = Tenant::withTrashed()->where('nit', DesarrolloSeeder::NIT_EMPRESA)->sole();
        $admin = User::withTrashed()->where('email', DesarrolloSeeder::CORREO_EMPRESA)->sole();

        expect($empresa->trashed())->toBeTrue()
            ->and($admin->trashed())->toBeTrue()
            ->and(User::query()->where('email', DesarrolloSeeder::CORREO_PROVEEDOR)->exists())->toBeTrue();
    });

    it('la siembra no revive la empresa retirada', function () {
        $this->artisan('cuentas-de-prueba:retirar-empresa')->assertSuccessful();

        app(DesarrolloSeeder::class)->run();

        expect(Tenant::query()->where('nit', DesarrolloSeeder::NIT_EMPRESA)->exists())->toBeFalse();
    });

    it('correrlo otra vez no hace nada', function () {
        $this->artisan('cuentas-de-prueba:retirar-empresa')->assertSuccessful();
        $this->artisan('cuentas-de-prueba:retirar-empresa')
            ->expectsOutputToContain('no se retira nada')
            ->assertSuccessful();
    });

    it('no toca otras empresas', function () {
        $cliente = Tenant::factory()->create();

        $this->artisan('cuentas-de-prueba:retirar-empresa')->assertSuccessful();

        expect($cliente->fresh()->trashed())->toBeFalse();
    });
});
