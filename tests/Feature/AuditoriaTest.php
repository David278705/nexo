<?php

use App\Auditoria\AccionAuditada;
use App\Auditoria\AuditoriaInmutable;
use App\Models\Acceso;
use App\Models\Auditoria;
use App\Models\EjecucionDeAlertas;
use App\Models\Eps;
use App\Models\ErrorDeAplicacion;
use App\Models\Incapacidad;
use App\Models\Invitacion;
use App\Models\MetricaMensual;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\RegistroAuditable;

/**
 * La auditoría.
 *
 * Lo que se prueba aquí no es que exista una tabla: es que **nadie tenga que
 * acordarse de escribirla**. Ninguna prueba de este archivo llama a
 * `Auditoria::create()`. Si una línea aparece, la escribió el observador.
 */
beforeEach(function () {
    Schema::create('registros_auditables', function (Blueprint $tabla) {
        $tabla->id();
        $tabla->foreignId('tenant_id')->constrained('tenants');
        $tabla->string('titulo');
        $tabla->integer('salario')->default(0);
        $tabla->timestamps();
        $tabla->softDeletes();

        $tabla->index(['tenant_id', 'titulo']);
    });

    $this->empresa = Tenant::factory()->create(['nombre' => 'Empresa A']);
    app(ContextoTenant::class)->fijar($this->empresa);
});

/**
 * Las líneas del modelo de prueba, en orden. Se filtra por modelo porque crear
 * la empresa y el usuario de la escena también deja las suyas, que es
 * exactamente lo que se espera de un observador que no se puede olvidar.
 *
 * @return Collection<int, Auditoria>
 */
function lineasDelRegistro(): Collection
{
    return Auditoria::query()
        ->where('modelo', RegistroAuditable::class)
        ->orderBy('id')
        ->get();
}

it('deja una línea al crear un registro sin que nadie la escriba a mano', function () {
    $registro = RegistroAuditable::create(['titulo' => 'Contrato de Ana', 'salario' => 1_500_000]);

    $lineas = lineasDelRegistro();

    expect($lineas)->toHaveCount(1);

    $linea = $lineas->first();

    expect($linea->accion)->toBe(AccionAuditada::Creado)
        ->and($linea->registro_id)->toBe((string) $registro->id)
        ->and($linea->tenant_id)->toBe($this->empresa->id)
        ->and($linea->valores_anteriores)->toBeNull()
        ->and($linea->valores_nuevos['titulo'])->toBe('Contrato de Ana')
        ->and((int) $linea->valores_nuevos['salario'])->toBe(1_500_000);
});

it('guarda el valor anterior y el nuevo, solo de lo que cambió', function () {
    $registro = RegistroAuditable::create(['titulo' => 'Contrato de Ana', 'salario' => 1_500_000]);

    $registro->update(['salario' => 2_000_000]);

    $linea = lineasDelRegistro()->last();

    expect($linea->accion)->toBe(AccionAuditada::Editado)
        ->and(array_keys($linea->valores_nuevos))->toBe(['salario'])
        ->and((int) $linea->valores_anteriores['salario'])->toBe(1_500_000)
        ->and((int) $linea->valores_nuevos['salario'])->toBe(2_000_000)
        // El título no cambió, así que no tiene por qué figurar en la edición.
        ->and($linea->valores_anteriores)->not->toHaveKey('titulo');
});

it('deja línea al eliminar, con la fila entera como valor anterior', function () {
    $registro = RegistroAuditable::create(['titulo' => 'Contrato de Ana', 'salario' => 1_500_000]);

    $registro->delete();

    $linea = lineasDelRegistro()->last();

    expect($linea->accion)->toBe(AccionAuditada::Eliminado)
        ->and($linea->valores_anteriores['titulo'])->toBe('Contrato de Ana')
        ->and($linea->valores_nuevos)->toBeNull();
});

it('deja una sola línea al restaurar, y es de restauración', function () {
    $registro = RegistroAuditable::create(['titulo' => 'Contrato de Ana']);
    $registro->delete();

    $registro->restore();

    $acciones = lineasDelRegistro()->map(fn (Auditoria $linea) => $linea->accion->value);

    // Restaurar es un `save()` por dentro, así que sin cuidado dejaría dos
    // líneas: una edición de `deleted_at` y la restauración. Solo interesa la
    // segunda.
    expect($acciones->all())->toBe(['creado', 'eliminado', 'restaurado']);
});

it('no deja línea cuando la edición no cambió nada que importe', function () {
    $registro = RegistroAuditable::create(['titulo' => 'Contrato de Ana']);

    $registro->touch();

    expect(lineasDelRegistro())->toHaveCount(1);
});

it('guarda quién hizo el cambio', function () {
    $usuario = User::factory()->create(['tenant_id' => $this->empresa->id]);

    $this->actingAs($usuario);

    RegistroAuditable::create(['titulo' => 'Contrato de Ana']);

    expect(lineasDelRegistro()->first()->user_id)->toBe($usuario->id);
});

