<?php

use App\Autorizacion\SeccionDelPanel;
use App\CargaInicial\ArchivoIlegible;
use App\CargaInicial\CargaRechazada;
use App\CargaInicial\CatalogoDePlantillas;
use App\CargaInicial\Columna;
use App\CargaInicial\EjecucionDeCarga;
use App\CargaInicial\LectorDeArchivo;
use App\CargaInicial\Plantilla;
use App\CargaInicial\Plantillas\PlantillaDeEmpleados;
use App\CargaInicial\Plantillas\PlantillaDeIncapacidades;
use App\CargaInicial\Plantillas\PlantillaDeLicencias;
use App\CargaInicial\Plantillas\PlantillaDeMovimientos;
use App\CargaInicial\Plantillas\PlantillaDePerfilSociodemografico;
use App\CargaInicial\Plantillas\PlantillaDeVacaciones;
use App\CargaInicial\ResultadoDeLaCarga;
use App\CargaInicial\ResultadoDeSimulacion;
use App\CargaInicial\SimulacionDeCarga;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Licencias\TipoDeLicencia;
use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Ciudad;
use App\Models\Departamento;
use App\Models\Eps;
use App\Models\FondoPension;
use App\Models\Pais;
use App\Models\PeriodoContractual;
use App\Models\Persona;
use App\Models\RegistroDeCarga;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * El lector de plantillas y su modo simulación (F5-01, documento 2, capítulo 6).
 *
 * La prueba que da nombre a la tarea es «una plantilla con tres errores
 * deliberados los señala por fila y no escribe nada», y está en el primer
 * `describe`. Las demás cubren lo que la haría pasar por accidente: que las seis
 * plantillas de verdad se lean, que una celda mala no se cuente dos veces y que
 * las cinco plantillas que se vinculan por documento no acepten a quien no está
 * en la planta.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);

    Pais::query()->firstOrCreate(['codigo' => Pais::COLOMBIA], ['nombre' => 'Colombia', 'activo' => true]);

    $this->antioquia = Departamento::factory()->create(['nombre' => 'Antioquia']);
    $this->medellin = Ciudad::factory()->de($this->antioquia)->create(['nombre' => 'Medellín']);

    Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true]);
    FondoPension::query()->create(['nombre' => 'Protección', 'activo' => true]);
    Arl::query()->create(['nombre' => 'ARL Sura', 'activo' => true]);
    CajaCompensacion::query()->create(['nombre' => 'Comfama', 'activo' => true]);
    Banco::query()->create(['nombre' => 'Bancolombia', 'activo' => true]);
});

/**
 * Escribe un archivo de Excel con la estructura de una plantilla y las filas
 * que le pase la prueba, indexadas por el título de cada columna.
 *
 * Los archivos se arman aquí y no se guardan en el repositorio a propósito: un
 * `.xlsx` binario en `tests/` no se puede leer en una revisión, y el error
 * deliberado que contiene deja de estar a la vista de quien lee la prueba.
 *
 * @param  list<array<string, mixed>>  $filas
 * @param  list<string>|null  $titulos  para simular una plantilla con la estructura cambiada
 */
function archivoDeCarga(Plantilla $plantilla, array $filas, ?array $titulos = null, ?string $hoja = null): string
{
    $titulos ??= array_map(fn (Columna $columna): string => $columna->titulo, $plantilla->columnas());

    $libro = new Spreadsheet;
    $pagina = $libro->getActiveSheet();
    $pagina->setTitle($hoja ?? $plantilla->hoja());
    $pagina->fromArray($titulos, null, 'A1');

    foreach ($filas as $numero => $fila) {
        $pagina->fromArray(
            array_map(fn (string $titulo): mixed => $fila[$titulo] ?? null, $titulos),
            null,
            'A'.($numero + 2),
            // Sin la comparación estricta, un cero se confunde con una celda
            // vacía y la bonificación de quien no la tiene desaparecería.
            true,
        );
    }

    $ruta = tempnam(sys_get_temp_dir(), 'carga').'.xlsx';
    (new Xlsx($libro))->save($ruta);

    return $ruta;
}

/**
 * Una fila completa y correcta de la plantilla de empleados.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function filaDeEmpleado(array $cambios = []): array
{
    return [
        'Tipo de documento' => 'Cédula de ciudadanía',
        'Número de documento' => '43887201',
        'Primer nombre' => 'Carolina',
        'Segundo nombre' => 'Andrea',
        'Primer apellido' => 'Restrepo',
        'Segundo apellido' => 'Vélez',
        'Género' => 'Femenino',
        'Fecha de nacimiento' => '14/07/1991',
        'País de nacimiento' => 'Colombia',
        'Departamento de nacimiento' => 'Antioquia',
        'Ciudad de nacimiento' => 'Medellín',
        'Fecha de expedición del documento' => '05/08/2009',
        'Departamento de expedición del documento' => 'Antioquia',
        'Ciudad de expedición del documento' => 'Medellín',
        'Dirección de residencia' => 'Calle 33 # 74-20 Apto 502',
        'Departamento de residencia' => 'Antioquia',
        'Ciudad de residencia' => 'Medellín',
        'Celular' => '3007654321',
        'Correo electrónico' => 'carolina.restrepo@example.com',
        'EPS' => 'Sura EPS',
        'Fondo de pensión' => 'Protección',
        'ARL' => 'ARL Sura',
        'Nivel de riesgo' => 'I',
        'Caja de compensación' => 'Comfama',
        'Cargo' => 'Coordinadora de Operaciones',
        'Tipo de contrato' => 'Término indefinido',
        'Fecha de ingreso' => '03/02/2022',
        'Salario mensual' => 3200000,
        'Bonificación mensual' => 0,
        'Entidad bancaria' => 'Bancolombia',
        'Días de vacaciones disponibles hoy' => '17,25',
        ...$cambios,
    ];
}

/**
 * Corre la simulación dentro del contexto de la empresa de la prueba, que es
 * como la corre el comando: sin contexto no habría planta contra la que
 * comprobar un documento.
 */
function simular(Plantilla $plantilla, string $archivo): ResultadoDeSimulacion
{
    return app(ContextoTenant::class)->paraTenant(
        test()->empresa,
        fn () => app(SimulacionDeCarga::class)->ejecutar($plantilla, $archivo),
    );
}

/**
 * Un empleado ya cargado, para las plantillas de histórico.
 */
function empleadoDeLaPlanta(string $documento = '43887201', array $vinculacion = []): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant(
        test()->empresa,
        fn (): Vinculacion => Vinculacion::factory()
            ->de(Persona::factory()->conDocumento($documento)->create())
            ->create($vinculacion),
    );
}

