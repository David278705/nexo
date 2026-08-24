<?php

namespace App\CargaInicial;

use Illuminate\Support\Carbon;

/**
 * Una de las seis plantillas de `plantillas/`: qué columnas trae, dónde acaba
 * cada valor y qué tiene que cumplir una fila para poder cargarse.
 *
 * Toda plantilla responde cuatro preguntas, y conviene que sigan separadas:
 *
 * 1. **¿Cómo se lee cada celda?** Lo dicen sus `columnas()`, que atan el título
 *    escrito en el archivo con el campo del sistema y con la forma de leerlo.
 * 2. **¿Qué falta por resolver cuando la celda ya se leyó?** Lo hace
 *    `completar()`. Hay valores que no salen de una sola celda: el municipio
 *    depende del departamento de al lado, y el empleado de un histórico sale de
 *    un documento que hay que buscar en la planta ya cargada.
 * 3. **¿La fila es admisible?** Lo dicen `reglas()`, y **son las mismas del
 *    formulario del módulo**. Es la decisión de fondo de toda la carga: si la
 *    plantilla tuviera reglas propias, tarde o temprano admitiría por archivo lo
 *    que la pantalla rechaza, y el cliente acabaría con dos verdades.
 * 4. **¿Cómo se guarda?** Lo hace `escribir()`, y por el mismo motivo que las
 *    reglas: **escribe por donde escribe el módulo**, para que una fila cargada
 *    y una digitada acaben idénticas en la base de datos. Solo se llama desde
 *    `EjecucionDeCarga`, es decir, con la fila ya validada y dentro de la
 *    transacción única de la carga.
 *
 * `revisarElConjunto()` es la excepción: hay condiciones que ninguna fila puede
 * comprobar sola —un documento repetido dentro del mismo archivo, la coherencia
 * del último movimiento salarial con la planta— y que solo existen porque esto
 * es un archivo y no un formulario.
 */
abstract class Plantilla
{
    /**
     * El nombre corto con el que se pide esta plantilla por consola.
     */
    abstract public function clave(): string;

    /**
     * El nombre con el que la conoce el cliente, el del archivo de `plantillas/`.
     */
    abstract public function nombre(): string;

    /**
     * La hoja del libro donde están los datos.
     */
    abstract public function hoja(): string;

    /**
     * @return list<Columna>
     */
    abstract public function columnas(): array;

    /**
     * Las reglas que tiene que cumplir la fila, ya traducida y completada.
     *
     * @return array<string, mixed>
     */
    abstract public function reglas(FilaTraducida $fila): array;

    /**
     * Guarda una fila ya validada.
     *
     * Recibe la fecha de la carga porque hay un dato que ninguna plantilla trae
     * y que solo se sabe en este momento: el día al que se refieren los «días de
     * vacaciones disponibles hoy» de la plantilla de empleados.
     *
     * No abre transacción ni comprueba nada. Lo primero lo hace
     * `EjecucionDeCarga`, que envuelve el archivo entero —«o entra todo o no
     * entra nada»—, y lo segundo ya pasó: la simulación no dejó llegar hasta
     * aquí ninguna fila con error.
     *
     * @param  array<string, mixed>  $valores  una fila de `ResultadoDeSimulacion::valores()`
     */
    abstract public function escribir(array $valores, Carbon $fechaDeLaCarga): void;

    /**
     * Si esta plantilla necesita que la de empleados ya se haya procesado
     * (documento 2, 6.3).
     *
     * Lo son cinco de las seis. La excepción se declara al revés —sobrescribe
     * quien no depende— para que una plantilla nueva nazca dependiendo de la
     * planta, que es el lado seguro del olvido.
     */
    public function dependeDeLaPlanta(): bool
    {
        return true;
    }

    /**
     * La fecha de corte del saldo de vacaciones que deja esta carga, si deja
     * alguna.
     *
     * Solo la plantilla de empleados: es la única que trae saldos. Las demás no
     * mueven el punto de partida del cálculo.
     */
    public function fechaDeCorte(Carbon $fechaDeLaCarga): ?Carbon
    {
        return null;
    }

    /**
     * Lo que no sale de una sola celda: ubicaciones encadenadas, el empleado
     * que hay detrás de un documento, y lo que decida cada plantilla.
     */
    public function completar(FilaTraducida $fila, CatalogosDeCarga $catalogos, PlantaDeLaEmpresa $planta): void
    {
        //
    }

    /**
     * Las condiciones que solo se ven mirando el archivo entero.
     *
     * @param  list<FilaTraducida>  $filas
     * @return list<ErrorDeCarga>
     */
    public function revisarElConjunto(array $filas, PlantaDeLaEmpresa $planta): array
    {
        return [];
    }

    /**
     * Los nombres con los que una persona reconoce cada campo, para que el
     * motivo del error no hable en nombres de columna de base de datos.
     *
     * @return array<string, string>
     */
    public function atributos(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function mensajes(): array
    {
        return [];
    }

    /**
     * El título de la columna donde el cliente escribe un campo, para señalarle
     * el sitio exacto del archivo.
     *
     * Algunos campos no tienen columna propia porque se deducen —el empleado de
     * un histórico sale del documento—, y para esos `equivalencias()` dice qué
     * columna hay que mirar.
     */
    public function columnaDe(string $campo): ?string
    {
        foreach ($this->columnas() as $columna) {
            if ($columna->campo === $campo) {
                return $columna->titulo;
            }
        }

        return $this->equivalencias()[$campo] ?? null;
    }

    /**
     * Campos deducidos y la columna del archivo de la que salen.
     *
     * @return array<string, string>
     */
    protected function equivalencias(): array
    {
        return [];
    }
}
