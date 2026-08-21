<?php

namespace App\Autenticacion;

use Illuminate\Support\Carbon;

/**
 * El segundo factor del panel del proveedor: un código de seis dígitos que
 * cambia cada treinta segundos, del tipo que producen Google Authenticator,
 * Microsoft Authenticator o 1Password.
 *
 * Es el algoritmo TOTP del RFC 6238. Está escrito aquí, en unas setenta líneas,
 * en lugar de traer una dependencia: el estándar no cambia, sus propios vectores
 * de prueba comprueban esta implementación en `tests/Unit/SegundoFactorTest.php`,
 * y una dependencia más en la puerta de entrada del sistema es una dependencia
 * más de la que hay que responder.
 *
 * Esta clase **no** sabe de sesiones, de usuarios ni de peticiones: recibe un
 * secreto y un código y responde sí o no. Quién ya se verificó en esta sesión lo
 * lleva `SegundoFactorDeLaSesion`, y a quién se le exige lo decide el middleware
 * `SegundoFactorVerificado`.
 */
class SegundoFactor
{
    /** Los dígitos del código. Seis es lo que esperan todas las aplicaciones. */
    public const DIGITOS = 6;

    /** Cuánto vive cada código, en segundos. */
    public const SEGUNDOS_POR_CODIGO = 30;

    /**
     * Cuántos periodos de más se aceptan, hacia atrás y hacia adelante.
     *
     * Uno, que son treinta segundos. Existe porque el reloj del teléfono y el
     * del servidor nunca están exactamente iguales, y porque un código leído en
     * el segundo 29 se escribe en el 31. Sin tolerancia, el segundo factor
     * rechazaría códigos correctos; con más, la ventana de un código robado se
     * alarga sin necesidad.
     */
    public const TOLERANCIA = 1;

    /**
     * Veinte bytes, que son los ciento sesenta bits que recomienda el RFC 4226
     * y los que usa todo el mundo. En base32 son treinta y dos caracteres.
     */
    private const BYTES_DEL_SECRETO = 20;

    /** El alfabeto del RFC 4648, que es el que leen las aplicaciones. */
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Un secreto nuevo, en base32 y sin relleno.
     *
     * Se emite una sola vez por cuenta y no se vuelve a enseñar después de
     * confirmado: si alguien pierde el teléfono, se emite otro.
     */
    public function secretoNuevo(): string
    {
        return $this->aBase32(random_bytes(self::BYTES_DEL_SECRETO));
    }

    /**
     * El código que corresponde a este instante. `null` es «ahora».
     */
    public function codigo(string $secreto, ?int $instante = null): string
    {
        return $this->codigoDelPeriodo($secreto, $this->periodo($instante));
    }

    /**
     * Si el código escrito es el de este momento, el anterior o el siguiente.
     *
     * La comparación es con `hash_equals` y no con `===` para que el tiempo que
     * tarda en responder no delate cuántos dígitos iban bien.
     */
    public function verificar(string $secreto, string $codigo, ?int $instante = null): bool
    {
        // La gente copia el código con el espacio del medio que enseñan casi
        // todas las aplicaciones. Rechazarlo por eso sería absurdo.
        $escrito = preg_replace('/\D/', '', $codigo) ?? '';

        if (mb_strlen($escrito) !== self::DIGITOS) {
            return false;
        }

        $periodo = $this->periodo($instante);
        $valido = false;

        for ($salto = -self::TOLERANCIA; $salto <= self::TOLERANCIA; $salto++) {
            // Sin corto circuito: se recorren siempre los tres periodos para que
            // acertar el primero no tarde distinto que acertar el último.
            $valido = hash_equals($this->codigoDelPeriodo($secreto, $periodo + $salto), $escrito)
                || $valido;
        }

        return $valido;
    }

    /**
     * La dirección `otpauth://` que las aplicaciones leen de un código QR y que
     * también se puede pegar a mano.
     *
     * El emisor va dos veces —en la etiqueta y como parámetro— porque es lo que
     * recomienda la especificación de Key Uri Format: hay aplicaciones que solo
     * leen una de las dos.
     */
    public function enlaceDeAplicacion(string $correo, string $secreto, string $emisor): string
    {
        $etiqueta = rawurlencode($emisor).':'.rawurlencode($correo);

        $parametros = http_build_query([
            'secret' => $secreto,
            'issuer' => $emisor,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITOS,
            'period' => self::SEGUNDOS_POR_CODIGO,
        ]);

        return "otpauth://totp/{$etiqueta}?{$parametros}";
    }

    /**
     * El secreto en grupos de cuatro, que es como se copia sin equivocarse
     * cuando la aplicación no puede leer el código.
     */
    public function secretoParaEscribirAMano(string $secreto): string
    {
        return trim(chunk_split($secreto, 4, ' '));
    }

    private function periodo(?int $instante): int
    {
        return intdiv($instante ?? Carbon::now()->getTimestamp(), self::SEGUNDOS_POR_CODIGO);
    }

    /**
     * El código de un periodo concreto: HMAC-SHA1 del contador y el truncamiento
     * dinámico del RFC 4226, sección 5.3.
     */
    private function codigoDelPeriodo(string $secreto, int $periodo): string
    {
        $huella = hash_hmac('sha1', pack('J', $periodo), $this->deBase32($secreto), true);

        // Los cuatro bits finales dicen desde qué byte se leen los cuatro que
        // forman el número. El primer bit se descarta para que el resultado no
        // dependa de si la máquina interpreta el signo.
        $desde = ord($huella[19]) & 0x0F;

        $numero = ((ord($huella[$desde]) & 0x7F) << 24)
            | (ord($huella[$desde + 1]) << 16)
            | (ord($huella[$desde + 2]) << 8)
            | ord($huella[$desde + 3]);

        return str_pad(
            (string) ($numero % (10 ** self::DIGITOS)),
            self::DIGITOS,
            '0',
            STR_PAD_LEFT,
        );
    }

    private function aBase32(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $texto = '';

        foreach (str_split($bits, 5) as $grupo) {
            $texto .= self::ALFABETO[bindec(str_pad($grupo, 5, '0'))];
        }

        return $texto;
    }

    private function deBase32(string $texto): string
    {
        $bits = '';

        foreach (str_split(mb_strtoupper(str_replace([' ', '='], '', $texto))) as $caracter) {
            $posicion = strpos(self::ALFABETO, $caracter);

            if ($posicion === false) {
                continue;
            }

            $bits .= str_pad(decbin($posicion), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $grupo) {
            // El último grupo puede quedar corto: son bits de relleno del
            // base32, no un byte.
            if (mb_strlen($grupo) === 8) {
                $bytes .= chr(bindec($grupo));
            }
        }

        return $bytes;
    }
}
