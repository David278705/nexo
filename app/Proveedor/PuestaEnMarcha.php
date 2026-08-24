<?php

namespace App\Proveedor;

use App\CargaInicial\CatalogoDePlantillas;
use App\CargaInicial\HistorialDeCargas;
use App\CargaInicial\Plantilla;
use App\CargaInicial\Plantillas\PlantillaDeEmpleados;
use App\Models\Tenant;

/**
 * La pantalla de puesta en marcha del panel: el asistente de carga inicial y el
 * historial de lo ya cargado (documento 4, capítulo 5, y documento 3, 5.8).
 *
 * Aquí no se carga nada ni se valida nada. Esto arma lo que la pantalla enseña,
 * que son tres cosas y conviene que sigan separadas:
 *
 * 1. **A quién y con qué**: las empresas y las seis plantillas, en el orden de
 *    carga del documento 2, 6.3.
 * 2. **Cómo va esa empresa**: qué plantillas ya entraron, con su fecha, sus
 *    filas y quién las cargó. Es lo que evita el error más caro de la reunión de
 *    puesta en marcha —volver a pasar la plantilla de empleados y duplicar la
 *    planta entera— y es el «resumen final» del paso 5 del documento 3, 5.8.
 * 3. **El historial de todas las empresas**, que es lo que pide el capítulo 5
 *    del documento 4.
 *
 * El informe de la simulación no se arma aquí: llega de la petición anterior,
 * hecho por `ResultadoDeSimulacion::paraPantalla()`, y esta clase solo lo deja
 * pasar. Duplicar su forma para «adaptarla a la pantalla» sería tener dos
 * verdades de un mismo informe.
 */
final class PuestaEnMarcha
{
    /**
     * La nota del prototipo que explica por qué no puede quedar media carga.
     *
     * Está escrita en el servidor y no en el componente por lo mismo que las
     * demás frases del sistema: es lo que se promete, y una promesa que solo
     * vive en la interfaz no la puede comprobar ninguna prueba del servidor.
     */
    public const NOTA_DE_LA_TRANSACCION = 'La carga se ejecuta dentro de una transacción: o entra todo o no entra '
        .'nada. No quedan registros a medias de un intento anterior.';

    public const NOTA_DEL_ORDEN = 'Primero la plantilla de empleados y solo después los históricos, que se vinculan '
        .'por número de documento.';

    public function __construct(private readonly HistorialDeCargas $historial) {}

    /**
     * @param  array<string, mixed>|null  $informe  el de la simulación que se acaba de correr, si la hubo
     * @return array<string, mixed>
     */
    public function paraLaPantalla(?int $empresa, ?string $plantilla, ?array $informe): array
    {
        $elegida = $empresa === null ? null : Tenant::query()->find($empresa);

        return [
            'empresas' => $this->empresas(),
            'plantillas' => $this->plantillas(),
            'viendo' => [
                'empresa' => $elegida?->getKey(),
                'plantilla' => $plantilla ?? PlantillaDeEmpleados::CLAVE,
            ],
            // `estadoDeLaEmpresa` y no `estado`: `estado` es la prop compartida
            // con la que viaja el mensaje de una petición a la siguiente, y dos
            // props con el mismo nombre son una que se pierde.
            'estadoDeLaEmpresa' => $elegida instanceof Tenant ? $this->estadoDe($elegida) : null,
            'historial' => $this->historial->ultimas(),
            'informe' => $informe,
            'notaDeLaTransaccion' => self::NOTA_DE_LA_TRANSACCION,
            'notaDelOrden' => self::NOTA_DEL_ORDEN,
        ];
    }

    /**
     * @return list<array{valor: int, texto: string}>
     */
    private function empresas(): array
    {
        return Tenant::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (Tenant $empresa): array => [
                'valor' => (int) $empresa->getKey(),
                'texto' => (string) $empresa->getAttribute('nombre'),
            ])
            ->all();
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    private function plantillas(): array
    {
        return (new CatalogoDePlantillas)->paraElegir();
    }

    /**
     * Cómo va la puesta en marcha de esa empresa: qué plantillas entraron ya y
     * cuáles faltan.
     *
     * `obligatoria` es solo la de empleados. Es la única que toda empresa debe
     * entregar (documento 1, 13.2) y las otras cinco son opcionales de verdad:
     * la empresa que no tiene ningún histórico registrado arranca solo con ella
     * y con los saldos, que es el «escenario mínimo» del 13.4.
     *
     * @return array{empresa: string, plantaCargada: bool, plantillas: list<array{clave: string, nombre: string, obligatoria: bool, cargada: array{fecha: string, filas: int, quien: string}|null}>}
     */
    private function estadoDe(Tenant $empresa): array
    {
        $cargadas = $this->historial->porPlantilla($empresa);

        return [
            'empresa' => (string) $empresa->getAttribute('nombre'),
            'plantaCargada' => isset($cargadas[PlantillaDeEmpleados::CLAVE]),
            'plantillas' => array_map(
                fn (Plantilla $plantilla): array => [
                    'clave' => $plantilla->clave(),
                    'nombre' => $plantilla->nombre(),
                    'obligatoria' => $plantilla->clave() === PlantillaDeEmpleados::CLAVE,
                    'cargada' => $cargadas[$plantilla->clave()] ?? null,
                ],
                (new CatalogoDePlantillas)->todas(),
            ),
        ];
    }
}
