<?php

use App\Auditoria\AccionAuditada;
use App\Autorizacion\Rol;
use App\Autorizacion\SeccionDelPanel;
use App\Catalogos\Entidades;
use App\Models\Auditoria;
use App\Models\Ciudad;
use App\Models\Departamento;
use App\Models\Eps;
use App\Models\Festivo;
use App\Models\Tenant;
use App\Proveedor\CatalogosDelSistema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Los catálogos del sistema desde el panel del proveedor (documento 4,
 * capítulo 8).
 *
 * Lo que aquí se comprueba, en el orden en que importa:
 *
 * 1. **Agregar una EPS no requiere desplegar código**, que es el «Hecho cuando»
 *    de la tarea: se da de alta desde el panel y aparece esa misma tarde en el
 *    formulario de empleado de todas las empresas.
 * 2. **Nada se borra.** Desactivar una entidad la saca de los formularios y la
 *    deja legible; quitar un festivo es un borrado lógico.
 * 3. **El calendario avisa a tiempo**, desde octubre y no en enero.
 * 4. **Las constantes legales se leen y no se editan**: no hay ninguna ruta que
 *    las escriba, porque viven en la configuración.
 * 5. **Al panel entra solo el `owner`**, aquí como en el resto del panel.
 *
 * `ownerEnElPanel()` vive en `CrearEmpresaTest.php` y `usuarioDe()` en
 * `RolesYPermisosTest.php`; Pest comparte las funciones de todos los archivos.
 */

/**
 * Un departamento con un municipio, que es lo mínimo que la pestaña de ciudades
 * necesita para tener algo que enseñar.
 *
 * @return array{Departamento, Ciudad}
 */
function departamentoConMunicipio(string $departamento, string $municipio): array
{
    $uno = Departamento::factory()->create(['nombre' => $departamento]);
    $ciudad = Ciudad::factory()->create([
        'departamento_id' => $uno->id,
        'nombre' => $municipio,
    ]);

    return [$uno, $ciudad];
}

describe('la pantalla de catálogos', function () {
    it('deja de ser una sección pendiente del panel', function () {
        expect(SeccionDelPanel::pendientes())->not->toContain(SeccionDelPanel::Catalogos);
    });

    it('abre en las constantes legales, con su norma y su verificación', function () {
        ownerEnElPanel();

        $this->get('/proveedor/catalogos')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Proveedor/Catalogos')
                ->where('viendo.pestana', 'constantes')
                ->has('constantes.filas', 10)
                ->where('constantes.filas.0.constante', 'Acumulación de vacaciones por mes')
                ->where('constantes.filas.0.valor', '1,25 días')
                ->where('constantes.filas.0.norma', 'Art. 186 CST — 15 días hábiles por año'));
    });

    it('escribe el valor de las constantes leyendo la configuración, no una copia', function () {
        ownerEnElPanel();

        config()->set('reglas_laborales.preaviso_dias', 45);

        $this->get('/proveedor/catalogos')
            ->assertInertia(fn ($pagina) => $pagina
                ->where('constantes.filas.4.constante', 'Preaviso de no prórroga')
                ->where('constantes.filas.4.valor', '45 días'));
    });

    it('no ofrece ninguna ruta para editar una constante legal', function () {
        // La pantalla es de solo lectura por decisión, no porque falte el
        // formulario: los números legales viven en `config/reglas_laborales.php`
        // y sacarlos de ahí dejaría el archivo mintiendo.
        $rutas = collect(Route::getRoutes())
            ->map(fn ($ruta) => (string) $ruta->uri())
            ->filter(fn (string $uri) => str_contains($uri, 'catalogos/constantes'));

        expect($rutas)->toBeEmpty();
    });

    it('no la abre un usuario de empresa', function () {
        $empresa = Tenant::factory()->create();

        $this->actingAs(usuarioDe($empresa, Rol::Superadministrador))
            ->get('/proveedor/catalogos')
            ->assertForbidden();
    });
});

