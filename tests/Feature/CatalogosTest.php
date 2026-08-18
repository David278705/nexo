<?php

use App\Catalogos\Ubicaciones;
use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Ciudad;
use App\Models\Departamento;
use App\Models\Eps;
use App\Models\Festivo;
use App\Models\FondoPension;
use App\Models\Pais;
use App\Multiempresa\PerteneceATenant;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Las nueve tablas de catálogo, con la tabla que le corresponde a cada modelo.
 *
 * @return array<class-string, string>
 */
function tablasDeCatalogo(): array
{
    return [
        Pais::class => 'paises',
        Departamento::class => 'departamentos',
        Ciudad::class => 'ciudades',
        Eps::class => 'eps',
        FondoPension::class => 'fondos_pension',
        Arl::class => 'arls',
        CajaCompensacion::class => 'cajas_compensacion',
        Banco::class => 'bancos',
        Festivo::class => 'festivos',
    ];
}

it('no pone empresa en ninguna tabla de catálogo', function () {
    // Son globales: iguales para todos los clientes. Si a alguna le apareciera
    // `tenant_id`, alguien la habría confundido con una tabla de negocio y
    // estaría cargando la lista de EPS una vez por empresa.
    foreach (tablasDeCatalogo() as $tabla) {
        expect(Schema::hasColumn($tabla, 'tenant_id'))
            ->toBeFalse("La tabla {$tabla} no debería llevar tenant_id");
    }
});

it('no aplica el trait de empresa a ningún modelo de catálogo', function () {
    foreach (array_keys(tablasDeCatalogo()) as $modelo) {
        expect(class_uses_recursive($modelo))
            ->not->toContain(PerteneceATenant::class);
    }
});

