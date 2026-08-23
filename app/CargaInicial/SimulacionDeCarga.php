<?php

namespace App\CargaInicial;

use Illuminate\Support\Facades\Validator;

/**
 * El modo simulación del documento 2, 6.1: valida el archivo entero **sin
 * escribir nada** y devuelve los errores con su número de fila y su motivo.
 *
 * Es el paso que evita el momento de mayor riesgo del ciclo de vida de un
 * cliente. Un archivo con errores se devuelve, se corrige con la empresa y se
 * vuelve a pasar; hasta que salga limpio no hay ninguna escritura, y por eso
 * esta clase no conoce ni un solo `create()`, `save()` ni `insert()`.
 *
 * ## Las tres etapas de cada fila
 *
 * 1. **Leer cada celda** con `LecturaDeCelda`, que decide si lo escrito se puede
 *    interpretar: si «14/07/2025» es una fecha y si «Sura EPS» está en el
 *    catálogo. Lo que no se puede leer se anota y el campo queda marcado.
 * 2. **Completar lo que no sale de una sola celda**: el municipio que depende
 *    del departamento de al lado, el empleado que hay detrás de un documento.
 * 3. **Validar el conjunto** con las reglas del formulario del módulo. Los
 *    campos que ya fallaron al leerse no se vuelven a juzgar: una fecha
 *    ilegible produciría «no es una fecha» y después «es obligatoria», y dos
 *    mensajes para un solo error hacen que un informe de tres parezca de seis.
 *
 * Al final se revisa el archivo entero, que es donde se ven las condiciones que
 * ninguna fila puede comprobar sola.
 */
class SimulacionDeCarga
{
    public function __construct(private readonly LectorDeArchivo $lector) {}

    /**
     * @throws ArchivoIlegible
     */
    public function ejecutar(Plantilla $plantilla, string $ruta): ResultadoDeSimulacion
    {
        $archivo = $this->lector->leer($plantilla, $ruta);

        $catalogos = new CatalogosDeCarga;
        $planta = new PlantaDeLaEmpresa;
        $celdas = new LecturaDeCelda($catalogos);

        $filas = [];

        foreach ($archivo->filas as $original) {
            $fila = new FilaTraducida($original->numero);

            $this->traducir($plantilla, $original, $fila, $celdas);
            $plantilla->completar($fila, $catalogos, $planta);
            $this->validar($plantilla, $fila);

            $filas[] = $fila;
        }

        return new ResultadoDeSimulacion(
            $plantilla,
            $filas,
            $this->ordenados([
                ...$this->porColumnasAusentes($archivo),
                ...$this->deLasFilas($filas),
                ...$plantilla->revisarElConjunto($filas, $planta),
            ]),
        );
    }

    private function traducir(
        Plantilla $plantilla,
        FilaDelArchivo $original,
        FilaTraducida $fila,
        LecturaDeCelda $celdas,
    ): void {
        foreach ($plantilla->columnas() as $columna) {
            try {
                $fila->poner($columna->campo, $celdas->valor($columna, $original->celda($columna->titulo)));
            } catch (CeldaIlegible $ilegible) {
                $fila->error($columna->titulo, $ilegible->getMessage(), $columna->campo);
            }
        }
    }

    /**
     * Un solo motivo por campo. El segundo mensaje de una misma celda no añade
     * nada que ayude a corregirla y sí ensucia la cuenta de errores.
     */
    private function validar(Plantilla $plantilla, FilaTraducida $fila): void
    {
        $validador = Validator::make(
            $fila->valores(),
            $plantilla->reglas($fila),
            $plantilla->mensajes(),
            $plantilla->atributos(),
        );

        foreach ($validador->errors()->messages() as $campo => $motivos) {
            if ($fila->yaFallo($campo)) {
                continue;
            }

            $fila->error($plantilla->columnaDe($campo), $motivos[0], $campo);
        }
    }

    /**
     * La columna que no está se señala en la fila 1, que es donde vive el
     * título que falta. No impide leer el resto del archivo: quien recibe el
     * informe tiene que ver de una vez todo lo que hay que arreglar.
     *
     * @return list<ErrorDeCarga>
     */
    private function porColumnasAusentes(ArchivoLeido $archivo): array
    {
        return array_map(
            fn (string $titulo): ErrorDeCarga => new ErrorDeCarga(
                1,
                $titulo,
                "La hoja no trae la columna «{$titulo}». No agregue, quite ni reordene columnas, y no cambie los títulos de la fila 1.",
            ),
            $archivo->titulosQueFaltan,
        );
    }

    /**
     * @param  list<FilaTraducida>  $filas
     * @return list<ErrorDeCarga>
     */
    private function deLasFilas(array $filas): array
    {
        $errores = [];

        foreach ($filas as $fila) {
            foreach ($fila->errores() as $error) {
                $errores[] = $error;
            }
        }

        return $errores;
    }

    /**
     * @param  list<ErrorDeCarga>  $errores
     * @return list<ErrorDeCarga>
     */
    private function ordenados(array $errores): array
    {
        usort($errores, fn (ErrorDeCarga $uno, ErrorDeCarga $otro): int => $uno->fila <=> $otro->fila);

        return $errores;
    }
}
