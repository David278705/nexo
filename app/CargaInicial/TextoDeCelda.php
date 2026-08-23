<?php

namespace App\CargaInicial;

use Illuminate\Support\Str;

/**
 * Cómo se lee el texto que alguien escribió en una celda de Excel.
 *
 * Son dos operaciones distintas y conviene no confundirlas:
 *
 * - `limpio()` convierte la celda en el texto que el cliente quiso escribir:
 *   quita espacios de sobra, y traduce a texto lo que Excel devolvió como
 *   número o como booleano. Un documento guardado como número llega aquí como
 *   `43887201.0` y sin este paso se convertiría en `4.3887201E+7`, que es otro
 *   documento.
 * - `comparable()` produce la forma con la que se **compara** ese texto contra
 *   el nombre de una opción del sistema. No se guarda nunca: solo sirve para
 *   decidir si «UNIÓN LIBRE», «Unión Libre» y «union libre» son lo mismo, que
 *   es lo que son.
 *
 * La comparación ignora mayúsculas, tildes, espacios repetidos y el paréntesis
 * de género de las etiquetas del sistema —«Soltero(a)» y «Soltero» son la misma
 * opción—. **No hace nada más.** No corrige errores de escritura ni adivina
 * abreviaturas: si el cliente escribió «Sura» donde el catálogo dice «Sura EPS»,
 * eso es un error que el informe tiene que reportar, no algo que el sistema deba
 * resolver por su cuenta. La carga inicial es el momento de mayor riesgo del
 * ciclo de vida de un cliente (documento 2, capítulo 6) y adivinar ahí es
 * exactamente lo que no se puede hacer.
 */
final class TextoDeCelda
{
    /**
     * El texto de la celda, o nulo si está vacía.
     */
    public static function limpio(mixed $bruto): ?string
    {
        if ($bruto === null) {
            return null;
        }

        // Excel guarda una casilla de verificación como booleano. Se traduce al
        // «Sí» y «No» de las plantillas para que la lea el mismo decodificador.
        if (is_bool($bruto)) {
            return $bruto ? 'Sí' : 'No';
        }

        if (is_float($bruto)) {
            return self::numero($bruto);
        }

        if (is_int($bruto)) {
            return (string) $bruto;
        }

        // Lo que no sea texto ni número no tiene forma de leerse. El lector ya
        // convirtió a texto plano lo único que llega así: una celda con formato
        // enriquecido.
        if (! is_string($bruto)) {
            return null;
        }

        $texto = trim((string) preg_replace('/\s+/u', ' ', $bruto));

        return $texto === '' ? null : $texto;
    }

    /**
     * La forma con la que se compara un texto contra el nombre de una opción.
     */
    public static function comparable(string $texto): string
    {
        // «Soltero(a)» y «Posgraduado(a)» llevan el paréntesis en la etiqueta
        // del sistema y casi nadie lo escribe en una plantilla.
        $sinGenero = (string) preg_replace('/\s*\((?:a|o|a\/o)\)/iu', '', $texto);

        $plano = (string) preg_replace('/\s+/u', ' ', Str::ascii($sinGenero));

        return Str::lower(trim($plano));
    }

    /**
     * Un número de Excel escrito como lo escribiría una persona.
     *
     * Los enteros salen sin parte decimal, que es lo que hace que un documento
     * o un salario no lleguen a la validación con un `.0` pegado detrás.
     */
    private static function numero(float $valor): string
    {
        return $valor === floor($valor) && abs($valor) < 1e15
            ? number_format($valor, 0, '.', '')
            : (string) $valor;
    }
}