it('nunca copia la contraseña a la auditoría', function () {
    $usuario = User::factory()->create(['tenant_id' => $this->empresa->id]);

    $usuario->update(['password' => 'una-contrasena-larga-y-nueva']);

    $lineas = Auditoria::query()
        ->where('modelo', User::class)
        ->where('registro_id', (string) $usuario->id)
        ->get();

    $edicion = $lineas->last();

    // La línea dice que la contraseña cambió, pero no cuál es.
    expect($edicion->accion)->toBe(AccionAuditada::Editado)
        ->and($edicion->valores_nuevos['password'])->toBe('(oculto)')
        ->and($edicion->valores_anteriores['password'])->toBe('(oculto)');

    // Y ninguna otra línea la dejó escapar por otro camino: ni la creación del
    // usuario, que copia la fila entera.
    $todas = DB::table('auditoria')->pluck('valores_nuevos')->implode(' ')
        .DB::table('auditoria')->pluck('valores_anteriores')->implode(' ');

    // `$2y$` es el prefijo de todo hash de bcrypt. No hay ninguno en la tabla.
    expect($todas)->not->toContain('$2y$');
});

it('nunca copia el código de diagnóstico de una incapacidad a la auditoría', function () {
    // Es el dato sensible de la sección 6.3: solo lo ve quien tiene el
    // permiso del módulo de incapacidades, y la auditoría es de otro módulo.
    $vinculacion = Vinculacion::factory()->create();

    $incapacidad = Incapacidad::create([
        'vinculacion_id' => $vinculacion->id,
        'fecha_inicio' => '2026-07-14',
        'fecha_fin' => '2026-07-16',
        'codigo_diagnostico' => 'J06.9',
        'es_prorroga' => false,
        'eps_id' => Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true])->id,
        'origen' => 'comun',
    ]);

    $creacion = Auditoria::query()
        ->where('modelo', Incapacidad::class)
        ->where('registro_id', (string) $incapacidad->id)
        ->firstOrFail();

    expect($creacion->valores_nuevos['codigo_diagnostico'])->toBe('(oculto)');

    $todas = DB::table('auditoria')->pluck('valores_nuevos')->implode(' ');

    expect($todas)->not->toContain('J06.9');
});

it('no audita el registro de accesos ni las invitaciones', function () {
    // Son registros de seguridad por derecho propio, no datos de negocio.
    // Auditarlos duplicaría cada inicio de sesión en dos tablas.
    $usuario = User::factory()->create(['tenant_id' => $this->empresa->id]);

    Acceso::create([
        'user_id' => $usuario->id,
        'tenant_id' => $this->empresa->id,
        'correo' => $usuario->email,
        'resultado' => 'exitoso',
    ]);

    Invitacion::create([
        'user_id' => $usuario->id,
        'token' => hash('sha256', 'lo-que-sea'),
        'expira_en' => now()->addDays(7),
    ]);

    expect(Auditoria::query()->whereIn('modelo', [Acceso::class, Invitacion::class])->count())->toBe(0);
});

it('no audita los errores de aplicación, las ejecuciones de alertas ni las métricas mensuales', function () {
    // Las tres son del sistema entero, no de una empresa: no tienen tenant_id
    // y las escribe un proceso en consola, no una persona. Sin la exclusión,
    // cada línea quedaría con tenant_id y user_id en blanco.
    ErrorDeAplicacion::factory()->create();
    EjecucionDeAlertas::factory()->create();
    MetricaMensual::factory()->create();

    expect(Auditoria::query()->whereIn('modelo', [
        ErrorDeAplicacion::class,
        EjecucionDeAlertas::class,
        MetricaMensual::class,
    ])->count())->toBe(0);
});

it('no permite editar una línea de auditoría', function () {
    RegistroAuditable::create(['titulo' => 'Contrato de Ana']);

    $linea = lineasDelRegistro()->first();

    expect(fn () => $linea->update(['valores_nuevos' => ['titulo' => 'Otra cosa']]))
        ->toThrow(AuditoriaInmutable::class);

    expect(lineasDelRegistro()->first()->valores_nuevos['titulo'])->toBe('Contrato de Ana');
});

it('no permite borrar una línea de auditoría', function () {
    RegistroAuditable::create(['titulo' => 'Contrato de Ana']);

    $linea = lineasDelRegistro()->first();

    expect(fn () => $linea->delete())->toThrow(AuditoriaInmutable::class);

    expect(Auditoria::query()->whereKey($linea->id)->exists())->toBeTrue();
});

it('muestra a cada empresa solo su propia auditoría', function () {
    $contexto = app(ContextoTenant::class);

    RegistroAuditable::create(['titulo' => 'Contrato de la A']);

    $empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

    $contexto->paraTenant($empresaB, function () {
        RegistroAuditable::create(['titulo' => 'Contrato de la B']);
    });

    $vistoPorLaB = $contexto->paraTenant($empresaB, fn () => Auditoria::query()
        ->deLaEmpresaActual()
        ->where('modelo', RegistroAuditable::class)
        ->get());

    expect($vistoPorLaB)->toHaveCount(1)
        ->and($vistoPorLaB->first()->valores_nuevos['titulo'])->toBe('Contrato de la B');
});

it('no muestra ninguna auditoría cuando no hay empresa en contexto', function () {
    RegistroAuditable::create(['titulo' => 'Contrato de la A']);

    $visto = app(ContextoTenant::class)->sinTenant(
        fn () => Auditoria::query()->deLaEmpresaActual()->count()
    );

    // Falla cerrado, igual que el global scope: sin empresa no se ve nada.
    expect($visto)->toBe(0);
});
