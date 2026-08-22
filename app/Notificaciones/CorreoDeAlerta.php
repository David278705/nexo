<?php

namespace App\Notificaciones;

use App\Models\AlertaEnviada;
use App\Models\Tenant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * El correo de cualquier alerta del capítulo 9.
 *
 * Es uno solo para las once, no uno por alerta, y esa es la plantilla base que
 * pide la sección 5.4: encabezado con el nombre del sistema, cuerpo, y pie con
 * el nombre de la empresa y el enlace para entrar. Lo único que cambia entre
 * una alerta y otra es el asunto y el cuerpo, y los dos los escribe la regla de
 * disparo con los textos del documento.
 *
 * **El texto no se calcula aquí.** Se lee de la fila de `alertas_enviadas`, que
 * lo guardó ya redactado. Si se recalculara al enviar, un reintento que ocurra
 * después de que alguien corrija el cargo del empleado mandaría un correo
 * distinto del que dice el registro.
 */
class CorreoDeAlerta extends Mailable
{
    public function __construct(
        public readonly AlertaEnviada $alerta,
        public readonly Tenant $empresa,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alerta->asunto);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'correos.alerta',
            with: [
                'parrafos' => self::parrafosDe($this->alerta->cuerpo),
                'empresa' => $this->empresa->getAttribute('nombre'),
                'modulo' => $this->alerta->tipo->modulo()->etiqueta(),
                'enlace' => route('inicio'),
            ],
        );
    }

    /**
     * El cuerpo, partido en párrafos.
     *
     * Las alertas individuales son una sola frase; los resúmenes mensuales
     * (F3-05) traen la frase y debajo el listado, línea a línea. Partir por
     * saltos de línea deja que las dos formas usen la misma plantilla sin que
     * el listado salga pegado en un solo bloque.
     *
     * @return list<string>
     */
    public static function parrafosDe(string $cuerpo): array
    {
        $lineas = preg_split('/\R+/', trim($cuerpo)) ?: [];

        return array_values(array_filter(
            array_map(trim(...), $lineas),
            fn (string $linea): bool => $linea !== '',
        ));
    }
}