describe('el caso obligatorio de F5-01', function () {
    it('señala los tres errores deliberados por su número de fila y no escribe nada', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Número de documento' => '1017234567']),
            filaDeEmpleado(['Número de documento' => '52998877', 'Fecha de ingreso' => 'catorce de julio']),
            filaDeEmpleado(['Número de documento' => '79112233', 'EPS' => 'Sura']),
            filaDeEmpleado(['Número de documento' => '1098765432', 'Días de vacaciones disponibles hoy' => null]),
        ], );

        $resultado = simular(new PlantillaDeEmpleados, $archivo);

        expect($resultado->filasLeidas())->toBe(4)
            ->and($resultado->filasConError())->toBe(3)
            ->and($resultado->filasCorrectas())->toBe(1);

        // Las filas del archivo, no el orden del registro: la 2 es la primera
        // con datos porque la 1 son los títulos.
        expect(array_map(fn ($error) => $error->fila, $resultado->errores))->toBe([3, 4, 5]);

        expect($resultado->motivosDe(3)[0])->toContain('no es una fecha');
        expect($resultado->motivosDe(4)[0])->toContain('no está en el catálogo');
        expect($resultado->motivosDe(5)[0])->toContain('días de vacaciones disponibles hoy son obligatorios');
    });

    it('no escribe ni una fila en la base de datos', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]);

        $resultado = simular(new PlantillaDeEmpleados, $archivo);

        expect($resultado->hayErrores())->toBeFalse()
            ->and(Persona::withoutGlobalScopes()->count())->toBe(0)
            ->and(Vinculacion::withoutGlobalScopes()->count())->toBe(0);
    });

    it('entrega la fila limpia ya traducida a campos del sistema', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]);

        $valores = simular(new PlantillaDeEmpleados, $archivo)->valores()[0];

        expect($valores['tipo_documento'])->toBe('cedula_ciudadania')
            ->and($valores['fecha_nacimiento'])->toBe('1991-07-14')
            ->and($valores['nacimiento']['pais'])->toBe('CO')
            ->and($valores['nacimiento']['ciudad'])->toBe(test()->medellin->id)
            ->and($valores['residencia']['ciudad'])->toBe(test()->medellin->id)
            ->and($valores['expedicion']['ciudad'])->toBe(test()->medellin->id)
            ->and($valores['tipo_riesgo'])->toBe('riesgo_i')
            ->and($valores['tipo_contrato'])->toBe('indefinido')
            ->and($valores['eps_id'])->toBe(Eps::query()->first()?->id)
            // El dinero sale como texto de precisión fija, nunca como coma
            // flotante.
            ->and($valores['salario'])->toBe('3200000.00')
            ->and($valores['saldo_inicial_vacaciones'])->toBe(17.25);
    });
});

describe('el lector', function () {
    it('encuentra las seis plantillas de plantillas/ con todas sus columnas', function () {
        $lector = app(LectorDeArchivo::class);

        foreach (app(CatalogoDePlantillas::class)->todas() as $plantilla) {
            $archivo = base_path('plantillas/'.nombreDelArchivo($plantilla->clave()));

            $leido = $lector->leer($plantilla, $archivo);

            expect($leido->titulosQueFaltan)->toBe([], "faltan columnas en «{$plantilla->nombre()}»")
                ->and($leido->filas)->not->toBeEmpty();
        }
    });

    /**
     * HD-10: el ejemplo de la fila 2 es lo primero que copia quien diligencia
     * el archivo, así que tiene que pasar las reglas de su propia plantilla.
     * Corre las seis con sus catálogos reales —los mismos nombres que
     * `beforeEach` siembra— para que un ejemplo desfasado, como «SURA» en vez
     * de «Sura EPS», se note aquí y no en la reunión de carga con el cliente.
     */
    it('acepta el ejemplo real de las seis plantillas de plantillas/', function () {
        $this->empresa->forceFill(['perfil_sociodemografico_habilitado' => true])->save();

        foreach (app(CatalogoDePlantillas::class)->todas() as $plantilla) {
            $archivo = base_path('plantillas/'.nombreDelArchivo($plantilla->clave()));

            if ($plantilla->clave() === PlantillaDeEmpleados::CLAVE) {
                // Se ejecuta de verdad, y no solo se simula: las cinco
                // plantillas siguientes se vinculan por documento con la
                // planta ya cargada, y sin escribirla no habría planta.
                try {
                    ejecutar($plantilla, $archivo);
                } catch (CargaRechazada $rechazo) {
                    $motivos = $rechazo->simulacion === null
                        ? $rechazo->getMessage()
                        : implode(' | ', array_map(fn ($error) => $error->comoTexto(), $rechazo->simulacion->errores));

                    expect(false)->toBeTrue("el ejemplo de «{$plantilla->nombre()}» no pasa sus propias reglas: {$motivos}");
                }

                continue;
            }

            $resultado = simular($plantilla, $archivo);

            expect($resultado->hayErrores())->toBeFalse(
                "el ejemplo de «{$plantilla->nombre()}» no pasa sus propias reglas: ".
                implode(' | ', array_map(fn ($error) => $error->comoTexto(), $resultado->errores))
            );
        }
    });

    it('traduce una fecha real de Excel a AAAA-MM-DD', function () {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Vacaciones');
        $hoja->fromArray(['Número de documento', 'Día inicial de vacaciones', 'Día final de vacaciones'], null, 'A1');
        $hoja->setCellValue('A2', '43887201');
        $hoja->setCellValue('B2', 45842);
        $hoja->getStyle('B2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $hoja->setCellValue('C2', '15/07/2025');

        $ruta = tempnam(sys_get_temp_dir(), 'carga').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        empleadoDeLaPlanta();

        $valores = simular(new PlantillaDeVacaciones, $ruta)->valores()[0];

        expect($valores['fecha_inicio'])->toBe('2025-07-04')
            ->and($valores['fecha_fin'])->toBe('2025-07-15');
    });

    it('lee la hoja aunque le hayan cambiado el nombre', function () {
        empleadoDeLaPlanta();

        $archivo = archivoDeCarga(new PlantillaDeVacaciones, [[
            'Número de documento' => '43887201',
            'Día inicial de vacaciones' => '04/07/2025',
            'Día final de vacaciones' => '15/07/2025',
        ]], hoja: 'Hoja1');

        expect(simular(new PlantillaDeVacaciones, $archivo)->hayErrores())->toBeFalse();
    });

    it('avisa cuando el archivo es el de otra plantilla', function () {
        $archivo = archivoDeCarga(new PlantillaDeVacaciones, []);

        expect(fn () => simular(new PlantillaDeIncapacidades, $archivo))
            ->toThrow(ArchivoIlegible::class, 'Incapacidades');
    });

    it('señala en la fila 1 la columna que falta y sigue leyendo el resto', function () {
        $titulos = array_map(
            fn (Columna $columna): string => $columna->titulo,
            (new PlantillaDeVacaciones)->columnas(),
        );

        $archivo = archivoDeCarga(new PlantillaDeVacaciones, [[
            'Número de documento' => '43887201',
            'Día inicial de vacaciones' => '04/07/2025',
        ]], titulos: array_slice($titulos, 0, 2));

        $resultado = simular(new PlantillaDeVacaciones, $archivo);

        expect($resultado->errores[0]->fila)->toBe(1)
            ->and($resultado->errores[0]->columna)->toBe('Día final de vacaciones')
            ->and($resultado->errores[0]->motivo)->toContain('no trae la columna')
            ->and($resultado->filasLeidas())->toBe(1);
    });

    it('salta las filas en blanco sin contarlas como error', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(),
            [],
            filaDeEmpleado(['Número de documento' => '1017234567']),
        ]);

        $resultado = simular(new PlantillaDeEmpleados, $archivo);

        expect($resultado->filasLeidas())->toBe(2)
            ->and($resultado->hayErrores())->toBeFalse();
    });
});

