<?php

use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use App\Multiempresa\SinContextoDeTenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\RegistroDePrueba;

beforeEach(function () {
    Schema::create('registros_de_prueba', function (Blueprint $tabla) {
        $tabla->id();
        $tabla->foreignId('tenant_id')->constrained('tenants');
        $tabla->string('titulo');
        $tabla->timestamps();
    });

    $this->contexto = app(ContextoTenant::class);
    $this->empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
    $this->empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);
});

it('rellena tenant_id al crear sin que nadie lo digite', function () {
    $registro = $this->contexto->paraTenant(
        $this->empresaA,
        fn () => RegistroDePrueba::create(['titulo' => 'Un registro'])
    );

    expect($registro->tenant_id)->toBe($this->empresaA->id);
});

it('pone el filtro de empresa en la consulta sin que el código lo escriba', function () {
    $sql = $this->contexto->paraTenant(
        $this->empresaA,
        fn () => RegistroDePrueba::query()->toSql()
    );

    expect($sql)->toContain('tenant_id');
});

it('no devuelve los registros de otra empresa', function () {
    $this->contexto->paraTenant(
        $this->empresaA,
        fn () => RegistroDePrueba::create(['titulo' => 'De la A'])
    );

    $vistosDesdeB = $this->contexto->paraTenant(
        $this->empresaB,
        fn () => RegistroDePrueba::all()
    );

    expect($vistosDesdeB)->toHaveCount(0);
});

it('no encuentra por clave primaria un registro de otra empresa', function () {
    $registro = $this->contexto->paraTenant(
        $this->empresaA,
        fn () => RegistroDePrueba::create(['titulo' => 'De la A'])
    );

    $encontrado = $this->contexto->paraTenant(
        $this->empresaB,
        fn () => RegistroDePrueba::find($registro->id)
    );

    expect($encontrado)->toBeNull();
});

it('no devuelve nada cuando no hay empresa en contexto', function () {
    $this->contexto->paraTenant(
        $this->empresaA,
        fn () => RegistroDePrueba::create(['titulo' => 'De la A'])
    );

    $sinContexto = $this->contexto->sinTenant(
        fn () => RegistroDePrueba::all()
    );

    expect($sinContexto)->toHaveCount(0);
});

it('no deja crear un registro sin empresa en contexto', function () {
    $this->contexto->sinTenant(
        fn () => RegistroDePrueba::create(['titulo' => 'Huérfano'])
    );
})->throws(SinContextoDeTenant::class);

it('ignora un tenant_id puesto a mano y usa el del contexto', function () {
    $registro = $this->contexto->paraTenant($this->empresaA, function () {
        $registro = new RegistroDePrueba(['titulo' => 'Intento de colarse']);
        $registro->tenant_id = $this->empresaB->id;
        $registro->save();

        return $registro;
    });

    expect($registro->fresh()->tenant_id)->toBe($this->empresaA->id);
});

it('restaura el contexto anterior aunque el trabajo falle', function () {
    $this->contexto->fijar($this->empresaA);

    try {
        $this->contexto->paraTenant($this->empresaB, function () {
            throw new RuntimeException('el trabajo reventó');
        });
    } catch (RuntimeException) {
        // Justamente lo que se quiere comprobar es lo que pasa después.
    }

    expect($this->contexto->id())->toBe($this->empresaA->id);
});

it('resuelve la empresa desde el usuario autenticado', function () {
    Route::middleware('web')->get('/prueba-de-tenant', fn () => response()->json([
        'nombre' => tenant_actual()?->nombre,
    ]));

    $usuario = User::factory()->create(['tenant_id' => $this->empresaB->id]);

    $this->actingAs($usuario)
        ->get('/prueba-de-tenant')
        ->assertOk()
        ->assertJson(['nombre' => 'Empresa B']);
});

it('deja el contexto vacío si el usuario no pertenece a ninguna empresa', function () {
    Route::middleware('web')->get('/prueba-de-tenant', fn () => response()->json([
        'nombre' => tenant_actual()?->nombre,
    ]));

    $usuario = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($usuario)
        ->get('/prueba-de-tenant')
        ->assertOk()
        ->assertJson(['nombre' => null]);
});

it('no fija ninguna empresa para una visita sin autenticar', function () {
    Route::middleware('web')->get('/prueba-de-tenant', fn () => response()->json([
        'nombre' => tenant_actual()?->nombre,
    ]));

    $this->get('/prueba-de-tenant')
        ->assertOk()
        ->assertJson(['nombre' => null]);
});
