<?php

use App\Auditoria\AccionAuditada;
use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Models\Auditoria;
use App\Models\Incapacidad;
use App\Models\Tenant;
use App\Models\User;
use App\Notificaciones\InvitacionAlSistema;
use Illuminate\Support\Facades\Notification;

/**
 * La pestaña «Usuarios y permisos» de Configuración, REV-10: la mitad que
 * F1-12 dejó sin construir. El documento 3, 5.7, la resume en una frase:
 * «Listado de usuarios con su rol y módulos. Al crear uno, se pide nombre y
 * correo, se marcan los módulos y se envía la invitación. Un usuario se
 * desactiva, no se borra.»
 *
 * Lo que aquí se defiende, en orden:
 *
 * 1. **Es del superadministrador.** Igual que Parámetros, un administrador
 *    recibe 403 en las cuatro acciones —ver, invitar, editar módulos,
 *    desactivar— porque las cuatro cuelgan del mismo módulo `Configuracion`.
 * 2. **Invitar crea, asigna módulos y manda la invitación de una sola vez**,
 *    reutilizando `Invitaciones`, el mismo mecanismo de F1B-02.
 * 3. **Editar módulos reemplaza el conjunto completo.**
 * 4. **Desactivar es borrado lógico y queda en la auditoría sola**, sin que
 *    nadie la escriba a mano: es la prueba central de la deuda de
 *    `SoftDeletes` que esta tarea paga en `User`, `Acceso` e `Invitacion`.
 * 5. **El superadministrador no se toca desde aquí**, ni para editar sus
 *    módulos —no tiene ninguno que asignar— ni para desactivarlo.
 * 6. **Aislamiento**: el enlace directo con el identificador de un usuario de
 *    otra empresa no funciona, aunque `User` no lleve `PerteneceATenant`.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `enLaEmpresa()` en
 * `PersonasYVinculacionesTest.php`: Pest carga las funciones de todos los
 * archivos de prueba.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create([
        'nombre' => 'Distribuidora El Progreso',
        'nit' => '900123456-7',
    ]);

    $this->superadmin = usuarioDe($this->empresa, Rol::Superadministrador);
});

function datosDelUsuarioNuevo(array $sobrescribe = []): array
{
    return [
        'nombre' => 'Camila Rojas',
        'correo' => 'camila@elprogreso.com',
        'modulos' => [Modulo::Vacaciones->value, Modulo::Contratos->value],
        ...$sobrescribe,
    ];
}

describe('quién entra', function () {
    it('deja ver el listado al superadministrador', function () {
        $this->actingAs($this->superadmin)
            ->get('/configuracion')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->component('Configuracion/Parametros')->etc());
    });

    it('responde 403 a un administrador que intenta invitar', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador, ...Modulo::asignables());

        $this->actingAs($administrador)
            ->post('/configuracion/usuarios', datosDelUsuarioNuevo())
            ->assertForbidden();

        expect(User::query()->where('email', 'camila@elprogreso.com')->exists())->toBeFalse();
    });

    it('responde 403 a un administrador que intenta editar módulos o desactivar', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador, ...Modulo::asignables());
        $otro = usuarioDe($this->empresa, Rol::Administrador);

        $this->actingAs($administrador)
            ->put("/configuracion/usuarios/{$otro->id}", ['modulos' => [Modulo::Vacaciones->value]])
            ->assertForbidden();

        $this->actingAs($administrador)
            ->delete("/configuracion/usuarios/{$otro->id}")
            ->assertForbidden();

        expect($otro->fresh()->trashed())->toBeFalse();
    });
});

describe('el listado', function () {
    it('trae el rol y los módulos de cada usuario', function () {
        usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones, Modulo::Contratos);

        $props = $this->actingAs($this->superadmin)->get('/configuracion')->viewData('page')['props'];

        $filas = collect($props['usuarios'])->keyBy('correo');

        expect($filas[$this->superadmin->email]['rol'])->toBe('Superadministrador')
            ->and($filas[$this->superadmin->email]['esSuperadministrador'])->toBeTrue()
            ->and($filas[$this->superadmin->email]['modulos'])->toBe('Todos los módulos');
    });

    it('no trae los usuarios de otra empresa', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Otra empresa', 'nit' => '800999111-2']);
        $ajeno = usuarioDe($otra, Rol::Superadministrador);

        $props = $this->actingAs($this->superadmin)->get('/configuracion')->viewData('page')['props'];

        $correos = array_column($props['usuarios'], 'correo');

        expect($correos)->not->toContain($ajeno->email);
    });

    it('no trae a un usuario ya desactivado', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador);
        $administrador->delete();

        $props = $this->actingAs($this->superadmin)->get('/configuracion')->viewData('page')['props'];

        expect(array_column($props['usuarios'], 'correo'))->not->toContain($administrador->email);
    });

    it('ofrece los módulos asignables para marcar', function () {
        $props = $this->actingAs($this->superadmin)->get('/configuracion')->viewData('page')['props'];

        $valores = array_column($props['modulosAsignables'], 'valor');

        expect($valores)->toBe(Modulo::valoresAsignables());
    });
});

describe('invitar a un administrador', function () {
    it('crea el usuario, le asigna los módulos marcados y le manda la invitación', function () {
        Notification::fake();

        $this->actingAs($this->superadmin)
            ->post('/configuracion/usuarios', datosDelUsuarioNuevo())
            ->assertRedirect('/configuracion')
            ->assertSessionHas('estado', fn (string $mensaje) => str_contains($mensaje, 'Camila Rojas'));

        $usuario = User::query()->where('email', 'camila@elprogreso.com')->firstOrFail();

        expect($usuario->tenant_id)->toBe($this->empresa->id)
            ->and($usuario->rol)->toBe(Rol::Administrador)
            ->and($usuario->modulosAsignados()->map(fn ($modulo) => $modulo->value)->all())
            ->toBe([Modulo::Contratos->value, Modulo::Vacaciones->value]);

        Notification::assertSentTo($usuario, InvitacionAlSistema::class);
    });

    it('no deja a nadie con una contraseña que el superadministrador conozca', function () {
        Notification::fake();

        $this->actingAs($this->superadmin)->post('/configuracion/usuarios', datosDelUsuarioNuevo());

        $usuario = User::query()->where('email', 'camila@elprogreso.com')->firstOrFail();

        expect($usuario->getAttribute('password'))->not->toBeEmpty();
    });

    it('exige nombre y correo', function () {
        $this->actingAs($this->superadmin)
            ->post('/configuracion/usuarios', ['nombre' => '', 'correo' => ''])
            ->assertSessionHasErrors(['nombre', 'correo']);
    });

    it('no admite un correo repetido', function () {
        $this->actingAs($this->superadmin)
            ->post('/configuracion/usuarios', datosDelUsuarioNuevo(['correo' => $this->superadmin->email]))
            ->assertSessionHasErrors('correo');
    });

    it('no admite un módulo que no exista', function () {
        $this->actingAs($this->superadmin)
            ->post('/configuracion/usuarios', datosDelUsuarioNuevo(['modulos' => ['inventado']]))
            ->assertSessionHasErrors('modulos.0');
    });
});

describe('editar los módulos', function () {
    it('reemplaza el conjunto completo', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones, Modulo::Contratos);

        $this->actingAs($this->superadmin)
            ->put("/configuracion/usuarios/{$administrador->id}", ['modulos' => [Modulo::Incapacidades->value]])
            ->assertRedirect('/configuracion');

        $administrador->refresh();

        expect($administrador->modulosAsignados()->map(fn ($modulo) => $modulo->value)->all())
            ->toBe([Modulo::Incapacidades->value]);
    });

    it('puede dejarlo sin ningún módulo', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        $this->actingAs($this->superadmin)
            ->put("/configuracion/usuarios/{$administrador->id}", ['modulos' => []])
            ->assertRedirect('/configuracion');

        expect($administrador->modulosAsignados())->toHaveCount(0);
    });

    it('responde 403 si el usuario de la dirección es el superadministrador', function () {
        $this->actingAs($this->superadmin)
            ->put("/configuracion/usuarios/{$this->superadmin->id}", ['modulos' => [Modulo::Vacaciones->value]])
            ->assertForbidden();
    });

    it('responde 403 si el usuario es de otra empresa', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Otra empresa', 'nit' => '800999111-2']);
        $ajeno = usuarioDe($otra, Rol::Administrador);

        $this->actingAs($this->superadmin)
            ->put("/configuracion/usuarios/{$ajeno->id}", ['modulos' => [Modulo::Vacaciones->value]])
            ->assertForbidden();

        expect($ajeno->modulosAsignados())->toHaveCount(0);
    });
});

describe('desactivar', function () {
    it('deja al usuario sin poder iniciar sesión, sin borrar la fila', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador);
        $administrador->forceFill(['password' => 'clave-de-prueba'])->save();

        $this->actingAs($this->superadmin)
            ->delete("/configuracion/usuarios/{$administrador->id}")
            ->assertRedirect('/configuracion')
            ->assertSessionHas('estado', fn (string $mensaje) => str_contains($mensaje, $administrador->name));

        expect(User::withTrashed()->find($administrador->id))->not->toBeNull()
            ->and(User::find($administrador->id))->toBeNull();

        // El superadministrador que desactivó sigue con sesión abierta en
        // este cliente de pruebas: hay que salir antes de intentar entrar
        // como el usuario recién desactivado.
        $this->post('/salir');

        $this->post('/acceso', [
            'correo' => $administrador->email,
            'contrasena' => 'clave-de-prueba',
        ])->assertSessionHasErrors('correo');

        $this->assertGuest();
    });

    it('conserva las líneas de auditoría que el usuario escribió', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador, ...Modulo::asignables());

        $this->actingAs($administrador);
        enLaEmpresa(fn () => Incapacidad::factory()->create());

        $lineasAntes = Auditoria::query()->where('user_id', $administrador->id)->count();
        expect($lineasAntes)->toBeGreaterThan(0);

        $this->actingAs($this->superadmin)->delete("/configuracion/usuarios/{$administrador->id}");

        $lineasDespues = Auditoria::query()->where('user_id', $administrador->id)->get();

        expect($lineasDespues)->toHaveCount($lineasAntes)
            ->and($lineasDespues->first()->usuario?->id)->toBe($administrador->id);
    });

    it('deja su propia línea de eliminado en la auditoría', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador);

        $this->actingAs($this->superadmin)->delete("/configuracion/usuarios/{$administrador->id}");

        $linea = Auditoria::query()
            ->where('modelo', User::class)
            ->where('registro_id', (string) $administrador->id)
            ->where('accion', AccionAuditada::Eliminado->value)
            ->first();

        expect($linea)->not->toBeNull()
            ->and($linea->tenant_id)->toBe($this->empresa->id);
    });

    it('responde 403 al intentar desactivar al superadministrador', function () {
        $this->actingAs($this->superadmin)
            ->delete("/configuracion/usuarios/{$this->superadmin->id}")
            ->assertForbidden();

        expect($this->superadmin->fresh()->trashed())->toBeFalse();
    });

    it('responde 403 si el usuario es de otra empresa', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Otra empresa', 'nit' => '800999111-2']);
        $ajeno = usuarioDe($otra, Rol::Administrador);

        $this->actingAs($this->superadmin)
            ->delete("/configuracion/usuarios/{$ajeno->id}")
            ->assertForbidden();

        expect($ajeno->fresh()->trashed())->toBeFalse();
    });

    it('explica la consecuencia antes de pedir confirmar', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador);

        $props = $this->actingAs($this->superadmin)->get('/configuracion')->viewData('page')['props'];

        $fila = collect($props['usuarios'])->firstWhere('correo', $administrador->email);

        expect($fila['consecuenciaDeDesactivar'])
            ->toContain('Vas a desactivar a '.$administrador->name)
            ->toContain('registrada en la auditoría');
    });
});
