<?php

namespace App\CargaInicial;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Abre el archivo de una plantilla y devuelve sus filas indexadas por el título
 * de cada columna.
 *
 * Aquí no se interpreta ni se valida nada: eso son `LecturaDeCelda` y las
 * reglas de cada plantilla. Lo único que este lector decide es **de dónde sale
 * cada valor**, y son tres decisiones:
 *
 * 1. **La columna se busca por su título, no por su posición.** Las seis
 *    plantillas le piden al cliente que no agregue, quite ni reordene columnas;
 *    buscarlas por posición convertiría una columna insertada por descuido en
 *    treinta valores corridos un puesto, cada uno guardado en el campo del
 *    vecino y ninguno marcado como error.
 * 2. **La hoja se busca por su nombre y, si no está, por su fila de títulos.**
 *    Ninguna plantilla prohíbe renombrar la hoja, así que un nombre cambiado no
 *    puede ser motivo para no leer un archivo que por dentro está bien.
 * 3. **Una fecha real de Excel se traduce aquí a `AAAA-MM-DD`.** Excel la guarda
 *    como un número de días desde 1900 y sin traducirlo el 14 de julio de 2025
 *    llegaría a la validación como «45852». Es lo que hace cierta la promesa de
 *    las instrucciones: «si Excel las convierte a otro formato no hay problema,
 *    mientras sean fechas reales y no texto».
 */
class LectorDeArchivo
{
    /**
     * @throws ArchivoIlegible
     */
    public function leer(Plantilla $plantilla, string $ruta): ArchivoLeido
    {
        $hoja = $this->hoja($this->libro($ruta), $plantilla);
        $titulos = $this->titulos($hoja);

        $columnas = [];
        $faltan = [];

        foreach ($plantilla->columnas() as $columna) {
            $letra = $titulos[TextoDeCelda::comparable($columna->titulo)] ?? null;

            if ($letra === null) {
                $faltan[] = $columna->titulo;

                continue;
            }

            $columnas[$columna->titulo] = $letra;
        }

        return new ArchivoLeido($this->filas($hoja, $columnas), $faltan);
    }

    /**
     * @throws ArchivoIlegible
     */
    private function libro(string $ruta): Spreadsheet
    {
        if (! is_file($ruta)) {
            throw new ArchivoIlegible("No se encontró el archivo «{$ruta}».");
        }

        try {
            $lector = IOFactory::createReaderForFile($ruta);

            // Con solo los datos se perderían los formatos, y con ellos la
            // única forma de distinguir una fecha real de un número suelto.
            $lector->setReadDataOnly(false);

            return $lector->load($ruta);
        } catch (Throwable $fallo) {
            throw new ArchivoIlegible(
                'El archivo no se pudo abrir como hoja de cálculo. Debe ser un archivo de Excel (.xlsx).',
                previous: $fallo,
            );
        }
    }

    /**
     * @throws ArchivoIlegible
     */
    private function hoja(Spreadsheet $libro, Plantilla $plantilla): Worksheet
    {
        $buscada = TextoDeCelda::comparable($plantilla->hoja());

        foreach ($libro->getAllSheets() as $hoja) {
            if (TextoDeCelda::comparable($hoja->getTitle()) === $buscada) {
                return $hoja;
            }
        }

        // La hoja renombrada se reconoce por su fila de títulos, que es lo que
        // las instrucciones sí piden no tocar. Se exige que reconozca **más de
        // la mitad** de las columnas y no solo la primera: las seis plantillas
        // empiezan por «Número de documento», así que con una columna en común
        // el archivo de vacaciones pasaría por el de incapacidades.
        foreach ($libro->getAllSheets() as $hoja) {
            if ($this->columnasReconocidas($hoja, $plantilla) * 2 > count($plantilla->columnas())) {
                return $hoja;
            }
        }

        throw new ArchivoIlegible(
            "El archivo no trae la hoja «{$plantilla->hoja()}» de la plantilla «{$plantilla->nombre()}». ".
            '¿Se subió el archivo de otra plantilla?'
        );
    }

    private function columnasReconocidas(Worksheet $hoja, Plantilla $plantilla): int
    {
        $titulos = $this->titulos($hoja);
        $reconocidas = 0;

        foreach ($plantilla->columnas() as $columna) {
            if (isset($titulos[TextoDeCelda::comparable($columna->titulo)])) {
                $reconocidas++;
            }
        }

        return $reconocidas;
    }

    /**
     * Los títulos de la fila 1, en su forma comparable, con la letra de su columna.
     *
     * @return array<string, string>
     */
    private function titulos(Worksheet $hoja): array
    {
        $titulos = [];

        foreach ($hoja->getRowIterator(1, 1) as $fila) {
            foreach ($fila->getCellIterator() as $celda) {
                $texto = TextoDeCelda::limpio($this->valor($celda));

                if ($texto !== null) {
                    $titulos[TextoDeCelda::comparable($texto)] = $celda->getColumn();
                }
            }
        }

        return $titulos;
    }

    /**
     * @param  array<string, string>  $columnas  título de la columna => letra
     * @return list<FilaDelArchivo>
     */
    private function filas(Worksheet $hoja, array $columnas): array
    {
        $filas = [];

        for ($numero = 2; $numero <= $hoja->getHighestDataRow(); $numero++) {
            $celdas = [];

            foreach ($columnas as $titulo => $letra) {
                $celdas[$titulo] = $this->valor($hoja->getCell($letra.$numero));
            }

            // Una fila en blanco no es un error: las plantillas vienen con
            // doscientas filas con formato y casi ninguna empresa las llena.
            if (array_filter($celdas, fn (mixed $celda): bool => TextoDeCelda::limpio($celda) !== null) !== []) {
                $filas[] = new FilaDelArchivo($numero, $celdas);
            }
        }

        return $filas;
    }

    /**
     * El contenido de una celda, con la fecha de Excel ya traducida y el texto
     * enriquecido ya aplanado.
     */
    private function valor(Cell $celda): mixed
    {
        $valor = $celda->getValue();

        if ($valor instanceof RichText) {
            return $valor->getPlainText();
        }

        if (is_numeric($valor) && Date::isDateTime($celda)) {
            return Date::excelToDateTimeObject((float) $valor)->format('Y-m-d');
        }

        return $valor;
    }
}