describe('la validación de la plantilla de empleados', function () {
    it('no reporta dos veces la misma celda', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Fecha de ingreso' => 'el año pasado']),
        ]);

        $resultado = simular(new PlantillaDeEmpleados, $archivo);

        expect($resultado->motivosDe(2))->toHaveCount(1);
    });

    it('rechaza la fecha de ingreso futura, que el formulario sí admite', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Fecha de ingreso' => now()->addMonth()->format('d/m/Y')]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('no puede ser futura');
    });

    it('rechaza el documento repetido dentro del mismo archivo', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(),
            filaDeEmpleado(),
        ]);

        $resultado = simular(new PlantillaDeEmpleados, $archivo);

        expect($resultado->filasConError())->toBe(1)
            ->and($resultado->motivosDe(3)[0])->toContain('ya está en la fila 2');
    });

    it('no acepta un municipio que no sea del departamento escrito', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Ciudad de residencia' => 'Cartagena']),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('no es un municipio de Antioquia');
    });

    /*
     * HD-07 (documento 6, D.7): el catálogo no se toca —Bogotá sigue sin ser
     * un municipio de Cundinamarca, porque el DANE la trata como su propio
     * departamento— pero el rechazo deja de ser el genérico «no es un
     * municipio» y explica qué escribir en su lugar.
     */
    it('explica que Bogotá no es un municipio de Cundinamarca y qué escribir en su lugar', function () {
        Departamento::factory()->create(['nombre' => 'Cundinamarca']);

        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Departamento de residencia' => 'Cundinamarca',
                'Ciudad de residencia' => 'Bogotá',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('Distrito Capital')
            ->toContain('«Bogotá D.C.»');
    });

    it('explica que Bogotá se busca como «Bogotá D.C.» cuando se escribe como si fuera el departamento', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Departamento de nacimiento' => 'Bogotá',
                'Ciudad de nacimiento' => 'Bogotá',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('«Bogotá D.C.»');
    });

    it('explica Bogotá también en el departamento de expedición del documento', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Departamento de expedición del documento' => 'Bogotá',
                'Ciudad de expedición del documento' => 'Bogotá',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('«Bogotá D.C.»');
    });

    it('no acepta para la expedición un municipio que no sea del departamento escrito', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Ciudad de expedición del documento' => 'Cartagena']),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('no es un municipio de Antioquia');
    });

    it('pide el departamento y la ciudad de expedición en el mismo orden que el asistente manual', function () {
        $campos = array_map(
            fn (Columna $columna): string => $columna->campo,
            (new PlantillaDeEmpleados)->columnas(),
        );

        $posicion = array_search('fecha_expedicion_documento', $campos, true);

        expect(array_slice($campos, $posicion, 4))->toBe([
            'fecha_expedicion_documento',
            'expedicion.pais',
            'expedicion.departamento',
            'expedicion.ciudad',
        ]);
    });

    it('deja el departamento y la ciudad del exterior como texto libre', function () {
        Pais::query()->create(['codigo' => 'VE', 'nombre' => 'Venezuela', 'activo' => true]);

        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'País de nacimiento' => 'Venezuela',
                'Departamento de nacimiento' => 'Zulia',
                'Ciudad de nacimiento' => 'Maracaibo',
            ]),
        ]);

        $valores = simular(new PlantillaDeEmpleados, $archivo)->valores()[0];

        expect($valores['nacimiento']['pais'])->toBe('VE')
            ->and($valores['nacimiento']['ciudad'])->toBe('Maracaibo');
    });

    it('asume Colombia para la expedición cuando la columna de país viene vacía', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]);

        $valores = simular(new PlantillaDeEmpleados, $archivo)->valores()[0];

        expect($valores['expedicion']['pais'])->toBe('CO')
            ->and($valores['expedicion']['ciudad'])->toBe(test()->medellin->id);
    });

    it('rechaza un país de expedición que no está en el catálogo', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['País de expedición del documento' => 'Wakanda']),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('no está en la lista de países del sistema');
    });

    it('deja el departamento y la ciudad de expedición del exterior en sus propias columnas, sin paréntesis', function () {
        Pais::query()->create(['codigo' => 'VE', 'nombre' => 'Venezuela', 'activo' => true]);

        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'País de expedición del documento' => 'Venezuela',
                'Departamento de expedición del documento' => 'Distrito Capital',
                'Ciudad de expedición del documento' => 'Caracas',
            ]),
        ]);

        $valores = simular(new PlantillaDeEmpleados, $archivo)->valores()[0];

        expect($valores['expedicion']['pais'])->toBe('VE')
            ->and($valores['expedicion']['ciudad'])->toBe('Caracas')
            ->and($valores['expedicion']['departamento'])->toBe('Distrito Capital');
    });

    it('un empleado cargado con expedición en el exterior queda igual que si se hubiera registrado a mano', function () {
        Pais::query()->create(['codigo' => 'VE', 'nombre' => 'Venezuela', 'activo' => true]);

        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'País de expedición del documento' => 'Venezuela',
                'Departamento de expedición del documento' => 'Distrito Capital',
                'Ciudad de expedición del documento' => 'Caracas',
            ]),
        ]);

        ejecutar(new PlantillaDeEmpleados, $archivo);

        $persona = app(ContextoTenant::class)->paraTenant(
            test()->empresa,
            fn (): Persona => Persona::query()->firstOrFail(),
        );

        expect($persona->pais_expedicion)->toBe('VE')
            ->and($persona->ciudad_expedicion_id)->toBeNull()
            ->and($persona->departamento_expedicion_exterior)->toBe('Distrito Capital')
            ->and($persona->ciudad_expedicion_exterior)->toBe('Caracas');
    });

    it('rechaza un salario escrito con punto de miles, que en Colombia es ambiguo', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Salario mensual' => '3.200.000']),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('sin puntos de miles');
    });

    it('admite el nivel de riesgo escrito como número romano', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Nivel de riesgo' => 'IV']),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->valores()[0]['tipo_riesgo'])->toBe('riesgo_iv');
    });

    it('exige la fecha de finalización al contrato a término fijo y la prohíbe al indefinido', function () {
        $sinFecha = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Tipo de contrato' => 'Término fijo']),
        ]);

        expect(simular(new PlantillaDeEmpleados, $sinFecha)->motivosDe(2)[0])
            ->toContain('necesita fecha de finalización');

        $conFechaDeMas = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Fecha de finalización del contrato' => '31/12/2026']),
        ]);

        expect(simular(new PlantillaDeEmpleados, $conFechaDeMas)->motivosDe(2)[0])
            ->toContain('Solo el contrato a término fijo');
    });

    it('carga la fecha estimada de una obra o labor y la prohíbe en las otras modalidades (PROD-23)', function () {
        $obra = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Tipo de contrato' => 'Obra o labor', 'Fecha de finalización estimada' => '31/12/2026']),
        ]);

        ejecutar(new PlantillaDeEmpleados, $obra);

        $vinculacion = app(ContextoTenant::class)->paraTenant(test()->empresa, fn () => Vinculacion::query()->sole());

        expect($vinculacion->fecha_fin_estimada->toDateString())->toBe('2026-12-31');

        $indefinidoConEstimada = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Número de documento' => '52998877', 'Fecha de finalización estimada' => '31/12/2026']),
        ]);

        expect(simular(new PlantillaDeEmpleados, $indefinidoConEstimada)->motivosDe(2)[0])
            ->toContain('Solo el contrato de obra o labor');
    });
});

