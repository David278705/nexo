<?php

use Symfony\Component\Finder\Finder;

/**
 * Los once componentes base del documento 3, sección 3.4. Si mañana se agrega
 * uno, entra aquí: la lista es el contrato del sistema de diseño.
 *
 * @return list<string>
 */
function componentesBase(): array
{
    return [
        'TablaDatos',
        'BarraFiltros',
        'Modal',
        'CampoTexto',
        'CampoFecha',
        'Selector',
        'Etiqueta',
        'Aviso',
        'EstadoVacio',
        'TarjetaIndicador',
        'Notificacion',
    ];
}

/**
 * @return array<string, string> ruta relativa => contenido
 */
function archivosDelFrontend(): array
{
    $archivos = [];

    $encontrados = Finder::create()
        ->files()
        ->in(resource_path('js'))
        ->name(['*.vue', '*.js']);

    foreach ($encontrados as $archivo) {
        $archivos['resources/js/'.str_replace('\\', '/', $archivo->getRelativePathname())]
            = (string) $archivo->getContents();
    }

    return $archivos;
}

it('tiene los once componentes base del sistema de diseño', function () {
    foreach (componentesBase() as $componente) {
        expect(resource_path("js/Components/{$componente}.vue"))
            ->toBeReadableFile("Falta el componente {$componente}");
    }
});

it('muestra los once componentes en la página de muestra', function () {
    $muestra = file_get_contents(resource_path('js/Pages/SistemaDeDiseno.vue'));

    foreach (componentesBase() as $componente) {
        expect($muestra)->toContain("@/Components/{$componente}.vue");
    }
});

it('sirve la página de muestra fuera de producción', function () {
    $this->get('/sistema-de-diseno')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('SistemaDeDiseno'));
});

it('define la paleta y la tipografía del documento 3 en un solo archivo', function () {
    $hoja = file_get_contents(resource_path('css/app.css'));

    // Los nueve colores de la tabla 3.1, con el valor exacto del documento.
    $paleta = [
        '--color-marca: #123a5c',
        '--color-accion: #1b6ec2',
        '--color-exito: #157347',
        '--color-advertencia: #b45309',
        '--color-error: #b02a37',
        '--color-texto: #111827',
        '--color-texto-secundario: #4b5563',
        '--color-borde: #e5e7eb',
        '--color-fondo: #f7f8fa',
    ];

    foreach ($paleta as $declaracion) {
        expect($hoja)->toContain($declaracion);
    }

    // Los seis tamaños de la tabla 3.2 y la familia Inter.
    foreach (['24px', '18px', '15px', '14px', '13px', '32px'] as $tamano) {
        expect($hoja)->toContain($tamano);
    }

    expect($hoja)->toContain("'Inter'")
        ->and($hoja)->toContain('--radius-caja: 6px')
        ->and($hoja)->toContain('--radius-modal: 8px');
});

it('no escribe ni un color literal fuera de la hoja de estilos', function () {
    // Un hexadecimal, un `rgb()`/`hsl()`, o una clase de la paleta que trae
    // Tailwind de fábrica. Cualquiera de las tres delata un color escrito a
    // mano en vez de leído del sistema de diseño.
    $literales = '/#[0-9a-fA-F]{3,8}\b'
        .'|\b(?:rgba?|hsla?)\('
        .'|\b(?:bg|text|border|ring|from|via|to|fill|stroke|outline|decoration|shadow|accent|caret|divide|placeholder)'
        .'-(?:white|black|slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)\b/';

    $culpables = [];

    foreach (archivosDelFrontend() as $ruta => $contenido) {
        if (preg_match($literales, $contenido, $coincidencia) === 1) {
            $culpables[$ruta] = $coincidencia[0];
        }
    }

    expect($culpables)->toBe([], 'Hay colores literales: '.json_encode($culpables));
});
