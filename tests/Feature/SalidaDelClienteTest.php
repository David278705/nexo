<?php

use App\Autenticacion\SegundoFactor;
use App\Models\AceptacionLegal;
use App\Models\Pago;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\PlantaDeMuestra;
use App\Proveedor\Salida\EliminacionDeLaEmpresa;
use App\Proveedor\Salida\ExportacionDeLaEmpresa;
use App\Proveedor\Salida\TablasDeLaEmpresa;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * La salida de un cliente (documento 6, A.4): entregarle toda su información y,
 * pasado el periodo de gracia, eliminarla para siempre.
 *
 * Las dos empresas llevan la planta de muestra entera, que toca casi todas las
 * tablas, para que la eliminación se pruebe contra las llaves foráneas reales y
 * no contra un caso de juguete.
 */
function ownerConElPanelAbierto(): User
{
    $owner = User::factory()->owner()->conSegundoFactor()->create();
    $codigo = app(SegundoFactor::class)->codigo((string) $owner->segundo_factor_secreto);

    test()->actingAs($owner)->post('/proveedor/verificacion', ['codigo' => $codigo]);

    return $owner;
}

function empresaConHistoria(string $nombre, string $nit): Tenant
{
    $empresa = Tenant::factory()->create(['nombre' => $nombre, 'nit' => $nit]);

    app(ContextoTenant::class)->paraTenant($empresa, function () {
        app(PlantaDeMuestra::class)->escribir();
        AceptacionLegal::factory()->create();
    });

    $suscripcion = Suscripcion::factory()->create(['tenant_id' => $empresa->id]);
    Pago::factory()->for($suscripcion, 'suscripcion')->create();

    return $empresa;
}

/**
 * @return array<string, int>
 */
function filasPorTabla(Tenant $empresa): array
{
    $conteo = [];

    foreach (TablasDeLaEmpresa::aEliminar() as $tabla) {
        $conteo[$tabla] = DB::table($tabla)->where('tenant_id', $empresa->id)->count();
    }

    return $conteo;
}

function listaParaEliminar(Tenant $empresa, int $diasDesdeLaEntrega = 31): Tenant
{
    $empresa->forceFill([
        'estado' => Tenant::ESTADO_SUSPENDIDA,
        'datos_exportados_en' => Carbon::now()->subDays($diasDesdeLaEntrega),
    ])->save();

    return $empresa->refresh();
}

beforeEach(function () {
    $this->seed(CatalogosSeeder::class);
    Storage::fake('local');

    $this->empresa = empresaConHistoria('Confecciones El Progreso S.A.S.', '900111222-3');
    $this->vecina = empresaConHistoria('Transportes La Vecina S.A.S.', '900444555-6');
});

it('clasifica toda tabla con tenant_id en exactamente una de las tres listas', function () {
    $conTenant = collect(DB::select(
        "select table_name from information_schema.columns where column_name = 'tenant_id' and table_schema = 'public'"
    ))->pluck('table_name')->sort()->values()->all();

    $clasificadas = TablasDeLaEmpresa::clasificadas();
    sort($clasificadas);

    expect($clasificadas)->toBe($conTenant)
        ->and(count($clasificadas))->toBe(count(array_unique($clasificadas)));
});

describe('la exportación', function () {
    it('entrega un CSV por tabla del cliente, el LEEME y deja la constancia de la entrega', function () {
        $ruta = app(ExportacionDeLaEmpresa::class)->generar($this->empresa);

        $zip = new ZipArchive;
        $zip->open($ruta);
        $archivos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $archivos[] = $zip->getNameIndex($i);
        }

        foreach (TablasDeLaEmpresa::DEL_CLIENTE as $tabla) {
            expect($archivos)->toContain("{$tabla}.csv");
        }

        expect($archivos)->toContain('LEEME.txt')
            ->and($zip->getFromName('LEEME.txt'))->toContain('900111222-3')
            ->and($this->empresa->refresh()->datos_exportados_en)->not->toBeNull();

        $zip->close();
        unlink($ruta);
    });

    it('no se lleva ni una fila de otra empresa', function () {
        $ruta = app(ExportacionDeLaEmpresa::class)->generar($this->empresa);
        $zip = new ZipArchive;
        $zip->open($ruta);

        $personas = (string) $zip->getFromName('personas.csv');
        $idsDeLaVecina = DB::table('personas')->where('tenant_id', $this->vecina->id)->pluck('id')->all();
        $filas = array_slice(preg_split('/\r?\n/', trim($personas)), 1);

        expect($filas)->toHaveCount(PlantaDeMuestra::TOTAL);

        foreach ($filas as $fila) {
            expect($idsDeLaVecina)->not->toContain((int) explode(';', $fila)[0]);
        }

        $zip->close();
        unlink($ruta);
    });

    it('no entrega contraseñas ni secretos de acceso', function () {
        $ruta = app(ExportacionDeLaEmpresa::class)->generar($this->empresa);
        $zip = new ZipArchive;
        $zip->open($ruta);

        $encabezado = strtok((string) $zip->getFromName('users.csv'), "\n");

        foreach (TablasDeLaEmpresa::COLUMNAS_SECRETAS['users'] as $secreta) {
            expect($encabezado)->not->toContain($secreta);
        }

        $zip->close();
        unlink($ruta);
    });

    it('la descarga el owner desde la ficha de la empresa', function () {
        ownerConElPanelAbierto();

        $respuesta = $this->get("/proveedor/empresas/{$this->empresa->id}/exportacion");

        $respuesta->assertOk()->assertDownload('informacion-900111222-3-'.now()->format('Y-m-d').'.zip');
    });
});