describe('las plantillas que se vinculan por documento', function () {
    it('no acepta un histórico de quien no está en la plantilla de empleados', function () {
        $archivo = archivoDeCarga(new PlantillaDeVacaciones, [[
            'Número de documento' => '43887201',
            'Día inicial de vacaciones' => '04/07/2025',
            'Día final de vacaciones' => '15/07/2025',
        ]]);

        expect(simular(new PlantillaDeVacaciones, $archivo)->motivosDe(2)[0])
            ->toContain('No hay ningún empleado activo con el documento 43887201');
    });

    it('no alcanza al empleado de otra empresa', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Otra empresa']);

        app(ContextoTenant::class)->paraTenant($otra, fn () => Vinculacion::factory()
            ->de(Persona::factory()->conDocumento('43887201')->create())
            ->create());

        $archivo = archivoDeCarga(new PlantillaDeVacaciones, [[
            'Número de documento' => '43887201',
            'Día inicial de vacaciones' => '04/07/2025',
            'Día final de vacaciones' => '15/07/2025',
        ]]);

        expect(simular(new PlantillaDeVacaciones, $archivo)->motivosDe(2)[0])
            ->toContain('No hay ningún empleado');
    });

    it('acepta la incapacidad de un empleado activo y la traduce', function () {
        $vinculacion = empleadoDeLaPlanta();

        $archivo = archivoDeCarga(new PlantillaDeIncapacidades, [[
            'Número de documento' => '43887201',
            'Fecha de inicio' => '14/07/2025',
            'Fecha de terminación' => '16/07/2025',
            'Código de diagnóstico' => 'J06.9',
            '¿Es prórroga?' => 'No',
            'EPS que la expide' => 'Sura EPS',
            'Origen' => 'Común',
        ]]);

        $valores = simular(new PlantillaDeIncapacidades, $archivo)->valores()[0];

        expect($valores['vinculacion_id'])->toBe($vinculacion->id)
            ->and($valores['es_prorroga'])->toBe('no')
            ->and($valores['origen'])->toBe('comun');
    });

    /**
     * HD-10: la comparación ignora mayúsculas y tildes tanto en una columna de
     * catálogo (EPS, contra la tabla) como en una de lista (Origen, contra el
     * enumerado), así que un empleado no rebota por cómo lo escribió el cliente.
     */
    it('no distingue mayúsculas ni tildes al leer una columna de catálogo o de lista', function () {
        empleadoDeLaPlanta();

        $archivo = archivoDeCarga(new PlantillaDeIncapacidades, [[
            'Número de documento' => '43887201',
            'Fecha de inicio' => '14/07/2025',
            'Fecha de terminación' => '16/07/2025',
            'Código de diagnóstico' => 'J06.9',
            '¿Es prórroga?' => 'No',
            'EPS que la expide' => 'sura eps',
            'Origen' => 'comun',
        ]]);

        $resultado = simular(new PlantillaDeIncapacidades, $archivo);

        expect($resultado->hayErrores())->toBeFalse()
            ->and($resultado->valores()[0]['eps_id'])->toBe(Eps::query()->first()?->id)
            ->and($resultado->valores()[0]['origen'])->toBe('comun');
    });

    it('«suspension» sin tilde entra igual que la etiqueta «Suspensión» del tipo de novedad', function () {
        empleadoDeLaPlanta();

        $archivo = archivoDeCarga(new PlantillaDeLicencias, [[
            'Número de documento' => '43887201',
            'Tipo de novedad' => 'suspension',
            'Fecha inicial' => '16/03/2025',
            'Fecha final' => '15/04/2025',
        ]]);

        $resultado = simular(new PlantillaDeLicencias, $archivo);

        expect($resultado->hayErrores())->toBeFalse()
            ->and($resultado->valores()[0]['tipo'])->toBe(TipoDeLicencia::Suspension->value);
    });

    it('traduce los nombres que la plantilla del perfil escribe distinto del sistema', function () {
        empleadoDeLaPlanta();

        $archivo = archivoDeCarga(new PlantillaDePerfilSociodemografico, [[
            'Número de documento' => '43887201',
            'Estado civil' => 'Unión libre',
            'Nivel educativo alcanzado' => 'Bachillerato',
            'Tipo de vivienda' => 'Arrendada',
            'Estrato socioeconómico' => 3,
            'Medio de transporte al trabajo' => 'Moto',
            'Tiempo de desplazamiento' => 'De 30 min a 1 hora',
        ]]);

        $perfil = simular(new PlantillaDePerfilSociodemografico, $archivo)->valores()[0]['perfil'];

        expect($perfil['estado_civil'])->toBe('union_libre')
            ->and($perfil['nivel_educativo'])->toBe('bachiller')
            ->and($perfil['medio_transporte'])->toBe('motocicleta')
            ->and($perfil['tiempo_desplazamiento'])->toBe('entre_30_y_60_minutos');
    });

    it('rechaza el rango de desplazamiento que la plantilla ofrece y el sistema no tiene', function () {
        empleadoDeLaPlanta();

        $archivo = archivoDeCarga(new PlantillaDePerfilSociodemografico, [[
            'Número de documento' => '43887201',
            'Tiempo de desplazamiento' => 'Menos de 30 min',
        ]]);

        expect(simular(new PlantillaDePerfilSociodemografico, $archivo)->motivosDe(2)[0])
            ->toContain('Menos de 15 minutos');
    });

    it('avisa si el último movimiento no cuadra con lo que informó la plantilla de empleados', function () {
        empleadoDeLaPlanta('43887201', ['cargo' => 'Coordinadora de Operaciones', 'salario' => 3_200_000]);

        $archivo = archivoDeCarga(new PlantillaDeMovimientos, [[
            'Número de documento' => '43887201',
            'Tipo de movimiento' => 'Promoción',
            'Fecha de efectividad' => '01/08/2025',
            'Cargo anterior' => 'Analista Senior',
            'Cargo nuevo' => 'Coordinadora de Operaciones',
            'Salario anterior' => 2700000,
            'Salario nuevo' => 2900000,
        ]]);

        expect(simular(new PlantillaDeMovimientos, $archivo)->motivosDe(2)[0])
            ->toContain('tendría que ser el mismo que informó la plantilla de empleados');
    });

    it('acepta el histórico de movimientos que cuadra con la planta', function () {
        empleadoDeLaPlanta('43887201', ['cargo' => 'Coordinadora de Operaciones', 'salario' => 3_200_000]);

        $archivo = archivoDeCarga(new PlantillaDeMovimientos, [
            [
                'Número de documento' => '43887201',
                'Tipo de movimiento' => 'Aumento salarial',
                'Fecha de efectividad' => '01/03/2024',
                'Cargo anterior' => 'Analista Senior',
                'Cargo nuevo' => 'Analista Senior',
                'Salario anterior' => 2500000,
                'Salario nuevo' => 2700000,
            ],
            [
                'Número de documento' => '43887201',
                'Tipo de movimiento' => 'Promoción',
                'Fecha de efectividad' => '01/08/2025',
                'Cargo anterior' => 'Analista Senior',
                'Cargo nuevo' => 'Coordinadora de Operaciones',
                'Salario anterior' => 2700000,
                'Salario nuevo' => 3200000,
            ],
        ]);

        expect(simular(new PlantillaDeMovimientos, $archivo)->hayErrores())->toBeFalse();
    });
});

