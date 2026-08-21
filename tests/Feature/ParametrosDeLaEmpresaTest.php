<?php

use App\Auditoria\AccionAuditada;
use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Configuracion\ParametrosDeLaEmpresa;
use App\Models\Auditoria;
use App\Models\Persona;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use Illuminate\Testing\TestResponse;

/**
 * Los parámetros de la empresa, F1-12: el capítulo 4 del documento 1 y la
 * sección 5.7 del documento 3.
 *
 * Lo que aquí se defiende, en orden de importancia:
 *
 * 1. **El criterio de terminado: cambiar un parámetro se refleja de inmediato en
 *    los cálculos.** No hay copia, no hay caché y no hay que volver a entrar. El
 *    único parámetro que hoy tiene un consumidor construido es el perfil
 *    sociodemográfico —el motor de vacaciones es la fase 2—, así que es con él
 *    con el que se prueba el efecto de punta a punta: se guarda «sí» y el
 *    asistente de registro cambia en la petición siguiente.
 * 2. **Es del superadministrador.** `Configuracion` es el único módulo que no se
 *    puede asignar a nadie, y es también la única pantalla del cliente que
 *    escribe en `tenants`. Un administrador con todos los demás módulos recibe
 *    403, tanto al mirar como al guardar.
 * 3. **Las explicaciones son del servidor.** Cada parámetro llega con el texto
 *    que promete qué cambia al activarlo. La prueba lee la promesa y el efecto.
 * 4. **El aviso del capítulo 4.** Al habilitar el perfil con la planta cargada,
 *    el aviso dice cuántos empleados quedarán en blanco y que eso no es un
 *    error.
 * 5. **Los correos que reciben las alertas se deducen, no se guardan.** Salen de
 *    los usuarios de la empresa y de sus módulos, y no de otra empresa.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `enLaEmpresa()` en
 * `PersonasYVinculacionesTest.php`: Pest carga las funciones de todos los
 * archivos de prueba.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create([
        'nombre' => 'Distribuidora El Progreso',
        'nit' => '900123456-7',
        'sabado_laboral' => false,
        'suspensiones_afectan_vacaciones' => false,
        'perfil_sociodemografico_habilitado' => false,
    ]);

    $this->usuario = usuarioDe($this->empresa, Rol::Superadministrador);
});

/**
 * Las props de la pantalla de parámetros.
 *
 * @return array<string, mixed>
 */
function pantallaDeParametros(): array
{
    $respuesta = test()->actingAs(test()->usuario)->get('/configuracion');

    $respuesta->assertOk();

    return $respuesta->viewData('page')['props'];
}

/**
 * Guarda los tres parámetros. Lo que no se diga queda en «no».
 *
 * @param  array<string, string>  $valores
 */
function guardarParametros(array $valores): TestResponse
{
    return test()->actingAs(test()->usuario)->put('/configuracion/parametros', [
        'sabado_laboral' => ParametrosDeLaEmpresa::NO,
        'suspensiones_afectan_vacaciones' => ParametrosDeLaEmpresa::NO,
        'perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::NO,
        ...$valores,
    ]);
}

/**
 * El parámetro con esa clave, tal como llega a la pantalla.
 *
 * @param  array<string, mixed>  $props
 * @return array<string, mixed>
 */
function parametro(array $props, string $clave): array
{
    /** @var list<array<string, mixed>> $parametros */
    $parametros = $props['parametros'];

    foreach ($parametros as $parametro) {
        if ($parametro['clave'] === $clave) {
            return $parametro;
        }
    }

    return [];
}

describe('quién entra', function () {
    it('deja entrar al superadministrador', function () {
        $this->actingAs($this->usuario)
            ->get('/configuracion')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->component('Configuracion/Parametros')->etc());
    });

    it('responde 403 a un administrador con todos los demás módulos', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador, ...Modulo::asignables());

        $this->actingAs($administrador)->get('/configuracion')->assertForbidden();
    });

    it('responde 403 al administrador que intenta guardar directamente', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador, ...Modulo::asignables());

        $this->actingAs($administrador)
            ->put('/configuracion/parametros', [
                'sabado_laboral' => ParametrosDeLaEmpresa::SI,
                'suspensiones_afectan_vacaciones' => ParametrosDeLaEmpresa::SI,
                'perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::SI,
            ])
            ->assertForbidden();

        expect($this->empresa->fresh()->sabado_laboral)->toBeFalse();
    });

    it('manda a iniciar sesión a quien no ha entrado', function () {
        $this->get('/configuracion')->assertRedirect('/acceso');
    });
});

