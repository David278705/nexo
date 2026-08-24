<?php

use App\Auditoria\AccionAuditada;
use App\Autorizacion\Rol;
use App\Models\Auditoria;
use App\Models\NotaCliente;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Models\VisitaAlEntorno;
use App\Proveedor\AccesoAlEntorno;

/**
 * La bitácora del cliente y el acceso del proveedor a su entorno (documento 4,
 * 4.3 y 4.4).
 *
 * Son dos cosas en la misma pestaña y son lo contrario en visibilidad, que es
 * justo lo que aquí se comprueba:
 *
 * 1. **La bitácora es interna.** Lo que el equipo escribe sobre un cliente
 *    —«llamó molesto», «se le prometió revisarlo»— no puede llegarle a él por
 *    ningún camino, y el camino que había era la auditoría.
 * 2. **La visita es lo contrario: existe para que él la vea.** Entrar deja
 *    constancia en la auditoría **de esa empresa**, que es la que el cliente
 *    puede leer, con el motivo escrito y con el proveedor como autor.
 * 3. **Es de solo lectura por defecto**, y eso se comprueba en el servidor con
 *    una escritura de verdad, no mirando si hay un botón.
 * 4. **Activar la escritura es expreso y deja un registro aparte**: dos líneas
 *    de auditoría, no una.
 *
 * `ownerEnElPanel()` vive en `CrearEmpresaTest.php`, y `enEmpresa()` y
 * `usuarioDe()` en `PanelDeEmpresasTest.php` y `RolesYPermisosTest.php`; Pest
 * comparte las funciones de todos los archivos.
 */

/**
 * Una empresa cliente con un empleado dentro, para tener algo que mirar y algo
 * que intentar escribir.
 */
function empresaVisitable(string $nombre = 'Distribuidora El Progreso'): Tenant
{
    $empresa = Tenant::factory()->create(['nombre' => $nombre]);

    enEmpresa($empresa, fn () => Vinculacion::factory()->create());

    return $empresa;
}

/**
 * Entra al entorno de esa empresa, como lo hace el modal de la ficha.
 */
function entrarAlEntorno(Tenant $empresa, bool $conEscritura = false): void
{
    test()->post('/proveedor/empresas/'.$empresa->getKey().'/entorno', [
        'motivo' => 'Revisar el cálculo de vacaciones que reportó Ana María.',
        'escritura' => $conEscritura,
    ])->assertRedirect('/');
}

/**
 * Las líneas de auditoría de las visitas que esa empresa puede ver.
 *
 * Se leen como las lee el cliente: dentro del contexto de su empresa y por el
 * único camino que el entorno del cliente tiene para leer la auditoría.
 *
 * @return list<Auditoria>
 */
function visitasVisiblesPara(Tenant $empresa): array
{
    return enEmpresa($empresa, fn (): array => Auditoria::query()
        ->deLaEmpresaActual()
        ->where('modelo', VisitaAlEntorno::class)
        ->orderBy('id')
        ->get()
        ->all());
}

