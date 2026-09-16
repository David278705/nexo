<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EstadoVinculacion;
use App\Contratacion\Genero;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoDocumento;
use App\Contratacion\TipoRiesgo;
use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Ciudad;
use App\Models\Departamento;
use App\Models\Eps;
use App\Models\FondoPension;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Testing\TestResponse;

/**
 * El registro de empleado, F1-04: el asistente de cuatro pasos de las secciones
 * 5.1 a 5.3 del documento 1.
 *
 * Lo que se defiende aquí, en orden de importancia:
 *
 * 1. **El estado queda activo sin que nadie lo digite** (regla 5.3). Es el
 *    «hecho cuando» de la tarea, y la prueba lo comprueba de dos maneras:
 *    mirando el estado que quedó y comprobando que mandar `estado` en el
 *    formulario no cambia nada.
 * 2. **Persona y vinculación son entidades separadas** (regla 5 del proyecto).
 *    Registrar dos veces el mismo documento deja una persona y dos
 *    vinculaciones, nunca dos personas.
 * 3. **La regla 5.1.1**: en Colombia se guarda el identificador del catálogo y
 *    los textos del exterior quedan vacíos; fuera de Colombia, al revés.
 * 4. **La fecha de finalización la manda la modalidad**, no el usuario
 *    (documento 1, 5.4).
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`: Pest carga las funciones de
 * todos los archivos de prueba.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

    $this->catalogos = catalogosDelFormulario();
});

/**
 * Las filas de catálogo mínimas que el formulario exige.
 *
 * No se corre `CatalogosSeeder`: traer los 1.101 municipios y los 232 países en
 * cada caso costaría más que todo lo que se está probando. Lo que hace falta es
 * que existan y estén activos, no que sean los de verdad.
 *
 * @return array<string, mixed>
 */
function catalogosDelFormulario(): array
{
    Pais::query()->firstOrCreate(['codigo' => Pais::COLOMBIA], ['nombre' => 'Colombia', 'activo' => true]);
    Pais::query()->firstOrCreate(['codigo' => 'VE'], ['nombre' => 'Venezuela', 'activo' => true]);

    $antioquia = Departamento::factory()->create(['nombre' => 'Antioquia']);
    $atlantico = Departamento::factory()->create(['nombre' => 'Atlántico']);

    return [
        'antioquia' => $antioquia,
        'atlantico' => $atlantico,
        'medellin' => Ciudad::factory()->de($antioquia)->create(['nombre' => 'Medellín']),
        'envigado' => Ciudad::factory()->de($antioquia)->create(['nombre' => 'Envigado']),
        'barranquilla' => Ciudad::factory()->de($atlantico)->create(['nombre' => 'Barranquilla']),
        'eps' => Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true]),
        'fondo' => FondoPension::query()->create(['nombre' => 'Porvenir', 'activo' => true]),
        'arl' => Arl::query()->create(['nombre' => 'ARL Sura', 'activo' => true]),
        'caja' => CajaCompensacion::query()->create(['nombre' => 'Comfama', 'activo' => true]),
        'banco' => Banco::query()->create(['nombre' => 'Bancolombia', 'activo' => true]),
    ];
}

/**
 * Un formulario completo y válido, al que cada caso le cambia lo que va a probar.
 *
 * @return array<string, mixed>
 */
