<?php

use App\Contratacion\TipoContrato;
use App\Incapacidades\OrigenIncapacidad;
use App\Licencias\TipoDeLicencia;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\PeriodoContractual;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Terminacion;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Novedades\ComprobacionDeTraslape;
use App\Proveedor\PlantaDeMuestra;
use App\Terminaciones\TipoDeSalida;
use Database\Seeders\CatalogosSeeder;

/**
 * La planta compartida por el ambiente de demostración y el seeder de
 * desarrollo.
 *
 * Lo que se prueba aquí no es que existan 28 filas —eso sería repetir el
 * número—, sino las dos propiedades de las que depende que sirva para algo:
 * que **cubra** cada situación que el sistema sabe resolver, y que **no
 * contradiga** a las reglas del propio sistema.
 */
beforeEach(function (): void {
    // Los catálogos de verdad y no unos mínimos escritos a mano: parte de lo
    // que se prueba aquí es que la planta reparta varias EPS y varias ciudades,
    // y con un catálogo de una sola EPS eso no se puede ni afirmar ni negar.
    $this->seed(CatalogosSeeder::class);

    $this->contexto = app(ContextoTenant::class);

    $this->empresa = Tenant::factory()->create();

    $this->contexto->paraTenant($this->empresa, function (): void {
        app(PlantaDeMuestra::class)->escribir();
    });
});

it('escribe una planta con gente suficiente para que las pantallas tengan qué enseñar', function () {
    $this->contexto->paraTenant($this->empresa, function (): void {
        expect(Persona::query()->count())->toBe(PlantaDeMuestra::TOTAL)
            ->and(Vinculacion::query()->count())->toBe(PlantaDeMuestra::TOTAL);
    });
});

it('le da a casi todo el mundo más de una novedad, que era el hallazgo de David', function () {
    // La planta anterior daba una novedad a cada persona como mucho, así que
    // entrar a una ficha cualquiera la mostraba vacía. Esta prueba es la que
    // impide volver a esa situación sin que nadie se entere.
    $this->contexto->paraTenant($this->empresa, function (): void {
        $conNovedades = Vinculacion::query()
            ->where(fn ($consulta) => $consulta
                ->has('vacaciones')
                ->orHas('incapacidades')
                ->orHas('licencias')
            )
            ->count();

        $conVariasVacaciones = Vinculacion::query()->has('vacaciones', '>=', 2)->count();

        expect($conNovedades)->toBeGreaterThanOrEqual(20)
            ->and($conVariasVacaciones)->toBeGreaterThanOrEqual(10);
    });
});

it('cubre los tres tipos de contrato, los tres de licencia y los dos orígenes de incapacidad', function () {
    $this->contexto->paraTenant($this->empresa, function (): void {
        $contratos = Vinculacion::query()->distinct()->pluck('tipo_contrato')
            ->map(fn ($tipo) => $tipo instanceof TipoContrato ? $tipo->value : $tipo)->sort()->values();

        $licencias = Licencia::query()->distinct()->pluck('tipo')
            ->map(fn ($tipo) => $tipo instanceof TipoDeLicencia ? $tipo->value : $tipo)->sort()->values();

        $origenes = Incapacidad::query()->distinct()->pluck('origen')
            ->map(fn ($origen) => $origen instanceof OrigenIncapacidad ? $origen->value : $origen)->sort()->values();

        expect($contratos)->toHaveCount(count(TipoContrato::cases()))
            ->and($licencias)->toHaveCount(count(TipoDeLicencia::cases()))
            ->and($origenes)->toHaveCount(count(OrigenIncapacidad::cases()));
    });
});

it('tiene una salida de cada uno de los siete tipos, para que el filtro del listado filtre algo', function () {
    $this->contexto->paraTenant($this->empresa, function (): void {
        $tipos = Terminacion::query()->pluck('tipo_salida')
            ->map(fn ($tipo) => $tipo instanceof TipoDeSalida ? $tipo->value : $tipo)
            ->unique()->sort()->values();

        expect($tipos)->toHaveCount(count(TipoDeSalida::cases()))
            ->and(Terminacion::query()->count())->toBe(PlantaDeMuestra::INACTIVOS);
    });
});