describe('la bitácora del cliente', function () {
    it('guarda la nota con su autor y su fecha, y la enseña en la ficha', function () {
        $owner = ownerEnElPanel();
        $empresa = empresaVisitable();

        $this->post('/proveedor/empresas/'.$empresa->getKey().'/notas', [
            'texto' => 'Llamó Ana María preguntando por el cálculo de una licencia. Quedó clara.',
        ])->assertRedirect('/proveedor/empresas/'.$empresa->getKey());

        $this->get('/proveedor/empresas/'.$empresa->getKey())
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('bitacora.notas', 1)
                ->where('bitacora.notas.0.autor', $owner->getAttribute('name'))
                ->where('bitacora.notas.0.texto', 'Llamó Ana María preguntando por el cálculo de una licencia. Quedó clara.')
                ->etc());
    });

    it('no le enseña al cliente en su auditoría lo que el equipo escribió sobre él', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        $this->post('/proveedor/empresas/'.$empresa->getKey().'/notas', [
            'texto' => 'Cliente difícil: discutió la factura de julio y amenazó con irse.',
        ]);

        // Ni por el modelo auditado ni por el texto suelto en ninguna línea.
        $lineas = Auditoria::query()->get();

        expect($lineas->where('modelo', NotaCliente::class))->toBeEmpty()
            ->and(json_encode($lineas->toArray()))->not->toContain('Cliente difícil');
    });

    it('quita una nota sin borrarla de la base de datos', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        $this->post('/proveedor/empresas/'.$empresa->getKey().'/notas', [
            'texto' => 'Nota guardada en la ficha equivocada.',
        ]);

        $nota = NotaCliente::query()->firstOrFail();

        $this->delete('/proveedor/empresas/'.$empresa->getKey().'/notas/'.$nota->getKey())
            ->assertRedirect('/proveedor/empresas/'.$empresa->getKey());

        expect(NotaCliente::query()->count())->toBe(0)
            ->and(NotaCliente::withTrashed()->count())->toBe(1);
    });

    it('no deja quitar desde una ficha la nota de otro cliente', function () {
        ownerEnElPanel();
        $unaEmpresa = empresaVisitable('Distribuidora El Progreso');
        $otraEmpresa = empresaVisitable('Textiles Aurora');

        $this->post('/proveedor/empresas/'.$otraEmpresa->getKey().'/notas', [
            'texto' => 'Lo que se habló con Textiles Aurora.',
        ]);

        $nota = NotaCliente::query()->firstOrFail();

        $this->delete('/proveedor/empresas/'.$unaEmpresa->getKey().'/notas/'.$nota->getKey())
            ->assertNotFound();

        expect(NotaCliente::query()->count())->toBe(1);
    });

    it('exige escribir la nota antes de guardarla', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        $this->post('/proveedor/empresas/'.$empresa->getKey().'/notas', ['texto' => ''])
            ->assertSessionHasErrors('texto');

        expect(NotaCliente::query()->count())->toBe(0);
    });
});

