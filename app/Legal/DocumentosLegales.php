<?php

namespace App\Legal;

use RuntimeException;

/**
 * Los documentos legales del servicio: los archivos de `resources/legal/`.
 *
 * **Un archivo por documento, y el archivo es la única fuente.** El texto, el
 * título, la versión y si exige aceptación viven juntos en el mismo archivo,
 * en un encabezado al estilo de un *front matter*:
 *
 *     ---
 *     titulo: Términos y condiciones del servicio
 *     version: 2
 *     estado: vigente
 *     exige_aceptacion: si
 *     ---
 *
 * Así, publicar una versión nueva del contrato es cambiar un archivo y subir un
 * número, sin tocar código ni base de datos, y agregar un documento nuevo es
 * agregar un archivo. El nombre del archivo, sin `.md`, es su clave y su
 * dirección pública: `terminos.md` se lee en `/legal/terminos`.
 *
 * **Un archivo mal escrito falla en voz alta**, en vez de publicarse a medias:
 * un contrato sin versión no se puede aceptar de manera que conste nada.
 */
final class DocumentosLegales
{
    private const CLAVE_VALIDA = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const ESTADOS = ['borrador' => true, 'vigente' => false];

    private const SI_O_NO = ['si' => true, 'no' => false];

    public function __construct(private readonly string $carpeta) {}

    public static function delProyecto(): self
    {
        return new self(resource_path('legal'));
    }

    /**
     * @return list<DocumentoLegal>
     */
    public function todos(): array
    {
        $archivos = glob($this->carpeta.'/*.md') ?: [];
        sort($archivos);

        return array_map(fn (string $archivo) => $this->leer($archivo), $archivos);
    }

    public function porClave(string $clave): ?DocumentoLegal
    {
        foreach ($this->todos() as $documento) {
            if ($documento->clave === $clave) {
                return $documento;
            }
        }

        return null;
    }

    /**
     * Los que una empresa tiene que aceptar antes de usar el sistema.
     *
     * @return list<DocumentoLegal>
     */
    public function queExigenAceptacion(): array
    {
        return array_values(array_filter($this->todos(), fn (DocumentoLegal $d) => $d->exigeAceptacion));
    }

    /**
     * @return list<DocumentoLegal>
     */
    public function borradores(): array
    {
        return array_values(array_filter($this->todos(), fn (DocumentoLegal $d) => $d->esBorrador));
    }

    private function leer(string $archivo): DocumentoLegal
    {
        $clave = basename($archivo, '.md');
        $texto = (string) file_get_contents($archivo);

        if (preg_match(self::CLAVE_VALIDA, $clave) !== 1) {
            throw new RuntimeException("El documento legal «{$clave}.md» tiene un nombre que no sirve de dirección: solo minúsculas, números y guiones.");
        }

        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $texto, $partes) !== 1) {
            throw new RuntimeException("El documento legal «{$clave}.md» no empieza con su encabezado entre líneas «---».");
        }

        $datos = [];

        foreach (preg_split('/\R/', trim($partes[1])) ?: [] as $linea) {
            [$nombre, $valor] = array_pad(explode(':', $linea, 2), 2, '');
            $datos[trim($nombre)] = trim($valor);
        }

        foreach (['titulo', 'version', 'estado', 'exige_aceptacion'] as $obligatorio) {
            if (($datos[$obligatorio] ?? '') === '') {
                throw new RuntimeException("Al documento legal «{$clave}.md» le falta «{$obligatorio}» en el encabezado.");
            }
        }

        if (! array_key_exists($datos['estado'], self::ESTADOS)) {
            throw new RuntimeException("El estado del documento legal «{$clave}.md» tiene que ser «borrador» o «vigente».");
        }

        if (! array_key_exists($datos['exige_aceptacion'], self::SI_O_NO)) {
            throw new RuntimeException("«exige_aceptacion» del documento legal «{$clave}.md» tiene que ser «si» o «no».");
        }

        return new DocumentoLegal(
            clave: $clave,
            titulo: $datos['titulo'],
            version: $datos['version'],
            esBorrador: self::ESTADOS[$datos['estado']],
            exigeAceptacion: self::SI_O_NO[$datos['exige_aceptacion']],
            contenido: trim($partes[2]),
        );
    }
}
