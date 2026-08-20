<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EstadoCivil;
use App\Contratacion\MedioTransporte;
use App\Contratacion\NivelEducativo;
use App\Contratacion\TiempoDesplazamiento;
use App\Contratacion\TipoVivienda;
use App\Models\PerfilSociodemografico;
use App\Models\Persona;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;

/**
 * El perfil sociodemográfico, F1-06: la sección 5.2 del documento 1.
 *
 * Lo que se defiende aquí, en orden de importancia:
 *
 * 1. **Lo enciende y lo apaga la empresa.** Es el «hecho cuando» de la tarea:
 *    con el parámetro en sí el asistente tiene cinco pasos y guarda el bloque;
 *    con el parámetro en no tiene cuatro y no guarda nada, aunque el formulario
 *    llegue con el bloque lleno.
 * 2. **Todos sus campos son opcionales aunque el bloque esté habilitado.**
 *    Registrar sin llenar ni uno tiene que funcionar, y no dejar una fila de
 *    nulos.
 * 3. **Encenderlo después no rompe nada.** Los empleados que ya existían quedan
 *    en blanco y el sistema no lo señala como error.
 * 4. **Lo que sí se digita tiene que estar en su lista.** Un estrato 9 no entra.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `catalogosDelFormulario()`,
 * `formularioDeEmpleado()`, `registrarEmpleado()` y `ultimaVinculacion()` en
 * `RegistroDeEmpleadoTest.php`: Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create([
        'nombre' => 'Distribuidora El Progreso',
        'perfil_sociodemografico_habilitado' => true,
    ]);

    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

    $this->catalogos = catalogosDelFormulario();
});

/**
 * Un bloque lleno con las ocho respuestas.
 *
 * @return array<string, mixed>
 */
function perfilCompleto(): array
{
    return [
        'estado_civil' => EstadoCivil::UnionLibre->value,
        'nivel_educativo' => NivelEducativo::TecnicoTecnologo->value,
        'numero_hijos' => '2',
        'personas_a_cargo' => '3',
        'tipo_vivienda' => TipoVivienda::Arrendada->value,
        'estrato' => '3',
        'medio_transporte' => MedioTransporte::TransportePublico->value,
        'tiempo_desplazamiento' => TiempoDesplazamiento::Entre30Y60Minutos->value,
    ];
}

/**
 * El perfil que quedó guardado, leído dentro del contexto de la empresa.
 */
function ultimoPerfil(): ?PerfilSociodemografico
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, fn () => PerfilSociodemografico::query()
        ->orderByDesc('id')
        ->first());
}

/**
 * Deja el parámetro de la empresa como lo pida el caso.
 */
function habilitarPerfil(bool $habilitado): void
{
    test()->empresa->forceFill(['perfil_sociodemografico_habilitado' => $habilitado])->save();
}

describe('el parámetro de la empresa', function () {
    it('agrega el paso al asistente cuando está habilitado', function () {
        $this->actingAs($this->usuario)
            ->get('/empleados/nuevo')
            ->assertInertia(fn ($pagina) => $pagina
                ->where('perfilSociodemografico', true)
                ->has('opciones.estadosCiviles', 6)
                ->has('opciones.nivelesEducativos', 6)
                ->has('opciones.tiposVivienda', 3)
                ->has('opciones.estratos', 6)
                ->has('opciones.mediosTransporte', 7)
                ->has('opciones.tiemposDesplazamiento', 5)
                ->etc());
    });

    it('deja el asistente sin el paso cuando está deshabilitado', function () {
        habilitarPerfil(false);

        $this->actingAs($this->usuario)
            ->get('/empleados/nuevo')
            ->assertInertia(fn ($pagina) => $pagina->where('perfilSociodemografico', false)->etc());
    });
});

