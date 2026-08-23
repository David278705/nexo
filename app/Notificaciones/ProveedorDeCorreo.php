<?php

namespace App\Notificaciones;

use Illuminate\Config\Repository;

/**
 * Por dónde salen los correos del sistema (documento 2, sección 5.5).
 *
 * La primera línea de esa sección es una condición del producto, no una
 * preferencia técnica: las alertas salen de un **proveedor de correo
 * transaccional** —Postmark, Resend o Amazon SES, los tres que nombra la tabla
 * del capítulo 1— y no del servidor de la aplicación. Un correo enviado con el
 * `sendmail` de la máquina, desde una dirección IP sin reputación y sin firma
 * DKIM, termina en la carpeta de no deseado, y una alerta que llega a spam es
 * exactamente igual de útil que una alerta que no se envió. Toda la promesa que
 * se vende depende de esto.
 *
 * ## Por qué aquí no hay ninguna credencial
 *
 * El proveedor se configura por variables de entorno —`MAIL_MAILER`,
 * `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`—, que es
 * lo que exige la tabla de controles mínimos de la sección 7.1: «secretos fuera
 * del código y del repositorio». `.env.example` explica cuáles poner y con qué
 * valores; los tres proveedores hablan SMTP, así que cambiar de uno a otro es
 * cambiar el servidor y las credenciales, sin tocar código ni desplegar.
 *
 * Lo que esta clase hace es lo único que sí se puede comprobar desde dentro:
 * **decir si lo que hay configurado sirve para producción**. El comando
 * `alertas:prueba` la consulta antes de mandar el correo de prueba, y de ahí
 * saldrá también la tarjeta de salud del sistema del panel (F5-06).
 *
 * Lo que no se puede comprobar desde aquí y hay que hacer una vez en el DNS del
 * dominio propio: SPF, DKIM y DMARC. Sin los tres, el proveedor entrega y el
 * destinatario clasifica igual como sospechoso.
 */
class ProveedorDeCorreo
{
    /**
     * Los transportes que **no** son un proveedor transaccional.
     *
     * `log` y `array` ni siquiera envían —son los de desarrollo y los de las
     * pruebas—, y `sendmail` envía desde la propia máquina, que es justo lo que
     * la sección 5.5 prohíbe.
     */
    private const SIN_PROVEEDOR = ['log', 'array', 'sendmail'];

    /**
     * La dirección de ejemplo con la que nace `.env.example`. Mientras siga
     * puesta, el dominio propio con SPF y DKIM no está configurado.
     */
    private const REMITENTE_DE_EJEMPLO = 'hello@example.com';

    public function __construct(private readonly Repository $configuracion) {}

    /**
     * El nombre del mailer configurado: `smtp`, `ses`, `postmark`, `log`…
     */
    public function nombre(): string
    {
        return (string) $this->configuracion->get('mail.default', 'log');
    }

    public function remitente(): string
    {
        return (string) $this->configuracion->get('mail.from.address', '');
    }

    /**
     * Si los correos salen por un proveedor externo y no por la aplicación.
     */
    public function esTransaccional(): bool
    {
        return ! in_array($this->nombre(), self::SIN_PROVEEDOR, true);
    }

    public function tieneRemitentePropio(): bool
    {
        $remitente = $this->remitente();

        return $remitente !== '' && $remitente !== self::REMITENTE_DE_EJEMPLO;
    }

    /**
     * Lo que hay que arreglar antes de que esto pueda salir a producción, o
     * nulo si no hay nada.
     *
     * Es una advertencia y no un bloqueo: en local el correo se manda al log a
     * propósito, y hay que poder probar el flujo entero sin credenciales de
     * ningún proveedor.
     */
    public function advertencia(): ?string
    {
        if (! $this->esTransaccional()) {
            return 'Los correos salen por «'.$this->nombre().'», que no es un proveedor transaccional. '
                .'En producción hay que configurar Postmark, Resend o Amazon SES (documento 2, 5.5): '
                .'enviando desde el propio servidor, las alertas terminan en spam.';
        }

        if (! $this->tieneRemitentePropio()) {
            return 'El remitente sigue siendo el de ejemplo. Hay que ponerlo en el dominio propio, '
                .'con SPF, DKIM y DMARC configurados, o el proveedor entregará y el destinatario '
                .'clasificará el correo como sospechoso.';
        }

        return null;
    }
}