describe('los cuatro parámetros en pantalla', function () {
    it('trae los tres parámetros de cálculo con su valor actual', function () {
        $this->empresa->forceFill(['sabado_laboral' => true])->save();

        $props = pantallaDeParametros();

        expect($props['parametros'])->toHaveCount(3)
            ->and(parametro($props, 'sabado_laboral')['valor'])->toBe(ParametrosDeLaEmpresa::SI)
            ->and(parametro($props, 'suspensiones_afectan_vacaciones')['valor'])
            ->toBe(ParametrosDeLaEmpresa::NO)
            ->and(parametro($props, 'perfil_sociodemografico_habilitado')['valor'])
            ->toBe(ParametrosDeLaEmpresa::NO);
    });

    it('explica en lenguaje claro qué cambia cada parámetro', function () {
        $props = pantallaDeParametros();

        expect(parametro($props, 'sabado_laboral')['titulo'])
            ->toBe('¿El sábado es día laboral?')
            ->and(parametro($props, 'sabado_laboral')['explicacion'])
            ->toContain('Si el sábado es laboral, un festivo que caiga en sábado sí se descuenta')
            ->and(parametro($props, 'suspensiones_afectan_vacaciones')['explicacion'])
            ->toContain('1,25 días que se acumulan cada mes')
            ->and(parametro($props, 'perfil_sociodemografico_habilitado')['explicacion'])
            ->toContain('Seguridad y Salud en el Trabajo');
    });

    it('ofrece dos opciones dichas con palabras y no un sí y un no pelados', function () {
        $opciones = parametro(pantallaDeParametros(), 'suspensiones_afectan_vacaciones')['opciones'];

        expect($opciones)->toBe([
            ['valor' => ParametrosDeLaEmpresa::SI, 'texto' => 'Sí, reducen la acumulación'],
            ['valor' => ParametrosDeLaEmpresa::NO, 'texto' => 'No, no la afectan'],
        ]);
    });

    it('enseña los datos de la empresa con la nota de que los cambia el proveedor', function () {
        $props = pantallaDeParametros();

        expect($props['empresa']['nombre'])->toBe('Distribuidora El Progreso')
            ->and($props['empresa']['nombre'])->toBe($this->empresa->nombre)
            ->and($props['empresa']['nit'])->toBe('900123456-7')
            ->and($props['empresa']['nota'])->toContain('los cambia el proveedor');
    });
});

describe('guardar', function () {
    it('guarda los tres parámetros y lo confirma', function () {
        guardarParametros([
            'sabado_laboral' => ParametrosDeLaEmpresa::SI,
            'suspensiones_afectan_vacaciones' => ParametrosDeLaEmpresa::SI,
            'perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::SI,
        ])
            ->assertRedirect('/configuracion')
            ->assertSessionHas('estado', fn (string $mensaje) => str_contains($mensaje, 'parámetros'));

        $empresa = $this->empresa->fresh();

        expect($empresa->sabado_laboral)->toBeTrue()
            ->and($empresa->suspensiones_afectan_vacaciones)->toBeTrue()
            ->and($empresa->perfil_sociodemografico_habilitado)->toBeTrue();
    });

    it('apaga el que se deja en no', function () {
        $this->empresa->forceFill([
            'sabado_laboral' => true,
            'suspensiones_afectan_vacaciones' => true,
        ])->save();

        guardarParametros(['sabado_laboral' => ParametrosDeLaEmpresa::SI])->assertRedirect('/configuracion');

        $empresa = $this->empresa->fresh();

        expect($empresa->sabado_laboral)->toBeTrue()
            ->and($empresa->suspensiones_afectan_vacaciones)->toBeFalse();
    });

    it('no admite un valor que no sea sí o no', function () {
        test()->actingAs($this->usuario)
            ->put('/configuracion/parametros', [
                'sabado_laboral' => 'quizá',
                'suspensiones_afectan_vacaciones' => ParametrosDeLaEmpresa::NO,
                'perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::NO,
            ])
            ->assertSessionHasErrors('sabado_laboral');

        expect($this->empresa->fresh()->sabado_laboral)->toBeFalse();
    });

    it('exige los tres parámetros', function () {
        test()->actingAs($this->usuario)
            ->put('/configuracion/parametros', [])
            ->assertSessionHasErrors(ParametrosDeLaEmpresa::CAMPOS);
    });

    it('deja una sola línea de auditoría con lo que cambió', function () {
        guardarParametros(['sabado_laboral' => ParametrosDeLaEmpresa::SI]);

        $lineas = enLaEmpresa(fn () => Auditoria::query()
            ->where('modelo', Tenant::class)
            ->where('accion', AccionAuditada::Editado->value)
            ->get());

        expect($lineas)->toHaveCount(1)
            ->and($lineas->first()->tenant_id)->toBe($this->empresa->id)
            ->and($lineas->first()->valores_nuevos)->toHaveKey('sabado_laboral')
            ->and($lineas->first()->valores_nuevos['sabado_laboral'])->toBeTruthy();
    });
});