describe('el comando de simulación', function () {
    it('enumera los motivos y termina en error sin escribir nada', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Días de vacaciones disponibles hoy' => null]),
        ]);

        $this->artisan('carga:simular', [
            'empresa' => (string) $this->empresa->id,
            'plantilla' => 'empleados',
            'archivo' => $archivo,
        ])
            ->expectsOutputToContain('Filas leídas: 1')
            ->expectsOutputToContain('días de vacaciones disponibles hoy son obligatorios')
            ->assertExitCode(1);

        expect(Persona::withoutGlobalScopes()->count())->toBe(0);
    });

    it('confirma que el archivo está limpio', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]);

        $this->artisan('carga:simular', [
            'empresa' => (string) $this->empresa->id,
            'plantilla' => 'empleados',
            'archivo' => $archivo,
        ])
            ->expectsOutputToContain('El archivo está limpio')
            ->assertExitCode(0);
    });

    it('no acepta una plantilla que no existe', function () {
        $this->artisan('carga:simular', [
            'empresa' => (string) $this->empresa->id,
            'plantilla' => 'nomina',
            'archivo' => 'no importa',
        ])
            ->expectsOutputToContain('No existe la plantilla «nomina»')
            ->assertExitCode(1);
    });
});

describe('la ejecución de la carga', function () {
    it('deja el saldo en el que informó la plantilla más lo acumulado desde la fecha de corte', function () {
        // El «Hecho cuando» de F5-02, con el corte a fin de mes para que la
        // cuenta se lea de un vistazo: septiembre, octubre y noviembre son tres
        // meses cerrados enteros, sin ninguna fracción de por medio.
        $this->travelTo(Carbon::parse('2026-08-31'));

        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Días de vacaciones disponibles hoy' => '17,25']),
        ]);

        $resultado = ejecutar(new PlantillaDeEmpleados, $archivo);

        expect($resultado->filasCargadas())->toBe(1);

        $saldos = app(ContextoTenant::class)->paraTenant(test()->empresa, function (): array {
            $vinculacion = Vinculacion::query()->firstOrFail();

            return [
                'corte' => $vinculacion->fecha_corte_saldo?->toDateString(),
                'alCorte' => SaldoDeVacaciones::de($vinculacion)->al(Carbon::parse('2026-08-31')),
                'tresMesesDespues' => SaldoDeVacaciones::de($vinculacion)->al(Carbon::parse('2026-11-30')),
            ];
        });

        expect($saldos['corte'])->toBe('2026-08-31')
            // Al día del corte, exactamente lo que escribió la empresa: la
            // acumulación arranca al día siguiente.
            ->and($saldos['alCorte'])->toBe(17.25)
            // Y tres meses después, esos mismos días más 1,25 por mes cerrado.
            ->and($saldos['tresMesesDespues'])->toBe(21.0);
    });

    it('reconstruye tramo a tramo el historial cuando la plantilla trae varias prórrogas (HD-09)', function () {
        // El caso real de David: alguien que entró en 2018 con contrato a
        // término fijo y varias prórrogas, sin dónde ponerlas antes de esta
        // tarea. El periodo inicial dura seis meses —menos de un año, así que
        // gasta cupo corto (C.1)—, la primera prórroga es también de seis
        // meses y las dos siguientes de un año exacto cada una.
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Tipo de contrato' => 'Término fijo',
                'Fecha de ingreso' => '01/01/2018',
                'Fecha de finalización del contrato' => '31/12/2020',
                'Fecha de inicio de la prórroga 1' => '01/07/2018',
                'Fecha de fin de la prórroga 1' => '31/12/2018',
                'Fecha de inicio de la prórroga 2' => '01/01/2019',
                'Fecha de fin de la prórroga 2' => '31/12/2019',
                'Fecha de inicio de la prórroga 3' => '01/01/2020',
                'Fecha de fin de la prórroga 3' => '31/12/2020',
            ]),
        ]);

        ejecutar(new PlantillaDeEmpleados, $archivo);

        // Todo se lee dentro del contexto de la empresa: el historial se
        // recalcula con una consulta propia, y fuera del contexto el global
        // scope de `PerteneceATenant` no encontraría ningún tramo.
        $resultado = app(ContextoTenant::class)->paraTenant(test()->empresa, function (): array {
            $vinculacion = Vinculacion::query()->with('periodos')->firstOrFail();
            $periodos = $vinculacion->periodos->values();
            $historial = $vinculacion->historial();

            return [
                'periodos' => $periodos->map(fn (PeriodoContractual $periodo): array => [
                    'evento' => $periodo->evento->value,
                    'inicio' => $periodo->fecha_inicio->toDateString(),
                    'fin' => $periodo->fecha_fin->toDateString(),
                ])->all(),
                'fechaFinVigente' => $vinculacion->fecha_fin->toDateString(),
                'prorrogasAcumuladas' => $historial->prorrogasAcumuladas(),
                'contratoInicialEsInferiorAUnAnio' => $historial->contratoInicialEsInferiorAUnAnio(),
                'prorrogasCortas' => $historial->prorrogasCortas(),
                'prorrogasCortasRestantes' => $historial->prorrogasCortasRestantes(),
            ];
        });

        expect($resultado['periodos'])->toHaveCount(4)
            // El periodo inicial no dura hasta el vencimiento vigente: dura
            // hasta el día antes de que empiece la primera prórroga, que es
            // justo el dato que la plantilla anterior no podía reconstruir.
            ->and($resultado['periodos'][0])->toBe(['evento' => 'inicial', 'inicio' => '2018-01-01', 'fin' => '2018-06-30'])
            ->and($resultado['periodos'][1])->toBe(['evento' => 'prorroga_automatica', 'inicio' => '2018-07-01', 'fin' => '2018-12-31'])
            ->and($resultado['periodos'][2])->toBe(['evento' => 'prorroga_automatica', 'inicio' => '2019-01-01', 'fin' => '2019-12-31'])
            ->and($resultado['periodos'][3])->toBe(['evento' => 'prorroga_automatica', 'inicio' => '2020-01-01', 'fin' => '2020-12-31'])
            // Lo vigente de la vinculación termina donde termina la última
            // prórroga, como si «Renovar contrato» la hubiera registrado tres
            // veces desde la pantalla.
            ->and($resultado['fechaFinVigente'])->toBe('2020-12-31')
            // El conteo sale de las filas cargadas, no de un número aparte:
            // el periodo inicial dura menos de un año —así que la regla de
            // C.1 aplica—, pero para el cupo solo cuentan las prórrogas, y de
            // las tres solo la primera es corta; la segunda y la tercera
            // duran exactamente un año.
            ->and($resultado['prorrogasAcumuladas'])->toBe(3)
            ->and($resultado['contratoInicialEsInferiorAUnAnio'])->toBeTrue()
            ->and($resultado['prorrogasCortas'])->toBe(1)
            ->and($resultado['prorrogasCortasRestantes'])->toBe(2);
    });

    it('no permite cargar una prórroga sin haber cargado antes la anterior', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Tipo de contrato' => 'Término fijo',
                'Fecha de finalización del contrato' => '31/12/2026',
                'Fecha de inicio de la prórroga 2' => '01/01/2027',
                'Fecha de fin de la prórroga 2' => '31/12/2027',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('sin haber registrado antes la prórroga 1');
    });

    it('rechaza una prórroga corta desde la cuarta cuando el contrato inicial fue corto (PROD-33)', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Tipo de contrato' => 'Término fijo',
                'Fecha de ingreso' => '01/01/2024',
                'Fecha de finalización del contrato' => '31/12/2026',
                'Fecha de inicio de la prórroga 1' => '01/07/2024',
                'Fecha de fin de la prórroga 1' => '31/12/2024',
                'Fecha de inicio de la prórroga 2' => '01/01/2025',
                'Fecha de fin de la prórroga 2' => '30/06/2025',
                'Fecha de inicio de la prórroga 3' => '01/07/2025',
                'Fecha de fin de la prórroga 3' => '31/12/2025',
                'Fecha de inicio de la prórroga 4' => '01/01/2026',
                'Fecha de fin de la prórroga 4' => '30/06/2026',
                'Fecha de inicio de la prórroga 5' => '01/07/2026',
                'Fecha de fin de la prórroga 5' => '31/12/2026',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('La prórroga 4 dura menos de un año');
    });

    it('rechaza una fecha de finalización más allá del tope de cuatro años (PROD-33)', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Tipo de contrato' => 'Término fijo',
                'Fecha de ingreso' => '15/01/2026',
                'Fecha de finalización del contrato' => '30/06/2030',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('pasa del tope legal de 4 años')
            ->toContain('15/01/2030');
    });

    it('marca el contrato con origen «carga inicial» (PROD-33)', function () {
        ejecutar(new PlantillaDeEmpleados, archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]));

        $cargada = app(ContextoTenant::class)->paraTenant(
            test()->empresa,
            fn () => Vinculacion::query()->firstOrFail()->vieneDeLaCargaInicial(),
        );

        expect($cargada)->toBeTrue();
    });

    it('exige las dos fechas de cada prórroga cargada', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Tipo de contrato' => 'Término fijo',
                'Fecha de finalización del contrato' => '31/12/2026',
                'Fecha de inicio de la prórroga 1' => '01/01/2026',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('necesita las dos fechas');
    });

    it('no admite columnas de prórroga en un contrato que no es a término fijo', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Fecha de inicio de la prórroga 1' => '01/01/2026',
                'Fecha de fin de la prórroga 1' => '31/12/2026',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('solo para el contrato a término fijo');
    });

    it('la primera prórroga tiene que empezar después de la fecha de ingreso', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Tipo de contrato' => 'Término fijo',
                'Fecha de ingreso' => '01/01/2026',
                'Fecha de finalización del contrato' => '31/12/2026',
                'Fecha de inicio de la prórroga 1' => '01/01/2026',
                'Fecha de fin de la prórroga 1' => '31/12/2026',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('después de la fecha de ingreso');
    });

    it('cada prórroga tiene que empezar el día siguiente a la anterior, sin huecos', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Tipo de contrato' => 'Término fijo',
                'Fecha de finalización del contrato' => '31/12/2027',
                'Fecha de inicio de la prórroga 1' => '01/01/2026',
                'Fecha de fin de la prórroga 1' => '31/12/2026',
                // Debería empezar el 01/01/2027: deja un día sin cubrir.
                'Fecha de inicio de la prórroga 2' => '02/01/2027',
                'Fecha de fin de la prórroga 2' => '31/12/2027',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('el día siguiente a la finalización de la prórroga anterior');
    });

    it('la última prórroga cargada tiene que terminar igual que la fecha de finalización del contrato', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado([
                'Tipo de contrato' => 'Término fijo',
                'Fecha de finalización del contrato' => '31/12/2026',
                'Fecha de inicio de la prórroga 1' => '01/01/2026',
                'Fecha de fin de la prórroga 1' => '30/11/2026',
            ]),
        ]);

        expect(simular(new PlantillaDeEmpleados, $archivo)->motivosDe(2)[0])
            ->toContain('tiene que terminar el mismo día');
    });

    it('un empleado registrado a mano no lleva ninguna prórroga previa a la carga', function () {
        $vinculacion = app(ContextoTenant::class)->paraTenant(
            test()->empresa,
            fn (): Vinculacion => Vinculacion::factory()
                ->de(Persona::factory()->create())
                ->aTerminoFijo('2026-01-01', '2026-12-31')
                ->create(),
        );

        expect($vinculacion->prorrogas_previas_a_la_carga)->toBe(0);
    });

    it('no escribe ni una fila cuando el archivo todavía tiene un error', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Número de documento' => '1017234567']),
            filaDeEmpleado(['Número de documento' => '52998877', 'EPS' => 'Sura']),
        ]);

        expect(fn () => ejecutar(new PlantillaDeEmpleados, $archivo))
            ->toThrow(CargaRechazada::class, 'No se guardó nada');

        expect(Persona::withoutGlobalScopes()->count())->toBe(0)
            ->and(RegistroDeCarga::withoutGlobalScopes()->count())->toBe(0);
    });

    it('no deja media planta cargada cuando la escritura se cae a mitad del archivo', function () {
        // La transacción del paso 5 del documento 2, 6.1, probada por donde de
        // verdad se rompería: no con un archivo malo —eso lo para la simulación
        // antes de escribir nada— sino con una escritura que falla cuando ya
        // entraron filas.
        $plantilla = new class extends PlantillaDeEmpleados
        {
            public int $escritas = 0;

            public function escribir(array $valores, Carbon $fechaDeLaCarga): void
            {
                $this->escritas++;

                if ($this->escritas === 2) {
                    throw new RuntimeException('la base de datos se cayó a mitad del archivo');
                }

                parent::escribir($valores, $fechaDeLaCarga);
            }
        };

        $archivo = archivoDeCarga($plantilla, [
            filaDeEmpleado(['Número de documento' => '1017234567']),
            filaDeEmpleado(['Número de documento' => '52998877']),
            filaDeEmpleado(['Número de documento' => '79112233']),
        ]);

        expect(fn () => ejecutar($plantilla, $archivo))->toThrow(RuntimeException::class);

        expect(Persona::withoutGlobalScopes()->count())->toBe(0)
            ->and(Vinculacion::withoutGlobalScopes()->count())->toBe(0)
            ->and(RegistroDeCarga::withoutGlobalScopes()->count())->toBe(0);
    });

    it('deja constancia de qué archivo, cuántas filas, quién y cuándo', function () {
        $this->travelTo(Carbon::parse('2026-08-31'));

        $usuario = User::factory()->create(['name' => 'David']);

        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Número de documento' => '1017234567']),
            filaDeEmpleado(['Número de documento' => '52998877']),
        ]);

        ejecutar(new PlantillaDeEmpleados, $archivo, $usuario);

        $registro = app(ContextoTenant::class)->paraTenant(
            test()->empresa,
            fn (): RegistroDeCarga => RegistroDeCarga::query()->firstOrFail(),
        );

        expect($registro->plantilla)->toBe('empleados')
            ->and($registro->archivo)->toBe('1 - Empleados actuales (obligatoria).xlsx')
            ->and($registro->filas)->toBe(2)
            ->and($registro->quien())->toBe('David')
            // El corte del saldo queda escrito y no se deduce después de la
            // hora de creación, que es un instante en UTC.
            ->and($registro->fecha_corte_saldo?->toDateString())->toBe('2026-08-31');
    });

    it('dice «Consola» en la carga que no tiene a nadie autenticado detrás', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]);

        expect(ejecutar(new PlantillaDeEmpleados, $archivo)->registro->quien())->toBe('Consola');
    });

    it('no deja cargar un histórico antes que la plantilla de empleados', function () {
        // La planta existe, pero no entró por una carga: es exactamente el caso
        // que la comprobación contra `cargas_iniciales` distingue y el que una
        // comprobación contra la planta dejaría pasar.
        empleadoDeLaPlanta();

        $archivo = archivoDeCarga(new PlantillaDeVacaciones, [[
            'Número de documento' => '43887201',
            'Día inicial de vacaciones' => '04/07/2025',
            'Día final de vacaciones' => '15/07/2025',
        ]]);

        expect(fn () => ejecutar(new PlantillaDeVacaciones, $archivo))
            ->toThrow(CargaRechazada::class, 'no se ha procesado en esta empresa');

        expect(Vacacion::withoutGlobalScopes()->count())->toBe(0);
    });

    it('carga el histórico en cuanto la plantilla de empleados ya entró', function () {
        ejecutar(new PlantillaDeEmpleados, archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]));

        $archivo = archivoDeCarga(new PlantillaDeVacaciones, [[
            'Número de documento' => '43887201',
            'Día inicial de vacaciones' => '04/07/2025',
            'Día final de vacaciones' => '15/07/2025',
        ]]);

        expect(ejecutar(new PlantillaDeVacaciones, $archivo)->filasCargadas())->toBe(1);

        expect(Vacacion::withoutGlobalScopes()->count())->toBe(1);
    });

    it('no deja pasar dos veces la misma plantilla de empleados', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]);

        ejecutar(new PlantillaDeEmpleados, $archivo);

        // Volver a pasarla abriría una segunda vinculación activa a cada
        // persona, que es el duplicado que nadie ve hasta que el listado enseña
        // la planta entera repetida.
        expect(fn () => ejecutar(new PlantillaDeEmpleados, $archivo))
            ->toThrow(CargaRechazada::class, 'fila(s) con error');

        expect(Vinculacion::withoutGlobalScopes()->count())->toBe(1);
    });

    it('no vuelve a descontar del saldo las vacaciones anteriores al corte', function () {
        // El histórico de vacaciones es opcional (documento 1, 13.3) y cargarlo
        // no puede cambiarle el saldo a nadie: los días disponibles que informó
        // la empresa ya vienen con esos periodos descontados.
        $this->travelTo(Carbon::parse('2026-08-31'));

        ejecutar(new PlantillaDeEmpleados, archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Días de vacaciones disponibles hoy' => '17,25']),
        ]));

        $antes = saldoDeLaPlanta('43887201');

        ejecutar(new PlantillaDeVacaciones, archivoDeCarga(new PlantillaDeVacaciones, [[
            'Número de documento' => '43887201',
            'Día inicial de vacaciones' => '04/07/2025',
            'Día final de vacaciones' => '15/07/2025',
        ]]));

        expect(saldoDeLaPlanta('43887201'))->toBe($antes)
            ->and($antes)->toBe(17.25);
    });
});

