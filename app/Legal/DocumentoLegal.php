<?php

namespace App\Legal;

use Illuminate\Support\Str;

/**
 * Un documento legal del servicio, tal como está escrito hoy en su archivo de
 * `resources/legal/`.
 *
 * La versión es lo que la empresa acepta: no el texto, que puede tener
 * correcciones de forma, sino el número que sube cuando el contenido cambia y
 * hay que volver a aceptarlo.
 */
final class DocumentoLegal
{
    public function __construct(
        public readonly string $clave,
        public readonly string $titulo,
        public readonly string $version,
        public readonly bool $esBorrador,
        public readonly bool $exigeAceptacion,
        private readonly string $contenido,
    ) {}

    /**
     * El cuerpo en HTML. El Markdown lo escribe el proveedor, no un usuario,
     * pero igual se le quita cualquier HTML crudo y los enlaces inseguros: un
     * texto legal pegado de Word puede traer cualquier cosa.
     */
    public function html(): string
    {
        return Str::markdown($this->contenido, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * La huella SHA-256 del texto exacto (`PROD-25`). Se guarda con cada
     * aceptación: prueba qué decía el documento ese día, aunque después alguien
     * lo corrija sin subir la versión.
     */
    public function huella(): string
    {
        return hash('sha256', $this->contenido);
    }

    public function url(): string
    {
        return route('legal.documento', $this->clave);
    }
}