describe('la eliminación', function () {
    it('no elimina a un cliente con el servicio activo', function () {
        $this->empresa->forceFill(['datos_exportados_en' => Carbon::now()->subDays(60)])->save();

        expect(app(EliminacionDeLaEmpresa::class)->impedimento($this->empresa->refresh()))
            ->toContain('no está suspendido');
    });

    it('no elimina lo que nunca se entregó', function () {
        $this->empresa->forceFill(['estado' => Tenant::ESTADO_SUSPENDIDA])->save();

        expect(app(EliminacionDeLaEmpresa::class)->impedimento($this->empresa->refresh()))
            ->toContain('Nunca se le entregó su información');
    });

    it('no elimina antes de que pase el periodo de gracia', function () {
        $empresa = listaParaEliminar($this->empresa, diasDesdeLaEntrega: 10);

        expect(app(EliminacionDeLaEmpresa::class)->impedimento($empresa))
            ->toContain('el periodo de gracia de 30 días termina');
    });

    it('nunca elimina el ambiente de demostración', function () {
        $demo = Tenant::factory()->create(['es_ambiente_demo' => true]);

        expect(app(EliminacionDeLaEmpresa::class)->impedimento($demo))->toContain('ambiente de demostración');
    });

    it('borra todo lo del cliente, deja intacta a la vecina y conserva lo comercial del proveedor', function () {
        $empresa = listaParaEliminar($this->empresa);
        $antesDeLaVecina = filasPorTabla($this->vecina);

        app(EliminacionDeLaEmpresa::class)->eliminar($empresa);

        // Del cliente no queda nada, salvo la línea de auditoría que deja la
        // propia constancia de la eliminación en la empresa.
        $despues = filasPorTabla($empresa);
        expect(array_sum($despues) - $despues['auditoria'])->toBe(0)
            ->and($despues['auditoria'])->toBeLessThanOrEqual(1)
            ->and(DB::table('users')->where('tenant_id', $empresa->id)->count())->toBe(0);

        // La vecina, intacta tabla por tabla.
        expect(filasPorTabla($this->vecina))->toBe($antesDeLaVecina);

        // Lo del proveedor sigue.
        expect(Tenant::query()->find($empresa->id))->not->toBeNull()
            ->and(Suscripcion::query()->where('tenant_id', $empresa->id)->count())->toBe(1)
            ->and(Pago::query()->count())->toBe(2)
            ->and($empresa->refresh()->datos_eliminados_en)->not->toBeNull();
    });

    it('borra también el certificado de Cámara de Comercio del disco', function () {
        Storage::disk('local')->put('certificados-camara/certificado.pdf', 'pdf');
        $this->empresa->forceFill([
            'certificado_camara_ruta' => 'certificados-camara/certificado.pdf',
            'certificado_camara_nombre' => 'certificado.pdf',
        ])->save();
        $empresa = listaParaEliminar($this->empresa);

        app(EliminacionDeLaEmpresa::class)->eliminar($empresa);

        Storage::disk('local')->assertMissing('certificados-camara/certificado.pdf');
    });

    it('el comando no borra nada si el NIT de la confirmación no coincide', function () {
        listaParaEliminar($this->empresa);
        $antes = filasPorTabla($this->empresa);

        $this->artisan('empresa:eliminar', ['nit' => '900111222-3'])
            ->expectsQuestion('Para confirmar, escribe el NIT de la empresa', '900111222-4')
            ->expectsOutputToContain('El NIT no coincide')
            ->assertFailed();

        expect(filasPorTabla($this->empresa))->toBe($antes);
    });

    it('el comando elimina con la confirmación correcta e imprime la constancia', function () {
        listaParaEliminar($this->empresa);

        $this->artisan('empresa:eliminar', ['nit' => '900111222-3'])
            ->expectsQuestion('Para confirmar, escribe el NIT de la empresa', '900111222-3')
            ->expectsOutputToContain('CONSTANCIA DE ELIMINACIÓN DE DATOS')
            ->assertSuccessful();

        expect(DB::table('personas')->where('tenant_id', $this->empresa->id)->count())->toBe(0);
    });

    it('el comando explica por qué no puede eliminar, sin preguntar nada', function () {
        $this->artisan('empresa:eliminar', ['nit' => '900111222-3'])
            ->expectsOutputToContain('No se puede eliminar')
            ->assertFailed();
    });
});