describe('el efecto inmediato en los cálculos', function () {
    it('habilita el bloque sociodemográfico del asistente en la petición siguiente', function () {
        $this->actingAs($this->usuario)
            ->get('/empleados/nuevo')
            ->assertInertia(fn ($pagina) => $pagina->where('perfilSociodemografico', false)->etc());

        guardarParametros(['perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::SI]);

        $this->actingAs($this->usuario)
            ->get('/empleados/nuevo')
            ->assertInertia(fn ($pagina) => $pagina->where('perfilSociodemografico', true)->etc());
    });

    it('vuelve a ocultarlo al apagarlo, sin romper nada de lo ya registrado', function () {
        $this->empresa->forceFill(['perfil_sociodemografico_habilitado' => true])->save();

        enLaEmpresa(fn () => Persona::factory()->create());

        guardarParametros(['perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::NO]);

        $this->actingAs($this->usuario)
            ->get('/empleados/nuevo')
            ->assertInertia(fn ($pagina) => $pagina->where('perfilSociodemografico', false)->etc());

        expect(enLaEmpresa(fn () => Persona::count()))->toBe(1);
    });

    it('la pantalla enseña el valor nuevo en cuanto se guarda', function () {
        guardarParametros(['sabado_laboral' => ParametrosDeLaEmpresa::SI]);

        expect(parametro(pantallaDeParametros(), 'sabado_laboral')['valor'])
            ->toBe(ParametrosDeLaEmpresa::SI);
    });
});

describe('el aviso de activar el perfil sociodemográfico', function () {
    it('dice cuántos empleados quedarán en blanco y que eso no es un error', function () {
        enLaEmpresa(fn () => Persona::factory()->count(3)->create());

        $props = pantallaDeParametros();

        expect($props['perfil']['habilitado'])->toBeFalse()
            ->and($props['perfil']['aviso'])
            ->toBe('Los 3 empleados ya registrados quedarán con estos campos vacíos. '
                .'Puedes completarlos cuando quieras; si no lo haces, no pasa nada.');
    });

    it('habla en singular con un solo empleado registrado', function () {
        enLaEmpresa(fn () => Persona::factory()->create());

        expect(pantallaDeParametros()['perfil']['aviso'])
            ->toStartWith('El empleado ya registrado quedará');
    });

    it('no advierte de nada en una empresa sin empleados', function () {
        expect(pantallaDeParametros()['perfil']['aviso'])->toBeNull();
    });

    it('no cuenta los empleados de otra empresa', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Otra empresa', 'nit' => '800999111-2']);

        app(ContextoTenant::class)
            ->paraTenant($otra, fn () => Persona::factory()->count(5)->create());

        expect(pantallaDeParametros()['perfil']['aviso'])->toBeNull();
    });
});

describe('los correos que reciben las alertas', function () {
    it('nombra al superadministrador con todos los módulos', function () {
        $filas = pantallaDeParametros()['destinatarios']['filas'];

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['etiqueta'])->toBe($this->usuario->email)
            ->and($filas[0]['valor'])->toBe('Todos los módulos');
    });

    it('nombra los módulos de cada administrador', function () {
        $administrador = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones, Modulo::Contratos);

        $filas = collect(pantallaDeParametros()['destinatarios']['filas'])
            ->keyBy('etiqueta');

        expect($filas[$administrador->email]['valor'])->toBe('Contratos · Vacaciones');
    });

    it('dice que un administrador sin módulos no recibe ninguna', function () {
        $sinModulos = usuarioDe($this->empresa, Rol::Administrador);

        $filas = collect(pantallaDeParametros()['destinatarios']['filas'])
            ->keyBy('etiqueta');

        expect($filas[$sinModulos->email]['valor'])->toBe('Sin módulos asignados');
    });

    it('explica que se cambian dando o quitando módulos', function () {
        expect(pantallaDeParametros()['destinatarios']['explicacion'])
            ->toContain('Usuarios y permisos');
    });

    it('no nombra a los usuarios de otra empresa', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Otra empresa', 'nit' => '800999111-2']);
        $ajeno = usuarioDe($otra, Rol::Superadministrador);

        $correos = array_column(pantallaDeParametros()['destinatarios']['filas'], 'etiqueta');

        expect($correos)->not->toContain($ajeno->email);
    });
});

describe('aislamiento entre empresas', function () {
    it('guardar en una empresa no toca los parámetros de la otra', function () {
        $otra = Tenant::factory()->create([
            'nombre' => 'Otra empresa',
            'nit' => '800999111-2',
            'sabado_laboral' => false,
        ]);
        $ajeno = usuarioDe($otra, Rol::Superadministrador);

        $this->actingAs($ajeno)->put('/configuracion/parametros', [
            'sabado_laboral' => ParametrosDeLaEmpresa::SI,
            'suspensiones_afectan_vacaciones' => ParametrosDeLaEmpresa::SI,
            'perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::SI,
        ])->assertRedirect('/configuracion');

        expect($otra->fresh()->sabado_laboral)->toBeTrue()
            ->and($this->empresa->fresh()->sabado_laboral)->toBeFalse();
    });
});
