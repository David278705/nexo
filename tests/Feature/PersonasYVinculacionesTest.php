<?php

use App\Contratacion\EstadoVinculacion;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoDocumento;
use App\Models\PerfilSociodemografico;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Multiempresa\PerteneceATenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La separación entre persona y vinculación, que es la regla 5 del proyecto y
 * la decisión que este archivo existe para defender.
 *
 * Si alguna vez alguien funde las dos tablas en una, o mueve la unicidad del
 * documento a `vinculaciones`, estas pruebas se ponen en rojo antes de que el
 * error llegue a los datos de un cliente.
 */
beforeEach(function () {
    $this->contexto = app(ContextoTenant::class);
    $this->empresa = Tenant::factory()->create(['nombre' => 'Empresa A']);
    $this->otraEmpresa = Tenant::factory()->create(['nombre' => 'Empresa B']);
});

/**
 * Ejecuta algo dentro de la empresa de la prueba.
 */
function enLaEmpresa(Closure $accion): mixed
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, $accion);
}

describe('la separación entre persona y vinculación', function () {
    it('permite dos vinculaciones de la misma persona sin duplicar sus datos personales', function () {
        [$persona, $primera, $segunda] = enLaEmpresa(function () {
            $persona = Persona::factory()->create(['numero_documento' => '1020304050']);

            return [
                $persona,
                Vinculacion::factory()->de($persona)->conIngreso('2023-02-01')->terminada()->create(),
                Vinculacion::factory()->de($persona)->conIngreso('2026-03-01')->create(),
            ];
        });

        expect(enLaEmpresa(fn () => Persona::count()))->toBe(1)
            ->and(enLaEmpresa(fn () => Vinculacion::count()))->toBe(2)
            ->and($primera->persona_id)->toBe($persona->id)
            ->and($segunda->persona_id)->toBe($persona->id)
            ->and(enLaEmpresa(fn () => $persona->vinculaciones()->count()))->toBe(2);
    });

    it('guarda el documento una sola vez, en la persona y no en la vinculación', function () {
        expect(Schema::hasColumn('personas', 'numero_documento'))->toBeTrue()
            ->and(Schema::hasColumn('vinculaciones', 'numero_documento'))->toBeFalse();
    });

    it('devuelve las vinculaciones de la más reciente a la más antigua', function () {
        $persona = enLaEmpresa(function () {
            $persona = Persona::factory()->create();

            Vinculacion::factory()->de($persona)->conIngreso('2022-05-01')->terminada()->create();
            Vinculacion::factory()->de($persona)->conIngreso('2026-01-15')->create();

            return $persona;
        });

        $fechas = enLaEmpresa(
            fn () => $persona->vinculaciones()->pluck('fecha_ingreso')
                ->map(fn ($fecha) => $fecha->toDateString())
                ->all()
        );

        expect($fechas)->toBe(['2026-01-15', '2022-05-01']);
    });
});

describe('el documento único por empresa', function () {
    it('no admite dos personas vivas con el mismo documento en la misma empresa', function () {
        enLaEmpresa(function () {
            Persona::factory()->create(['numero_documento' => '1111111111']);
            Persona::factory()->create(['numero_documento' => '1111111111']);
        });
    })->throws(QueryException::class);

    it('sí admite el mismo documento en dos empresas distintas', function () {
        $this->contexto->paraTenant(
            $this->empresa,
            fn () => Persona::factory()->create(['numero_documento' => '2222222222'])
        );

        $laOtra = $this->contexto->paraTenant(
            $this->otraEmpresa,
            fn () => Persona::factory()->create(['numero_documento' => '2222222222'])
        );

        expect($laOtra->tenant_id)->toBe($this->otraEmpresa->id)
            ->and($laOtra->numero_documento)->toBe('2222222222');
    });

    it('encuentra a la persona por su documento dentro de la empresa', function () {
        enLaEmpresa(fn () => Persona::factory()->create(['numero_documento' => '3333333333']));

        $encontradaAqui = enLaEmpresa(fn () => Persona::query()->conDocumento('3333333333')->first());

        $encontradaAlla = $this->contexto->paraTenant(
            $this->otraEmpresa,
            fn () => Persona::query()->conDocumento('3333333333')->first()
        );

        expect($encontradaAqui)->not->toBeNull()
            ->and($encontradaAlla)->toBeNull();
    });

    it('libera el documento cuando la persona se borra lógicamente', function () {
        $registrada = enLaEmpresa(function () {
            $primera = Persona::factory()->create(['numero_documento' => '4444444444']);
            $primera->delete();

            return Persona::factory()->create(['numero_documento' => '4444444444']);
        });

        // La primera sigue existiendo: en este sistema no se borra de verdad.
        expect($registrada->exists)->toBeTrue()
            ->and(enLaEmpresa(fn () => Persona::withTrashed()->count()))->toBe(2)
            ->and(enLaEmpresa(fn () => Persona::count()))->toBe(1);
    });
});