describe('el comando de ejecución', function () {
    it('pide confirmación y no escribe nada si se le dice que no', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]);

        $this->artisan('carga:ejecutar', [
            'empresa' => (string) $this->empresa->id,
            'plantilla' => 'empleados',
            'archivo' => $archivo,
        ])
            ->expectsConfirmation('Esto escribe en la base de datos. ¿Ejecutar la carga?', 'no')
            ->expectsOutputToContain('No se hizo nada')
            ->assertExitCode(0);

        expect(Persona::withoutGlobalScopes()->count())->toBe(0);
    });

    it('carga el archivo limpio y dice cuántas filas entraron', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Número de documento' => '1017234567']),
            filaDeEmpleado(['Número de documento' => '52998877']),
        ]);

        $this->artisan('carga:ejecutar', [
            'empresa' => (string) $this->empresa->id,
            'plantilla' => 'empleados',
            'archivo' => $archivo,
        ])
            ->expectsConfirmation('Esto escribe en la base de datos. ¿Ejecutar la carga?', 'yes')
            ->expectsOutputToContain('Se cargaron 2 filas de «Empleados actuales».')
            ->assertExitCode(0);

        expect(Vinculacion::withoutGlobalScopes()->count())->toBe(2);
    });

    it('enumera los motivos y no escribe nada cuando el archivo tiene errores', function () {
        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Días de vacaciones disponibles hoy' => null]),
        ]);

        $this->artisan('carga:ejecutar', [
            'empresa' => (string) $this->empresa->id,
            'plantilla' => 'empleados',
            'archivo' => $archivo,
        ])
            ->expectsConfirmation('Esto escribe en la base de datos. ¿Ejecutar la carga?', 'yes')
            ->expectsOutputToContain('días de vacaciones disponibles hoy son obligatorios')
            ->assertExitCode(1);

        expect(Persona::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('la pantalla de puesta en marcha', function () {
    it('deja de ser una sección pendiente del panel', function () {
        expect(SeccionDelPanel::pendientes())->not->toContain(SeccionDelPanel::PuestaEnMarcha);
    });

    it('enseña las seis plantillas en el orden de carga y el historial vacío', function () {
        ownerEnElPanel();

        $this->get('/proveedor/puesta-en-marcha')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Proveedor/PuestaEnMarcha')
                ->has('plantillas', 6)
                ->where('plantillas.0.valor', 'empleados')
                ->where('viendo.plantilla', 'empleados')
                ->where('estadoDeLaEmpresa', null)
                ->has('historial', 0));
    });

    it('dice qué plantillas ya entraron en la empresa elegida', function () {
        ownerEnElPanel();

        ejecutar(new PlantillaDeEmpleados, archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]));

        $this->get('/proveedor/puesta-en-marcha?empresa='.$this->empresa->id)
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Proveedor/PuestaEnMarcha')
                ->where('estadoDeLaEmpresa.plantaCargada', true)
                ->where('estadoDeLaEmpresa.plantillas.0.cargada.filas', 1)
                ->where('estadoDeLaEmpresa.plantillas.0.obligatoria', true)
                ->where('estadoDeLaEmpresa.plantillas.1.cargada', null)
                ->has('historial', 1)
                ->where('historial.0.empresa', 'Comercializadora del Valle')
                ->where('historial.0.plantilla', 'Empleados actuales'));
    });

    it('valida el archivo subido sin escribir nada y devuelve el informe por fila', function () {
        ownerEnElPanel();

        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Número de documento' => '1017234567']),
            filaDeEmpleado(['Número de documento' => '52998877', 'EPS' => 'Sura']),
        ]);

        $this->post('/proveedor/puesta-en-marcha/simular', [
            'empresa' => $this->empresa->id,
            'plantilla' => 'empleados',
            'archivo' => plantillaSubida($archivo),
        ])->assertRedirect('/proveedor/puesta-en-marcha?empresa='.$this->empresa->id.'&plantilla=empleados');

        $this->get('/proveedor/puesta-en-marcha?empresa='.$this->empresa->id)
            ->assertInertia(fn ($pagina) => $pagina
                ->where('informe.filasLeidas', 2)
                ->where('informe.filasCorrectas', 1)
                ->where('informe.filasConError', 1)
                ->where('informe.errores.0.fila', 3)
                ->where('informe.errores.0.columna', 'EPS'));

        expect(Persona::withoutGlobalScopes()->count())->toBe(0);
    });

    it('carga de verdad al confirmar y lo dice con el número de filas', function () {
        ownerEnElPanel();

        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [filaDeEmpleado()]);

        $this->post('/proveedor/puesta-en-marcha/cargar', [
            'empresa' => $this->empresa->id,
            'plantilla' => 'empleados',
            'archivo' => plantillaSubida($archivo),
        ])->assertSessionHas('estado', 'Se cargaron 1 filas de «Empleados actuales».');

        expect(Vinculacion::withoutGlobalScopes()->count())->toBe(1);

        // La constancia guarda el nombre con el que llegó el archivo y a quién
        // se le atribuye la carga, que aquí sí tiene sesión detrás.
        $registro = app(ContextoTenant::class)->paraTenant(
            test()->empresa,
            fn (): RegistroDeCarga => RegistroDeCarga::query()->firstOrFail(),
        );

        expect($registro->archivo)->toBe('1 - Empleados actuales (obligatoria).xlsx')
            ->and($registro->quien())->not->toBe('Consola');
    });

    it('no escribe nada al confirmar un archivo con errores y devuelve el motivo', function () {
        ownerEnElPanel();

        $archivo = archivoDeCarga(new PlantillaDeEmpleados, [
            filaDeEmpleado(['Días de vacaciones disponibles hoy' => null]),
        ]);

        $this->post('/proveedor/puesta-en-marcha/cargar', [
            'empresa' => $this->empresa->id,
            'plantilla' => 'empleados',
            'archivo' => plantillaSubida($archivo),
        ])
            ->assertSessionHasErrors('archivo')
            ->assertSessionHas('informe');

        expect(Persona::withoutGlobalScopes()->count())->toBe(0);
    });

    it('no deja confirmar un histórico antes que la plantilla de empleados', function () {
        ownerEnElPanel();

        empleadoDeLaPlanta();

        $archivo = archivoDeCarga(new PlantillaDeVacaciones, [[
            'Número de documento' => '43887201',
            'Día inicial de vacaciones' => '04/07/2025',
            'Día final de vacaciones' => '15/07/2025',
        ]]);

        $this->post('/proveedor/puesta-en-marcha/cargar', [
            'empresa' => $this->empresa->id,
            'plantilla' => 'vacaciones',
            'archivo' => plantillaSubida($archivo, 'historico.xlsx'),
        ])->assertSessionHasErrors('archivo');

        expect(Vacacion::withoutGlobalScopes()->count())->toBe(0);
    });

    it('rechaza un archivo que no es de Excel sin llegar al lector', function () {
        ownerEnElPanel();

        $this->post('/proveedor/puesta-en-marcha/simular', [
            'empresa' => $this->empresa->id,
            'plantilla' => 'empleados',
            'archivo' => UploadedFile::fake()->create('planta.csv', 12),
        ])->assertSessionHasErrors('archivo');
    });

    it('no la abre nadie que no sea el owner', function () {
        $usuario = User::factory()->create(['tenant_id' => $this->empresa->id]);

        $this->actingAs($usuario)->get('/proveedor/puesta-en-marcha')->assertForbidden();

        $this->actingAs($usuario)->post('/proveedor/puesta-en-marcha/cargar', [
            'empresa' => $this->empresa->id,
            'plantilla' => 'empleados',
        ])->assertForbidden();
    });
});