describe('el acceso al entorno del cliente', function () {
    it('deja constancia en la auditoría de esa empresa, que el propio cliente puede ver', function () {
        $owner = ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa);

        $visibles = visitasVisiblesPara($empresa);

        expect($visibles)->toHaveCount(1);

        $linea = $visibles[0];

        expect($linea->getAttribute('tenant_id'))->toBe($empresa->getKey())
            ->and($linea->getAttribute('user_id'))->toBe($owner->getKey())
            ->and($linea->accion)->toBe(AccionAuditada::Creado)
            ->and($linea->valores_nuevos['motivo'])
            ->toBe('Revisar el cálculo de vacaciones que reportó Ana María.');
    });

    it('no deja entrar sin motivo escrito', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        $this->post('/proveedor/empresas/'.$empresa->getKey().'/entorno', ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        expect(VisitaAlEntorno::query()->count())->toBe(0);
    });

    it('deja al proveedor ver el entorno de la empresa visitada', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa);

        $this->get('/empleados')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Empleados/Index')
                ->has('empleados.filas', 1)
                ->where('usuario.empresa', 'Distribuidora El Progreso')
                ->etc());
    });

    it('no le abre el entorno de ninguna otra empresa', function () {
        ownerEnElPanel();
        $visitada = empresaVisitable('Distribuidora El Progreso');
        $otra = empresaVisitable('Textiles Aurora');

        enEmpresa($otra, fn () => Vinculacion::factory()->count(2)->create());

        entrarAlEntorno($visitada);

        // Un solo empleado: el de la empresa visitada. Los tres de la otra no
        // existen para esta sesión, aunque quien mira sea el `owner`.
        $this->get('/empleados')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->has('empleados.filas', 1)->etc());
    });

    it('sin visita abierta, el proveedor sigue sin entrar al entorno de nadie', function () {
        ownerEnElPanel();
        empresaVisitable();

        $this->get('/empleados')->assertForbidden();
    });

    it('es de solo lectura: una escritura de verdad no pasa', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa);

        $vinculacion = enEmpresa($empresa, fn () => Vinculacion::query()->firstOrFail());

        $this->post('/vacaciones', [
            'vinculacion_id' => $vinculacion->getKey(),
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-05',
        ])->assertForbidden();

        expect(enEmpresa($empresa, fn () => $vinculacion->vacaciones()->count()))->toBe(0);
    });

    it('con la escritura activada de forma expresa, sí escribe', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa, conEscritura: true);

        $vinculacion = enEmpresa($empresa, fn () => Vinculacion::query()->firstOrFail());

        $this->post('/vacaciones', [
            'vinculacion_id' => $vinculacion->getKey(),
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-05',
        ])->assertRedirect();

        expect(enEmpresa($empresa, fn () => $vinculacion->vacaciones()->count()))->toBe(1);
    });

    it('la activación de la escritura deja un registro aparte', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa, conEscritura: true);

        $visibles = visitasVisiblesPara($empresa);

        // Dos hechos, dos líneas: entró, y activó la escritura. La segunda es
        // el «registro aparte» que pide el documento.
        expect($visibles)->toHaveCount(2)
            ->and($visibles[0]->accion)->toBe(AccionAuditada::Creado)
            ->and($visibles[1]->accion)->toBe(AccionAuditada::Editado)
            ->and($visibles[1]->valores_nuevos)->toHaveKey('escritura_activada_en');
    });

    it('mientras se está dentro, el panel del proveedor no se pide', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa);

        $this->get('/proveedor/empresas')
            ->assertRedirect('/')
            ->assertSessionHas('estado', AccesoAlEntorno::PANEL_BLOQUEADO);
    });

    it('salir cierra la visita, la deja registrada y devuelve el panel', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa);

        $this->post('/entorno/salir')
            ->assertRedirect('/proveedor/empresas/'.$empresa->getKey());

        $visita = VisitaAlEntorno::query()->firstOrFail();

        expect($visita->terminada_en)->not->toBeNull()
            ->and($visita->estaEnCurso())->toBeFalse();

        // Fuera de la visita vuelve a ser el `owner` de siempre: el panel se
        // pide y el entorno del cliente se cierra.
        $this->get('/proveedor/empresas')->assertOk();
        $this->get('/empleados')->assertForbidden();
    });

    it('la salida también queda en la auditoría de la empresa', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa);
        $this->post('/entorno/salir');

        $visibles = visitasVisiblesPara($empresa);

        expect($visibles)->toHaveCount(2)
            ->and($visibles[1]->valores_nuevos)->toHaveKey('terminada_en');
    });

    it('el historial de visitas se ve en la ficha de la empresa', function () {
        $owner = ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa);
        $this->post('/entorno/salir');

        $this->get('/proveedor/empresas/'.$empresa->getKey())
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('bitacora.visitas.filas', 1)
                ->where('bitacora.visitas.filas.0.quien', $owner->getAttribute('name'))
                ->where('bitacora.visitas.filas.0.modo', 'Consulta')
                ->etc());
    });

    it('un usuario de empresa no puede abrir ninguna visita', function () {
        $empresa = empresaVisitable();
        $superadmin = usuarioDe($empresa, Rol::Superadministrador);

        $this->actingAs($superadmin)
            ->post('/proveedor/empresas/'.$empresa->getKey().'/entorno', [
                'motivo' => 'Quiero entrar al entorno de otra empresa.',
            ])
            ->assertForbidden();

        expect(VisitaAlEntorno::query()->count())->toBe(0);
    });

    it('una visita cerrada no vuelve a servir de llave', function () {
        ownerEnElPanel();
        $empresa = empresaVisitable();

        entrarAlEntorno($empresa);

        // Se cierra por fuera de la sesión, como si el propio proveedor la
        // hubiera cerrado desde otra pantalla: la sesión sigue teniendo el
        // identificador, pero la fila ya no está en curso.
        VisitaAlEntorno::query()->firstOrFail()->update(['terminada_en' => now()]);

        $this->get('/empleados')->assertForbidden();
    });
});
