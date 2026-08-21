<?php

namespace App\Proveedor;

use App\Contratacion\OpcionesDeLista;

/**
 * El estado comercial de una empresa cliente (documento 4, 4.1): activa,
 * suspendida o en periodo de prueba.
 *
 * Existía ya como tres constantes de `Tenant`, que es de donde salen sus
 * valores. Se convierte en enumerado ahora porque el listado del panel necesita
 * de cada estado tres cosas más —cómo se llama en pantalla, de qué color se
 * pinta y si es un valor admisible en un filtro— y esas tres, repartidas por la
 * pantalla y el controlador, se separan a la primera.
 *
 * Ninguno de los tres se elige al crear la empresa: nace activa. La suspensión
 * la produce la mora (capítulo 6, F5-04) y el periodo de prueba es del ambiente
 * de demostración (capítulo 10, F5-03).
 */
enum EstadoDeEmpresa: string
{
    use OpcionesDeLista;

    case Activa = 'activa';

    case Suspendida = 'suspendida';

    case EnPrueba = 'en_prueba';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Suspendida => 'Suspendida',
            self::EnPrueba => 'En prueba',
        };
    }

    /**
     * Cuántas hay en este estado: «12 activas», «1 suspendida», «2 en prueba».
     *
     * Es el encabezado del listado en el prototipo, y el singular importa: «1
     * suspendidas» delata que la frase la armó una concatenación. Las tres
     * formas se escriben aquí porque ninguna se puede deducir de la otra —«en
     * prueba» no cambia— y porque una prueba tiene que poder leerlas.
     */
    public function contadas(int $cuantas): string
    {
        $palabra = match ($this) {
            self::Activa => $cuantas === 1 ? 'activa' : 'activas',
            self::Suspendida => $cuantas === 1 ? 'suspendida' : 'suspendidas',
            self::EnPrueba => 'en prueba',
        };

        return $cuantas.' '.$palabra;
    }

    public function tono(): string
    {
        return match ($this) {
            self::Activa => 'exito',
            self::Suspendida => 'error',
            self::EnPrueba => 'neutro',
        };
    }
}