/**
 * Ejecuta la carga dentro del contexto de la empresa de la prueba, que es como
 * la ejecutan el comando y la pantalla.
 */
function ejecutar(Plantilla $plantilla, string $archivo, ?User $usuario = null): ResultadoDeLaCarga
{
    return app(ContextoTenant::class)->paraTenant(
        test()->empresa,
        fn (): ResultadoDeLaCarga => app(EjecucionDeCarga::class)->ejecutar(
            $plantilla,
            $archivo,
            '1 - Empleados actuales (obligatoria).xlsx',
            $usuario,
        ),
    );
}

/**
 * El saldo de hoy de quien tenga ese documento en la planta.
 */
function saldoDeLaPlanta(string $documento): float
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, function () use ($documento): float {
        $vinculacion = Vinculacion::query()
            ->whereRelation('persona', 'numero_documento', $documento)
            ->firstOrFail();

        return SaldoDeVacaciones::de($vinculacion)->al(Carbon::now());
    });
}

/**
 * El archivo tal como llega de un formulario, sin copiarlo a ningún disco: el
 * Excel no se conserva (documento 1, 13.1).
 */
function plantillaSubida(string $ruta, string $nombre = '1 - Empleados actuales (obligatoria).xlsx'): UploadedFile
{
    return new UploadedFile($ruta, $nombre, null, null, true);
}

/**
 * El archivo de `plantillas/` que corresponde a cada plantilla del catálogo.
 *
 * Los nombres llevan número y paréntesis porque así se los entregan al cliente,
 * y esta correspondencia es lo único que ata el código a esos nombres.
 */
function nombreDelArchivo(string $clave): string
{
    return match ($clave) {
        'empleados' => '1 - Empleados actuales (obligatoria).xlsx',
        'perfil' => '2 - Perfil sociodemografico (opcional).xlsx',
        'incapacidades' => '3 - Historico de incapacidades (opcional).xlsx',
        'vacaciones' => '4 - Historico de vacaciones (opcional).xlsx',
        'licencias' => '5 - Historico de licencias y ausencias (opcional).xlsx',
        'movimientos' => '6 - Historico de aumentos y promociones (opcional).xlsx',
        default => throw new InvalidArgumentException("No hay archivo para «{$clave}»."),
    };
}