function formularioDeEmpleado(): array
{
    $catalogos = test()->catalogos;

    return [
        'tipo_documento' => TipoDocumento::CedulaCiudadania->value,
        'numero_documento' => '1017245880',
        'fecha_expedicion_documento' => '2010-06-14',
        'primer_nombre' => 'Carolina',
        'segundo_nombre' => 'Isabel',
        'primer_apellido' => 'Restrepo',
        'segundo_apellido' => 'Vélez',
        'fecha_nacimiento' => '1992-03-08',
        'genero' => Genero::Femenino->value,

        'nacimiento' => [
            'pais' => Pais::COLOMBIA,
            'departamento' => (string) $catalogos['antioquia']->getKey(),
            'ciudad' => (string) $catalogos['medellin']->getKey(),
        ],
        'expedicion' => [
            'pais' => Pais::COLOMBIA,
            'departamento' => (string) $catalogos['antioquia']->getKey(),
            'ciudad' => (string) $catalogos['medellin']->getKey(),
        ],

        'direccion' => 'Carrera 43A 18-95, apto 402',
        'residencia' => [
            'pais' => Pais::COLOMBIA,
            'departamento' => (string) $catalogos['antioquia']->getKey(),
            'ciudad' => (string) $catalogos['envigado']->getKey(),
        ],
        'celular' => '3007654321',
        'correo' => 'carolina.restrepo@example.com',

        'eps_id' => $catalogos['eps']->getKey(),
        'fondo_pension_id' => $catalogos['fondo']->getKey(),
        'arl_id' => $catalogos['arl']->getKey(),
        'tipo_riesgo' => TipoRiesgo::Uno->value,
        'caja_compensacion_id' => $catalogos['caja']->getKey(),
        'banco_id' => $catalogos['banco']->getKey(),

        'cargo' => 'Coordinadora de Operaciones',
        'tipo_contrato' => TipoContrato::Indefinido->value,
        'fecha_ingreso' => '2026-02-02',
        'salario' => '4200000',
        'bonificacion' => '0',
    ];
}

/**
 * Envía el formulario como el usuario de la empresa de la prueba.
 *
 * @param  array<string, mixed>  $cambios
 */
function registrarEmpleado(array $cambios = []): TestResponse
{
    return test()
        ->actingAs(test()->usuario)
        ->post('/empleados', [...formularioDeEmpleado(), ...$cambios]);
}

/**
 * Lo que quedó guardado, leído dentro del contexto de la empresa.
 */
function ultimaVinculacion(): ?Vinculacion
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, fn () => Vinculacion::query()
        ->with('persona')
        ->orderByDesc('id')
        ->first());
}

describe('el registro completo', function () {
    it('registra un empleado con todos sus datos', function () {
        registrarEmpleado()->assertRedirect('/empleados');

        $vinculacion = ultimaVinculacion();

        expect($vinculacion)->not->toBeNull()
            ->and($vinculacion->cargo)->toBe('Coordinadora de Operaciones')
            ->and($vinculacion->tipo_contrato)->toBe(TipoContrato::Indefinido)
            ->and($vinculacion->tipo_riesgo)->toBe(TipoRiesgo::Uno)
            ->and($vinculacion->fecha_ingreso->toDateString())->toBe('2026-02-02')
            ->and((float) $vinculacion->salario)->toBe(4200000.0)
            ->and($vinculacion->persona->nombreCompleto())->toBe('Carolina Isabel Restrepo Vélez')
            ->and($vinculacion->persona->genero)->toBe(Genero::Femenino)
            ->and($vinculacion->persona->celular)->toBe('3007654321')
            ->and($vinculacion->persona->correo)->toBe('carolina.restrepo@example.com');
    });

    it('deja el empleado activo sin que nadie digite el estado', function () {
        registrarEmpleado();

        expect(ultimaVinculacion()->estado)->toBe(EstadoVinculacion::Activo);
    });

    it('ignora el estado aunque venga en el formulario', function () {
        registrarEmpleado(['estado' => EstadoVinculacion::Inactivo->value]);

        expect(ultimaVinculacion()->estado)->toBe(EstadoVinculacion::Activo);
    });

    it('abre el periodo inicial del historial de contrato', function () {
        registrarEmpleado();

        expect(ultimaVinculacion()->periodos()->count())->toBe(1);
    });

    it('confirma con el nombre de quien se acaba de registrar', function () {
        registrarEmpleado()->assertSessionHas(
            'estado',
            'Carolina Isabel Restrepo Vélez quedó registrado como empleado activo.',
        );
    });

    it('guarda a quien no tiene segundo nombre ni segundo apellido', function () {
        registrarEmpleado(['segundo_nombre' => null, 'segundo_apellido' => null])
            ->assertSessionHasNoErrors();

        expect(ultimaVinculacion()->persona->nombreCompleto())->toBe('Carolina Restrepo');
    });

    it('exige el módulo de empleados también al guardar, no solo al ver el listado', function () {
        $ajeno = usuarioDe($this->empresa, Rol::Administrador);

        $this->actingAs($ajeno)
            ->post('/empleados', formularioDeEmpleado())
            ->assertForbidden();

        expect(ultimaVinculacion())->toBeNull();
    });
});