describe('la vinculación', function () {
    it('nace activa sin que nadie digite el estado', function () {
        $vinculacion = enLaEmpresa(fn () => Vinculacion::factory()->create());

        expect($vinculacion->estado)->toBe(EstadoVinculacion::Activo)
            ->and($vinculacion->estaActiva())->toBeTrue();
    });

    it('no deja poner el estado desde un formulario', function () {
        $vinculacion = enLaEmpresa(fn () => Vinculacion::factory()->create());

        // `fill()` es lo que hace un controlador con los datos de la petición.
        $vinculacion->fill(['estado' => EstadoVinculacion::Inactivo->value]);

        expect($vinculacion->estado)->toBe(EstadoVinculacion::Activo);
    });

    it('pasa a inactiva solo por el camino de la terminación', function () {
        $vinculacion = enLaEmpresa(function () {
            $creada = Vinculacion::factory()->create();
            $creada->desactivar();
            $creada->save();

            return $creada;
        });

        expect($vinculacion->estaActiva())->toBeFalse()
            ->and(enLaEmpresa(fn () => Vinculacion::query()->activas()->count()))->toBe(0);
    });

    it('deja la fecha de finalización vacía salvo en término fijo', function () {
        [$indefinida, $obra, $fija] = enLaEmpresa(fn () => [
            Vinculacion::factory()->create(),
            Vinculacion::factory()->deObraOLabor()->create(),
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-12-31')->create(),
        ]);

        expect($indefinida->fecha_fin)->toBeNull()
            ->and($obra->fecha_fin)->toBeNull()
            ->and($fija->fecha_fin?->toDateString())->toBe('2026-12-31')
            ->and($fija->tipo_contrato)->toBe(TipoContrato::Fijo)
            ->and($fija->tipo_contrato->exigeFechaFin())->toBeTrue();
    });

    it('no guarda ninguna columna de saldo de vacaciones', function () {
        // Regla 2 del proyecto: el saldo se calcula. `saldo_inicial_vacaciones`
        // es otra cosa —el punto de partida de la carga inicial— y sí existe.
        expect(Schema::hasColumn('vinculaciones', 'saldo_vacaciones'))->toBeFalse()
            ->and(Schema::hasColumn('vinculaciones', 'saldo_inicial_vacaciones'))->toBeTrue();
    });

    it('guarda el dinero con precisión fija', function () {
        $vinculacion = enLaEmpresa(
            fn () => Vinculacion::factory()->create(['salario' => 2_500_000, 'bonificacion' => 130_500.75])
        );

        expect($vinculacion->fresh()?->salario)->toBe('2500000.00')
            ->and($vinculacion->fresh()?->bonificacion)->toBe('130500.75');
    });
});

