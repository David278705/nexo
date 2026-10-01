<?php

namespace Tests;

use App\Legal\DocumentosLegales;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sin documentos legales por defecto. Si las pruebas leyeran los de
        // `resources/legal/`, toda empresa de prueba tendría la aceptación
        // pendiente y `ExigirAceptacionLegal` mandaría cada petición a la
        // pantalla de aceptar. Las pruebas del flujo legal montan su propia
        // carpeta con `documentosLegalesEn()`.
        $this->app->instance(DocumentosLegales::class, new DocumentosLegales(__DIR__.'/sin-documentos-legales'));
    }
}