describe('con los catálogos cargados', function () {
    beforeEach(function () {
        $this->seed(CatalogosSeeder::class);
    });

    it('carga la lista de países con Colombia dentro', function () {
        expect(Pais::count())->toBeGreaterThan(190);

        $colombia = Pais::where('codigo', Pais::COLOMBIA)->first();

        expect($colombia)->not->toBeNull()
            ->and($colombia->nombre)->toBe('Colombia');
    });

    it('carga los 33 departamentos con su código DANE', function () {
        expect(Departamento::count())->toBe(33);

        // Tres de la tabla del DANE, tomados de puntas distintas de la lista.
        expect(Departamento::where('codigo_dane', '05')->value('nombre'))->toBe('Antioquia')
            ->and(Departamento::where('codigo_dane', '11')->value('nombre'))->toBe('Bogotá D.C.')
            ->and(Departamento::where('codigo_dane', '99')->value('nombre'))->toBe('Vichada');
    });

    it('carga los municipios de Colombia, cada uno colgado de su departamento', function () {
        expect(Ciudad::count())->toBeGreaterThan(1000);

        // Ningún departamento se queda sin municipios: si uno quedara vacío, su
        // lista encadenada no ofrecería nada y el formulario no se podría
        // terminar para quien nació allí.
        $vacios = Departamento::doesntHave('ciudades')->pluck('nombre')->all();

        expect($vacios)->toBe([]);
    });

    it('encadena el departamento con sus ciudades y solo con las suyas', function () {
        $antioquia = Departamento::where('codigo_dane', '05')->firstOrFail();

        $ciudades = $antioquia->ciudades->pluck('nombre');

        expect($ciudades)->toContain('Medellín')
            ->and($ciudades)->toContain('Envigado')
            // Cali es del Valle. Si apareciera aquí, el encadenamiento no
            // estaría filtrando nada.
            ->and($ciudades)->not->toContain('Cali');
    });

    it('entrega al formulario los departamentos con sus ciudades dentro', function () {
        $ubicaciones = Ubicaciones::paraFormulario();

        expect($ubicaciones['codigoDeColombia'])->toBe('CO')
            ->and($ubicaciones['paises'])->not->toBeEmpty()
            ->and($ubicaciones['departamentos'])->toHaveCount(33);

        $cundinamarca = collect($ubicaciones['departamentos'])
            ->firstWhere('texto', 'Cundinamarca');

        expect(collect($cundinamarca['ciudades'])->pluck('texto'))
            ->toContain('Soacha')
            ->toContain('Zipaquirá');
    });

    it('sirve la página de muestra con las ubicaciones reales', function () {
        $this->get('/sistema-de-diseno')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('SistemaDeDiseno')
                ->has('departamentos', 33)
                ->where('codigoDeColombia', 'CO')
                ->etc()
            );
    });

    it('carga el calendario de festivos año por año', function () {
        expect(Festivo::aniosCargados())->toBe([2025, 2026, 2027]);

        // 2026 tiene los dieciocho festivos del calendario colombiano. 2025
        // tiene diecisiete filas para dieciocho celebraciones: San Pedro y el
        // Sagrado Corazón caen los dos en el lunes 30 de junio.
        expect(Festivo::delAnio(2026)->count())->toBe(18)
            ->and(Festivo::delAnio(2025)->count())->toBe(17)
            ->and(Festivo::where('fecha', '2025-06-30')->value('nombre'))
            ->toContain('San Pedro');
    });

    it('traslada al lunes los festivos que manda trasladar la ley', function () {
        // Los siete de la Ley 51 de 1983 más los tres de Pascua que se
        // trasladan: en 2026 todos tienen que caer en lunes.
        $trasladados = [
            '2026-01-12', // Reyes Magos
            '2026-03-23', // San José
            '2026-05-18', // Ascensión
            '2026-06-08', // Corpus Christi
            '2026-06-15', // Sagrado Corazón
            '2026-06-29', // San Pedro y San Pablo
            '2026-08-17', // Asunción
            '2026-10-12', // Día de la Raza
            '2026-11-02', // Todos los Santos
            '2026-11-16', // Independencia de Cartagena
        ];

        foreach ($trasladados as $fecha) {
            expect(Festivo::esFestivo(Carbon::parse($fecha)))->toBeTrue("Falta el festivo {$fecha}")
                ->and(Carbon::parse($fecha)->isMonday())->toBeTrue("{$fecha} debería ser lunes");
        }
    });

    it('no traslada los que no se trasladan', function () {
        // Jueves y Viernes Santo se quedan donde caen, y los seis de fecha fija
        // también. Si alguno se hubiera «arreglado» al lunes, el calendario
        // estaría calculado con una fórmula en vez de verificado a mano.
        foreach (['2026-04-02', '2026-04-03', '2026-01-01', '2026-05-01', '2026-07-20', '2026-08-07', '2026-12-08', '2026-12-25'] as $fecha) {
            expect(Festivo::esFestivo(Carbon::parse($fecha)))->toBeTrue("Falta el festivo {$fecha}");
        }
    });

    it('carga también los festivos que caen en sábado', function () {
        // Es la instrucción expresa del capítulo 10: para la empresa que tiene
        // el sábado como día laboral, un festivo en sábado sí cambia el conteo
        // de vacaciones. En 2027 son tres.
        $sabados = collect(Festivo::fechasDelAnio(2027))
            ->filter(fn (string $fecha): bool => Carbon::parse($fecha)->isSaturday())
            ->values();

        expect($sabados->all())->toBe(['2027-05-01', '2027-08-07', '2027-12-25']);
    });

    it('no duplica nada al volver a correr los seeders', function () {
        $antes = [Pais::count(), Departamento::count(), Ciudad::count(), Festivo::count()];

        $this->seed(CatalogosSeeder::class);

        expect([Pais::count(), Departamento::count(), Ciudad::count(), Festivo::count()])
            ->toBe($antes);
    });

    it('deja vacías las cinco listas de entidades mientras falte la decisión D.5', function () {
        // Las tablas existen y los modelos también, pero el contenido de EPS,
        // fondos, ARL, cajas y bancos es una decisión de negocio pendiente. Que
        // estén vacías es el estado correcto hoy: una lista inventada sería
        // peor. Cuando lleguen los datos, esta prueba cambia con ellos.
        expect(Eps::count())->toBe(0)
            ->and(FondoPension::count())->toBe(0)
            ->and(Arl::count())->toBe(0)
            ->and(CajaCompensacion::count())->toBe(0)
            ->and(Banco::count())->toBe(0);
    });

    it('ofrece en las listas solo lo que está activo', function () {
        $ciudad = Ciudad::query()->firstOrFail();
        $ciudad->update(['activo' => false]);

        expect(Ciudad::paraElegir()->pluck('id'))->not->toContain($ciudad->id);
    });
});