describe('las cinco listas de entidades', function () {
    it('agrega una EPS que se ofrece de inmediato en el formulario de empleado', function () {
        ownerEnElPanel();

        $this->post('/proveedor/catalogos/entidades/eps', [
            'codigo' => 'EPS050',
            'nombre' => 'Salud Total',
        ])->assertRedirect();

        $ofrecidas = collect(Entidades::paraFormulario()['eps'])->pluck('texto');

        expect($ofrecidas)->toContain('Salud Total');
    });

    it('no deja agregar dos veces la misma entidad', function () {
        ownerEnElPanel();

        Eps::create(['nombre' => 'Sanitas']);

        $this->post('/proveedor/catalogos/entidades/eps', ['nombre' => 'Sanitas'])
            ->assertSessionHasErrors('nombre');

        expect(Eps::query()->where('nombre', 'Sanitas')->count())->toBe(1);
    });

    it('desactiva una entidad en vez de borrarla', function () {
        ownerEnElPanel();

        $eps = Eps::create(['nombre' => 'Coomeva']);

        $this->post("/proveedor/catalogos/entidades/eps/{$eps->id}/estado")
            ->assertRedirect();

        expect(Eps::query()->find($eps->id))->not->toBeNull()
            ->and(Eps::query()->find($eps->id)->activo)->toBeFalse()
            ->and(collect(Entidades::paraFormulario()['eps'])->pluck('texto'))
            ->not->toContain('Coomeva');
    });

    it('vuelve a ofrecer la entidad que se reactiva', function () {
        ownerEnElPanel();

        $eps = Eps::create(['nombre' => 'Coomeva', 'activo' => false]);

        $this->post("/proveedor/catalogos/entidades/eps/{$eps->id}/estado");

        expect(collect(Entidades::paraFormulario()['eps'])->pluck('texto'))
            ->toContain('Coomeva');
    });

    it('corrige el nombre de una entidad ya cargada', function () {
        ownerEnElPanel();

        $eps = Eps::create(['nombre' => 'Nueva EPS']);

        $this->put("/proveedor/catalogos/entidades/eps/{$eps->id}", [
            'codigo' => 'EPS037',
            'nombre' => 'Nueva EPS S.A.',
        ])->assertRedirect();

        expect($eps->fresh()->nombre)->toBe('Nueva EPS S.A.')
            ->and($eps->fresh()->codigo)->toBe('EPS037');
    });

    it('no deja tocar una entidad desde la dirección de otra lista', function () {
        ownerEnElPanel();

        $eps = Eps::create(['nombre' => 'Sura']);

        // El identificador y la lista llegan por separado, así que sin la
        // comprobación bastaría con cambiar la lista de la dirección.
        $this->post("/proveedor/catalogos/entidades/bancos/{$eps->id}/estado")
            ->assertNotFound();
    });

    it('responde 404 a una lista que no existe', function () {
        ownerEnElPanel();

        $this->post('/proveedor/catalogos/entidades/cooperativas', ['nombre' => 'Cualquiera'])
            ->assertNotFound();
    });

    it('deja constancia en la auditoría de quién cambió la lista', function () {
        $owner = ownerEnElPanel();

        $this->post('/proveedor/catalogos/entidades/eps', ['nombre' => 'Compensar']);

        $linea = Auditoria::query()
            ->where('modelo', Eps::class)
            ->where('accion', AccionAuditada::Creado->value)
            ->first();

        expect($linea)->not->toBeNull()
            ->and($linea->user_id)->toBe($owner->id)
            // Es un catálogo global: la línea no es de ninguna empresa.
            ->and($linea->tenant_id)->toBeNull();
    });
});

describe('los municipios', function () {
    it('agrega un municipio al departamento que se está mirando', function () {
        ownerEnElPanel();

        [$departamento] = departamentoConMunicipio('Antioquia', 'Medellín');

        $this->post('/proveedor/catalogos/ciudades', [
            'departamento_id' => $departamento->id,
            'nombre' => 'Envigado',
            'codigo_dane' => '05266',
        ])->assertRedirect();

        expect(Ciudad::query()->where('nombre', 'Envigado')->first()->departamento_id)
            ->toBe($departamento->id);
    });

    it('admite el mismo nombre en dos departamentos distintos', function () {
        ownerEnElPanel();

        [$antioquia] = departamentoConMunicipio('Antioquia', 'Santa Rosa de Osos');
        [$bolivar] = departamentoConMunicipio('Bolívar', 'Cartagena');

        $this->post('/proveedor/catalogos/ciudades', [
            'departamento_id' => $bolivar->id,
            'nombre' => 'Santa Rosa de Osos',
        ])->assertSessionHasNoErrors();

        expect(Ciudad::query()->where('nombre', 'Santa Rosa de Osos')->count())->toBe(2)
            ->and($antioquia->ciudades()->count())->toBe(1);
    });

    it('rechaza un código DANE que no tiene cinco dígitos', function () {
        ownerEnElPanel();

        [$departamento] = departamentoConMunicipio('Antioquia', 'Medellín');

        $this->post('/proveedor/catalogos/ciudades', [
            'departamento_id' => $departamento->id,
            'nombre' => 'Envigado',
            'codigo_dane' => '526',
        ])->assertSessionHasErrors('codigo_dane');
    });

    it('desactiva un municipio sin borrarlo', function () {
        ownerEnElPanel();

        [, $ciudad] = departamentoConMunicipio('Antioquia', 'Medellín');

        $this->post("/proveedor/catalogos/ciudades/{$ciudad->id}/estado")
            ->assertRedirect();

        expect($ciudad->fresh()->activo)->toBeFalse()
            ->and($ciudad->fresh()->trashed())->toBeFalse();
    });

    it('enseña los municipios de un departamento a la vez', function () {
        ownerEnElPanel();

        [$antioquia] = departamentoConMunicipio('Antioquia', 'Medellín');
        departamentoConMunicipio('Bolívar', 'Cartagena');

        $this->get('/proveedor/catalogos?pestana=ciudades&departamento='.$antioquia->id)
            ->assertInertia(fn ($pagina) => $pagina
                ->has('ciudades.filas', 1)
                ->where('ciudades.filas.0.nombre', 'Medellín'));
    });
});