describe('la escritura del bloque', function () {
    it('guarda las ocho respuestas del perfil', function () {
        registrarEmpleado(['perfil' => perfilCompleto()])->assertRedirect('/empleados');

        $perfil = ultimoPerfil();

        expect($perfil)->not->toBeNull()
            ->and($perfil->estado_civil)->toBe(EstadoCivil::UnionLibre)
            ->and($perfil->nivel_educativo)->toBe(NivelEducativo::TecnicoTecnologo)
            ->and($perfil->numero_hijos)->toBe(2)
            ->and($perfil->personas_a_cargo)->toBe(3)
            ->and($perfil->tipo_vivienda)->toBe(TipoVivienda::Arrendada)
            ->and($perfil->estrato)->toBe(3)
            ->and($perfil->medio_transporte)->toBe(MedioTransporte::TransportePublico)
            ->and($perfil->tiempo_desplazamiento)->toBe(TiempoDesplazamiento::Entre30Y60Minutos);
    });

    it('cuelga el perfil de la persona y no de la vinculación', function () {
        registrarEmpleado(['perfil' => perfilCompleto()]);

        expect(ultimoPerfil()->persona_id)->toBe(ultimaVinculacion()->persona_id);
    });

    it('registra al empleado con el bloque en blanco', function () {
        registrarEmpleado(['perfil' => []])->assertRedirect('/empleados');

        expect(ultimaVinculacion())->not->toBeNull()
            ->and(ultimoPerfil())->toBeNull();
    });

    it('registra al empleado sin mandar el bloque siquiera', function () {
        registrarEmpleado()->assertRedirect('/empleados');

        expect(ultimaVinculacion())->not->toBeNull()
            ->and(ultimoPerfil())->toBeNull();
    });

    it('guarda lo poco que se haya llenado', function () {
        registrarEmpleado(['perfil' => ['estrato' => '2']]);

        $perfil = ultimoPerfil();

        expect($perfil)->not->toBeNull()
            ->and($perfil->estrato)->toBe(2)
            ->and($perfil->estado_civil)->toBeNull()
            ->and($perfil->numero_hijos)->toBeNull();
    });

    it('no escribe nada si la empresa tiene el bloque deshabilitado', function () {
        habilitarPerfil(false);

        registrarEmpleado(['perfil' => perfilCompleto()])->assertRedirect('/empleados');

        expect(ultimaVinculacion())->not->toBeNull()
            ->and(ultimoPerfil())->toBeNull();
    });

    it('actualiza el perfil que ya tenía la persona en vez de crear otro', function () {
        registrarEmpleado(['perfil' => perfilCompleto()]);

        registrarEmpleado(['perfil' => [
            ...perfilCompleto(),
            'numero_hijos' => '3',
            'estrato' => '4',
        ]]);

        $perfiles = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => PerfilSociodemografico::query()->get(),
        );

        expect($perfiles)->toHaveCount(1)
            ->and($perfiles->first()->numero_hijos)->toBe(3)
            ->and($perfiles->first()->estrato)->toBe(4);
    });
});

describe('habilitar el bloque después', function () {
    it('deja en blanco a los empleados que ya existían y no lo marca como error', function () {
        habilitarPerfil(false);

        registrarEmpleado()->assertRedirect('/empleados');

        habilitarPerfil(true);

        $persona = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Persona::query()->with('perfil')->orderByDesc('id')->first(),
        );

        expect($persona->perfil)->toBeNull();

        $this->actingAs($this->usuario)->get('/empleados')->assertOk();
        $this->actingAs($this->usuario)->get('/empleados/nuevo')->assertOk();
    });
});

describe('las listas del bloque', function () {
    it('rechaza un estrato fuera del 1 al 6', function () {
        registrarEmpleado(['perfil' => ['estrato' => '9']])
            ->assertSessionHasErrors('perfil.estrato');

        expect(ultimoPerfil())->toBeNull();
    });

    it('rechaza un nivel educativo que no está en la lista', function () {
        registrarEmpleado(['perfil' => ['nivel_educativo' => 'doctorado_honoris_causa']])
            ->assertSessionHasErrors('perfil.nivel_educativo');
    });

    it('rechaza un número de hijos negativo', function () {
        registrarEmpleado(['perfil' => ['numero_hijos' => '-1']])
            ->assertSessionHasErrors('perfil.numero_hijos');
    });

    it('nombra los campos como los reconoce quien registra', function () {
        registrarEmpleado(['perfil' => ['estrato' => '9']])
            ->assertSessionHasErrors([
                'perfil.estrato' => 'El campo estrato socioeconómico no debe ser mayor que 6.',
            ]);
    });
});