it('deja inactivas exactamente a las personas que tienen salida registrada', function () {
    $this->contexto->paraTenant($this->empresa, function (): void {
        $inactivas = Vinculacion::query()->get()->filter(fn (Vinculacion $v) => ! $v->estaActiva());

        expect($inactivas)->toHaveCount(PlantaDeMuestra::INACTIVOS);

        foreach ($inactivas as $vinculacion) {
            expect($vinculacion->terminacion)->not->toBeNull()
                ->and($vinculacion->fechaDeSalida())->not->toBeNull();
        }
    });
});

it('escribe prórrogas y movimientos, no solo el periodo inicial', function () {
    $this->contexto->paraTenant($this->empresa, function (): void {
        // Cada vinculación trae su periodo inicial del observador; que haya más
        // periodos que vinculaciones es la prueba de que las prórrogas entraron.
        expect(PeriodoContractual::query()->count())->toBeGreaterThan(PlantaDeMuestra::TOTAL)
            ->and(Movimiento::query()->count())->toBeGreaterThanOrEqual(10);
    });
});

it('reparte varias EPS y varias ciudades, para que los filtros no devuelvan siempre todo', function () {
    $this->contexto->paraTenant($this->empresa, function (): void {
        expect(Vinculacion::query()->distinct()->pluck('eps_id')->count())->toBeGreaterThanOrEqual(5)
            ->and(Persona::query()->distinct()->pluck('ciudad_residencia_id')->count())->toBeGreaterThanOrEqual(3);
    });
});

it('le pone celular y correo a todo el mundo, que es lo que pidió HD-06', function () {
    $this->contexto->paraTenant($this->empresa, function (): void {
        expect(Persona::query()->whereNull('celular')->count())->toBe(0)
            ->and(Persona::query()->whereNull('correo')->count())->toBe(0)
            ->and(Persona::query()->distinct()->pluck('celular')->count())->toBe(PlantaDeMuestra::TOTAL);
    });
});

it('no escribe ni una novedad que sus propios formularios rechazarían por traslape', function () {
    // Es la prueba que ata la planta a la regla de HD-03. Se usa el mismo
    // comprobador que usan los `FormRequest`, no una lógica paralela: si la
    // regla cambia, esta prueba cambia con ella sola.
    $this->contexto->paraTenant($this->empresa, function (): void {
        foreach (Vacacion::query()->get() as $vacacion) {
            expect(ComprobacionDeTraslape::paraVacaciones(
                $vacacion->vinculacion_id, $vacacion->fecha_inicio, $vacacion->fecha_fin, $vacacion->getKey()
            ))->toBeNull();
        }

        foreach (Incapacidad::query()->get() as $incapacidad) {
            expect(ComprobacionDeTraslape::paraIncapacidad(
                $incapacidad->vinculacion_id, $incapacidad->fecha_inicio, $incapacidad->fecha_fin, $incapacidad->getKey()
            ))->toBeNull();
        }

        foreach (Licencia::query()->get() as $licencia) {
            expect(ComprobacionDeTraslape::paraLicencia(
                $licencia->vinculacion_id, $licencia->fecha_inicio, $licencia->fecha_fin, $licencia->getKey()
            ))->toBeNull();
        }
    });
});

it('escribe siempre la misma planta, sin importar cuántas veces se llame', function () {
    $otra = Tenant::factory()->create();

    $this->contexto->paraTenant($otra, function (): void {
        app(PlantaDeMuestra::class)->escribir();
    });

    $documentosDeUna = $this->contexto->paraTenant(
        $this->empresa,
        fn () => Persona::query()->orderBy('numero_documento')->pluck('numero_documento')->all()
    );

    $documentosDeLaOtra = $this->contexto->paraTenant(
        $otra,
        fn () => Persona::query()->orderBy('numero_documento')->pluck('numero_documento')->all()
    );

    expect($documentosDeLaOtra)->toBe($documentosDeUna);
});

it('no se lleva ni una fila a la empresa de al lado', function () {
    $vecina = Tenant::factory()->create();

    $this->contexto->paraTenant($vecina, function (): void {
        expect(Persona::query()->count())->toBe(0)
            ->and(Vacacion::query()->count())->toBe(0)
            ->and(Terminacion::query()->count())->toBe(0);
    });
});
