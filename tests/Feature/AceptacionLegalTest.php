<?php

use App\Autorizacion\Rol;
use App\Legal\AceptacionDeLaEmpresa;
use App\Legal\DocumentosLegales;
use App\Models\AceptacionLegal;
use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Str;

/**
 * Los documentos legales y su aceptación a nombre de la empresa.
 *
 * `Tests\TestCase` deja por defecto un catálogo vacío, para que el resto de la
 * batería no choque con la pantalla de aceptar. Aquí cada prueba monta el suyo.
 */
function documentoLegal(string $titulo, string $version, bool $exige, string $cuerpo = 'El texto del documento.', string $estado = 'vigente'): string
{
    $exigeTexto = $exige ? 'si' : 'no';

    return "---\ntitulo: {$titulo}\nversion: {$version}\nestado: {$estado}\nexige_aceptacion: {$exigeTexto}\n---\n\n{$cuerpo}\n";
}

/**
 * @param  array<string, string>  $documentos  clave => contenido del archivo
 */
function documentosLegalesEn(array $documentos): void
{
    $carpeta = sys_get_temp_dir().'/nexo-legal-'.Str::random(10);
    mkdir($carpeta);

    foreach ($documentos as $clave => $contenido) {
        file_put_contents("{$carpeta}/{$clave}.md", $contenido);
    }

    app()->instance(DocumentosLegales::class, new DocumentosLegales($carpeta));
}

function losDocumentosDeSiempre(string $versionDeLosTerminos = '1'): void
{
    documentosLegalesEn([
        'terminos' => documentoLegal('Términos y condiciones del servicio', $versionDeLosTerminos, true),
        'acuerdo-de-tratamiento' => documentoLegal('Acuerdo de tratamiento de datos personales', '1', true),
        'autorizacion-empleados' => documentoLegal('Plantilla de autorización para empleados', '1', false),
    ]);
}

beforeEach(function () {
    losDocumentosDeSiempre();

    $this->empresa = Tenant::factory()->create(['nombre' => 'Confecciones El Progreso S.A.S.']);
    $this->superadmin = User::factory()->create([
        'tenant_id' => $this->empresa->id,
        'rol' => Rol::Superadministrador,
        'name' => 'Angela Lozano',
    ]);
});

describe('el bloqueo', function () {
    it('manda a aceptar al superadministrador de una empresa que no ha aceptado', function () {
        $this->actingAs($this->superadmin)->get('/')->assertRedirect('/aceptacion');
    });

    it('bloquea también a los demás usuarios de la empresa, no solo al superadministrador', function () {
        $administrador = User::factory()->create(['tenant_id' => $this->empresa->id, 'rol' => Rol::Administrador]);

        $this->actingAs($administrador)->get('/empleados')->assertRedirect('/aceptacion');
    });

    it('no bloquea el ambiente de demostración, que solo tiene datos ficticios', function () {
        $demo = Tenant::factory()->create(['es_ambiente_demo' => true]);
        $usuario = User::factory()->create(['tenant_id' => $demo->id, 'rol' => Rol::Superadministrador]);

        $this->actingAs($usuario)->get('/')->assertOk();
    });

    it('deja leer los documentos aunque estén pendientes', function () {
        $this->actingAs($this->superadmin)->get('/legal/terminos')->assertOk();
    });
});