describe('persona y vinculación, que son cosas distintas', function () {
    it('reutiliza la persona cuando el documento ya está registrado en la empresa', function () {
        registrarEmpleado();
        registrarEmpleado(['cargo' => 'Jefa de Operaciones', 'fecha_ingreso' => '2026-06-01']);

        $conteos = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => [
            'personas' => Persona::query()->count(),
            'vinculaciones' => Vinculacion::query()->count(),
        ]);

        expect($conteos)->toBe(['personas' => 1, 'vinculaciones' => 2]);
    });

    it('actualiza los datos personales al volver a registrar el mismo documento', function () {
        registrarEmpleado();
        registrarEmpleado(['direccion' => 'Calle 10 Sur 45-12']);

        expect(ultimaVinculacion()->persona->direccion)->toBe('Calle 10 Sur 45-12');
    });

    it('no reutiliza la persona de otra empresa que tenga el mismo documento', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Alimentos del Norte']);
        $ajena = app(ContextoTenant::class)->paraTenant($otra, fn () => Persona::factory()->create([
            'numero_documento' => '1017245880',
            'primer_nombre' => 'Otra',
        ]));

        registrarEmpleado();

        expect(ultimaVinculacion()->persona_id)->not->toBe($ajena->getKey())
            ->and(ultimaVinculacion()->persona->primer_nombre)->toBe('Carolina');
    });
});

describe('la regla 5.1.1 de país, departamento y ciudad', function () {
    it('guarda el identificador del catálogo cuando el país es Colombia', function () {
        registrarEmpleado();

        $persona = ultimaVinculacion()->persona;

        expect($persona->pais_nacimiento)->toBe(Pais::COLOMBIA)
            ->and($persona->ciudad_nacimiento_id)->toBe($this->catalogos['medellin']->getKey())
            ->and($persona->departamento_nacimiento_exterior)->toBeNull()
            ->and($persona->ciudad_nacimiento_exterior)->toBeNull();
    });

    it('guarda texto libre cuando el país es otro, y ningún identificador', function () {
        registrarEmpleado(['nacimiento' => [
            'pais' => 'VE',
            'departamento' => 'Zulia',
            'ciudad' => 'Maracaibo',
        ]])->assertSessionHasNoErrors();

        $persona = ultimaVinculacion()->persona;

        expect($persona->pais_nacimiento)->toBe('VE')
            ->and($persona->ciudad_nacimiento_id)->toBeNull()
            ->and($persona->departamento_nacimiento_exterior)->toBe('Zulia')
            ->and($persona->ciudad_nacimiento_exterior)->toBe('Maracaibo');
    });

    it('rechaza una ciudad que no es del departamento elegido', function () {
        registrarEmpleado(['residencia' => [
            'pais' => Pais::COLOMBIA,
            'departamento' => (string) $this->catalogos['antioquia']->getKey(),
            'ciudad' => (string) $this->catalogos['barranquilla']->getKey(),
        ]])->assertSessionHasErrors('residencia.ciudad');

        expect(ultimaVinculacion())->toBeNull();
    });

    it('rechaza un país que no está en el catálogo', function () {
        registrarEmpleado(['expedicion' => [
            'pais' => 'ZZ',
            'departamento' => 'Cualquiera',
            'ciudad' => 'Cualquiera',
        ]])->assertSessionHasErrors('expedicion.pais');
    });
});