describe('el perfil sociodemográfico', function () {
    it('cuelga de la persona y es uno solo', function () {
        $persona = enLaEmpresa(function () {
            $persona = Persona::factory()->create();
            PerfilSociodemografico::factory()->de($persona)->create();

            return $persona;
        });

        expect(enLaEmpresa(fn () => $persona->perfil))->not->toBeNull()
            ->and(enLaEmpresa(fn () => $persona->perfil?->persona_id))->toBe($persona->id);
    });

    it('admite todos sus campos en blanco', function () {
        $perfil = enLaEmpresa(fn () => PerfilSociodemografico::factory()->vacio()->create());

        expect($perfil->exists)->toBeTrue()
            ->and($perfil->estaVacio())->toBeTrue();
    });

    it('deja a la persona sin perfil sin marcar error', function () {
        // Es lo que pasa cuando una empresa activa el bloque meses después de
        // haber cargado su planta.
        $persona = enLaEmpresa(fn () => Persona::factory()->create());

        expect(enLaEmpresa(fn () => $persona->perfil))->toBeNull();
    });
});

describe('la forma de las tres tablas', function () {
    $tablas = [
        Persona::class => 'personas',
        Vinculacion::class => 'vinculaciones',
        PerfilSociodemografico::class => 'perfil_sociodemografico',
    ];

    it('lleva tenant_id, borrado lógico y el trait en las tres', function () use ($tablas) {
        foreach ($tablas as $modelo => $tabla) {
            expect(Schema::hasColumn($tabla, 'tenant_id'))->toBeTrue("Falta tenant_id en {$tabla}")
                ->and(Schema::hasColumn($tabla, 'deleted_at'))->toBeTrue("Falta deleted_at en {$tabla}")
                ->and(class_uses_recursive($modelo))->toContain(PerteneceATenant::class);
        }
    });

    it('tiene su política de aislamiento activa en PostgreSQL', function () use ($tablas) {
        $conPolitica = DB::table('pg_policies')->pluck('tablename')->all();

        foreach ($tablas as $tabla) {
            expect(in_array($tabla, $conPolitica, true))
                ->toBeTrue("Falta la política de RLS en {$tabla}");
        }
    });
});

describe('la persona', function () {
    it('arma el nombre completo sin dobles espacios cuando faltan los segundos', function () {
        $persona = enLaEmpresa(fn () => Persona::factory()->sinSegundosNombres()->create([
            'primer_nombre' => 'Ana',
            'primer_apellido' => 'Ríos',
        ]));

        expect($persona->nombreCompleto())->toBe('Ana Ríos');
    });

    it('guarda las ubicaciones de Colombia como catálogo y las del exterior como texto', function () {
        [$colombiana, $extranjera] = enLaEmpresa(fn () => [
            Persona::factory()->create(),
            Persona::factory()->nacidaEnElExterior()->create(),
        ]);

        expect($colombiana->ciudad_nacimiento_id)->not->toBeNull()
            ->and($colombiana->ciudad_nacimiento_exterior)->toBeNull()
            ->and(enLaEmpresa(fn () => $colombiana->ciudadNacimiento))->not->toBeNull()
            ->and($extranjera->ciudad_nacimiento_id)->toBeNull()
            ->and($extranjera->ciudad_nacimiento_exterior)->toBe('Maracaibo')
            ->and($extranjera->departamento_nacimiento_exterior)->toBe('Zulia');
    });

    it('llega al departamento de residencia por la ciudad, sin guardarlo dos veces', function () {
        $persona = enLaEmpresa(fn () => Persona::factory()->create());

        $departamento = enLaEmpresa(fn () => $persona->ciudadResidencia?->departamento);

        expect($departamento)->not->toBeNull()
            ->and(Schema::hasColumn('personas', 'departamento_residencia_id'))->toBeFalse();
    });

    it('guarda el tipo de documento como texto legible', function () {
        $persona = enLaEmpresa(fn () => Persona::factory()->create());

        // Se lee por fuera del ORM a propósito: lo que importa es lo que quedó
        // escrito en la columna, no lo que el casteo devuelve.
        $guardado = enLaEmpresa(
            fn () => DB::table('personas')->where('id', $persona->id)->value('tipo_documento')
        );

        expect($persona->tipo_documento)->toBe(TipoDocumento::CedulaCiudadania)
            ->and($guardado)->toBe('cedula_ciudadania');
    });
});