describe('aceptar', function () {
    it('enseña los documentos pendientes, sin las plantillas que no se aceptan', function () {
        $this->actingAs($this->superadmin)
            ->get('/aceptacion')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Legal/Aceptacion')
                ->where('puedeAceptar', true)
                ->where('empresa', 'Confecciones El Progreso S.A.S.')
                ->has('documentos', 2)
            );
    });

    it('no acepta sin marcar la casilla', function () {
        $this->actingAs($this->superadmin)->post('/aceptacion', [])->assertSessionHasErrors('acepto');

        expect(AceptacionLegal::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('deja constancia de cada documento con quién, qué versión y desde dónde, y deja pasar', function () {
        $this->actingAs($this->superadmin)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post('/aceptacion', ['acepto' => '1'])
            ->assertRedirect('/');

        $constancias = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AceptacionLegal::query()->orderBy('documento')->get());

        expect($constancias)->toHaveCount(2)
            ->and($constancias->pluck('documento')->all())->toBe(['acuerdo-de-tratamiento', 'terminos'])
            ->and($constancias->pluck('version')->unique()->all())->toBe(['1'])
            ->and($constancias->pluck('user_id')->unique()->all())->toBe([$this->superadmin->id])
            ->and($constancias->first()->ip)->toBe('203.0.113.9');

        $this->actingAs($this->superadmin)->get('/')->assertOk();
    });

    it('guarda en la constancia el nombre y el correo de quien aceptó, para que sobreviva al usuario', function () {
        $this->actingAs($this->superadmin)->post('/aceptacion', ['acepto' => '1']);

        $constancia = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AceptacionLegal::query()->first());

        expect($constancia->aceptado_por_nombre)->toBe($this->superadmin->name)
            ->and($constancia->aceptado_por_correo)->toBe($this->superadmin->email);
    });

    it('vuelve a pedir aceptar cuando el documento cambia de versión, y solo ese', function () {
        $this->actingAs($this->superadmin)->post('/aceptacion', ['acepto' => '1']);

        losDocumentosDeSiempre(versionDeLosTerminos: '2');

        $this->actingAs($this->superadmin)->get('/')->assertRedirect('/aceptacion');
        $this->actingAs($this->superadmin)
            ->get('/aceptacion')
            ->assertInertia(fn ($pagina) => $pagina
                ->has('documentos', 1)
                ->where('documentos.0.clave', 'terminos')
                ->where('documentos.0.version', '2')
            );
    });

    it('aceptar dos veces no deja dos constancias', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, function () {
            AceptacionDeLaEmpresa::de($this->empresa)->aceptar($this->superadmin, '127.0.0.1', null);
            AceptacionDeLaEmpresa::de($this->empresa)->aceptar($this->superadmin, '127.0.0.1', null);

            expect(AceptacionLegal::query()->count())->toBe(2);
        });
    });

    it('a un administrador le dice quién debe aceptar y no lo deja aceptar', function () {
        $administrador = User::factory()->create(['tenant_id' => $this->empresa->id, 'rol' => Rol::Administrador]);

        $this->actingAs($administrador)
            ->get('/aceptacion')
            ->assertInertia(fn ($pagina) => $pagina
                ->where('puedeAceptar', false)
                ->where('superadministrador', 'Angela Lozano')
            );

        $this->actingAs($administrador)->post('/aceptacion', ['acepto' => '1'])->assertForbidden();
    });

    it('enseña en Configuración lo aceptado, con fecha y nombre, y la plantilla para empleados', function () {
        $this->actingAs($this->superadmin)->post('/aceptacion', ['acepto' => '1']);

        $this->actingAs($this->superadmin)
            ->get('/configuracion')
            ->assertInertia(fn ($pagina) => $pagina
                ->where('legal.documentos.0.aceptado', true)
                ->where('legal.documentos.0.aceptadoPor', 'Angela Lozano')
                ->has('legal.plantillas', 1)
            );
    });
});

describe('leer un documento', function () {
    it('cualquiera lo puede leer sin sesión', function () {
        $this->get('/legal/acuerdo-de-tratamiento')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Legal/Documento')
                ->where('titulo', 'Acuerdo de tratamiento de datos personales')
                ->where('esBorrador', false)
            );
    });

    it('responde 404 a un documento que no existe', function () {
        $this->get('/legal/no-existe')->assertNotFound();
    });

    it('le quita al texto cualquier HTML crudo', function () {
        documentosLegalesEn([
            'terminos' => documentoLegal('Términos', '1', true, "Texto.\n\n<script>alert('x')</script>"),
        ]);

        $this->get('/legal/terminos')->assertInertia(fn ($pagina) => $pagina
            ->where('html', fn (string $html) => ! str_contains($html, '<script'))
        );
    });

    it('marca el borrador como borrador', function () {
        documentosLegalesEn([
            'terminos' => documentoLegal('Términos', '1', true, 'Pendiente.', 'borrador'),
        ]);

        $this->get('/legal/terminos')->assertInertia(fn ($pagina) => $pagina->where('esBorrador', true));
    });
});

describe('los archivos de resources/legal', function () {
    it('se leen todos sin error, y exigen aceptar justo los dos que deben', function () {
        $documentos = DocumentosLegales::delProyecto();

        $claves = array_map(fn ($d) => $d->clave, $documentos->queExigenAceptacion());
        sort($claves);

        expect($documentos->todos())->toHaveCount(4)
            ->and($claves)->toBe(['acuerdo-de-tratamiento', 'terminos']);
    });

    it('falla en voz alta con un archivo sin encabezado, en vez de publicarlo a medias', function () {
        documentosLegalesEn(['terminos' => "Solo texto, sin encabezado.\n"]);

        expect(fn () => app(DocumentosLegales::class)->todos())
            ->toThrow(RuntimeException::class, 'no empieza con su encabezado');
    });
});