describe('la fecha de finalización, que la manda la modalidad', function () {
    it('la exige en el contrato a término fijo', function () {
        registrarEmpleado(['tipo_contrato' => TipoContrato::Fijo->value])
            ->assertSessionHasErrors('fecha_fin');
    });

    it('la guarda cuando el contrato es a término fijo', function () {
        registrarEmpleado([
            'tipo_contrato' => TipoContrato::Fijo->value,
            'fecha_fin' => '2027-02-01',
        ])->assertSessionHasNoErrors();

        expect(ultimaVinculacion()->fecha_fin->toDateString())->toBe('2027-02-01');
    });

    it('la prohíbe en el contrato indefinido', function () {
        registrarEmpleado(['fecha_fin' => '2027-02-01'])
            ->assertSessionHasErrors('fecha_fin');
    });

    it('la prohíbe en obra o labor', function () {
        registrarEmpleado([
            'tipo_contrato' => TipoContrato::ObraLabor->value,
            'fecha_fin' => '2027-02-01',
        ])->assertSessionHasErrors('fecha_fin');
    });

    it('no acepta una finalización anterior al ingreso', function () {
        registrarEmpleado([
            'tipo_contrato' => TipoContrato::Fijo->value,
            'fecha_ingreso' => '2026-02-02',
            'fecha_fin' => '2026-01-02',
        ])->assertSessionHasErrors('fecha_fin');
    });
});

describe('lo que el formulario no deja pasar', function () {
    it('exige los campos obligatorios de la sección 5.1', function () {
        $this->actingAs($this->usuario)
            ->post('/empleados', [])
            ->assertSessionHasErrors([
                'tipo_documento', 'numero_documento', 'fecha_expedicion_documento',
                'primer_nombre', 'primer_apellido', 'fecha_nacimiento', 'genero',
                'direccion', 'celular', 'correo', 'eps_id', 'fondo_pension_id', 'arl_id', 'tipo_riesgo',
                'caja_compensacion_id', 'banco_id', 'cargo', 'tipo_contrato',
                'fecha_ingreso', 'salario', 'bonificacion',
            ]);
    });

    it('no acepta un celular que no tenga exactamente diez dígitos', function () {
        registrarEmpleado(['celular' => '30012345'])->assertSessionHasErrors('celular');
    });

    it('no acepta un correo sin formato de correo', function () {
        registrarEmpleado(['correo' => 'no-es-un-correo'])->assertSessionHasErrors('correo');
    });

    it('no ofrece ni acepta una entidad desactivada', function () {
        $this->catalogos['eps']->update(['activo' => false]);

        registrarEmpleado()->assertSessionHasErrors('eps_id');
    });

    it('acepta la bonificación en cero, que es su valor habitual', function () {
        registrarEmpleado(['bonificacion' => '0'])->assertSessionHasNoErrors();

        expect((float) ultimaVinculacion()->bonificacion)->toBe(0.0);
    });

    it('no acepta un salario negativo', function () {
        registrarEmpleado(['salario' => '-1'])->assertSessionHasErrors('salario');
    });

    it('conserva los ceros a la izquierda del documento', function () {
        registrarEmpleado(['numero_documento' => '000123'])->assertSessionHasNoErrors();

        expect(ultimaVinculacion()->persona->numero_documento)->toBe('000123');
    });

    it('no acepta una fecha de nacimiento futura', function () {
        registrarEmpleado(['fecha_nacimiento' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('fecha_nacimiento');
    });

    it('no guarda nada cuando la validación falla', function () {
        $this->actingAs($this->usuario)->post('/empleados', []);

        $personas = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Persona::query()->count());

        expect($personas)->toBe(0);
    });
});

describe('la pantalla del asistente', function () {
    it('lleva las listas que los cuatro pasos necesitan', function () {
        $respuesta = $this->actingAs($this->usuario)->get('/empleados/nuevo');

        $respuesta->assertOk();

        $props = $respuesta->viewData('page')['props'];

        expect($props['opciones'])->toHaveKeys([
            'documentos', 'generos', 'contratos', 'riesgos',
            'eps', 'fondosPension', 'arls', 'cajasCompensacion', 'bancos',
        ])
            ->and($props['ubicaciones'])->toHaveKeys(['paises', 'departamentos', 'codigoDeColombia'])
            ->and($props['opciones']['generos'])->toHaveCount(count(Genero::cases()))
            ->and($props['opciones']['riesgos'])->toHaveCount(count(TipoRiesgo::cases()));
    });

    it('no ofrece una entidad desactivada', function () {
        $this->catalogos['eps']->update(['activo' => false]);

        $respuesta = $this->actingAs($this->usuario)->get('/empleados/nuevo');

        expect($respuesta->viewData('page')['props']['opciones']['eps'])->toBe([]);
    });
});