describe('el calendario de festivos', function () {
    it('agrega un festivo deduciendo su año de la fecha', function () {
        ownerEnElPanel();

        $this->post('/proveedor/catalogos/festivos', [
            'fecha' => '2027-07-20',
            'nombre' => 'Día de la Independencia',
        ])->assertRedirect();

        $festivo = Festivo::query()->where('fecha', '2027-07-20')->first();

        expect($festivo->anio)->toBe(2027)
            ->and(Festivo::esFestivo(Carbon::parse('2027-07-20')))->toBeTrue();
    });

    it('no deja cargar dos veces el mismo día', function () {
        ownerEnElPanel();

        Festivo::registrar(Carbon::parse('2027-07-20'), 'Día de la Independencia');

        $this->post('/proveedor/catalogos/festivos', [
            'fecha' => '2027-07-20',
            'nombre' => 'Otra cosa',
        ])->assertSessionHasErrors('fecha');
    });

    it('quita un festivo sin borrarlo de verdad', function () {
        ownerEnElPanel();

        $festivo = Festivo::registrar(Carbon::parse('2027-07-20'), 'Día de la Independencia');

        $this->delete("/proveedor/catalogos/festivos/{$festivo->id}")
            ->assertRedirect();

        expect(Festivo::esFestivo(Carbon::parse('2027-07-20')))->toBeFalse()
            ->and(Festivo::withTrashed()->find($festivo->id))->not->toBeNull();
    });

    it('revive el festivo quitado cuando se vuelve a cargar esa fecha', function () {
        ownerEnElPanel();

        $festivo = Festivo::registrar(Carbon::parse('2027-07-20'), 'Día de la Independencia');
        $festivo->delete();

        $this->post('/proveedor/catalogos/festivos', [
            'fecha' => '2027-07-20',
            'nombre' => 'Día de la Independencia',
        ])->assertSessionHasNoErrors();

        expect(Festivo::esFestivo(Carbon::parse('2027-07-20')))->toBeTrue()
            // La misma fila, no una segunda: `fecha` es única y sigue siéndolo.
            ->and(Festivo::withTrashed()->where('fecha', '2027-07-20')->count())->toBe(1);
    });

    it('marca los festivos que caen en sábado', function () {
        ownerEnElPanel();

        // 2 de enero de 2027 es sábado.
        Festivo::registrar(Carbon::parse('2027-01-02'), 'Sábado de prueba');

        $filas = CatalogosDelSistema::festivos(2027);

        expect($filas[0]['enSabado'])->toBeTrue();
    });

    it('avisa desde octubre si el año siguiente no está cargado', function () {
        Carbon::setTestNow(Carbon::parse('2027-10-01'));

        Festivo::registrar(Carbon::parse('2027-07-20'), 'Día de la Independencia');

        expect(CatalogosDelSistema::avisoDeFestivos())
            ->toBe([
                'tono' => 'advertencia',
                'texto' => CatalogosDelSistema::avisoDelAnioSiguiente(2028),
            ]);
    });

    it('no avisa antes de octubre, que sería ruido', function () {
        Carbon::setTestNow(Carbon::parse('2027-09-30'));

        Festivo::registrar(Carbon::parse('2027-07-20'), 'Día de la Independencia');

        expect(CatalogosDelSistema::avisoDeFestivos())->toBeNull();
    });

    it('avisa en cualquier mes si falta el calendario del año en curso', function () {
        Carbon::setTestNow(Carbon::parse('2027-03-15'));

        expect(CatalogosDelSistema::avisoDeFestivos())
            ->toBe([
                'tono' => 'error',
                'texto' => CatalogosDelSistema::avisoDelAnioEnCurso(2027),
            ]);
    });

    it('ofrece el año siguiente al último cargado, que es el que falta', function () {
        Carbon::setTestNow(Carbon::parse('2027-11-02'));

        Festivo::registrar(Carbon::parse('2027-07-20'), 'Día de la Independencia');

        expect(CatalogosDelSistema::aniosOfrecidos())->toBe([2027, 2028]);
    });
});
