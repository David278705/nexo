<?php

namespace App\Autenticacion;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;

/**
 * La espera creciente tras varios intentos fallidos.
 *
 * El documento de diseño lo pide así en la sección 5.1: «Tras tres intentos
 * fallidos, espera creciente». Aquí eso significa que los tres primeros fallos
 * son gratis —una contraseña se escribe mal— y que a partir del tercero cada
 * fallo duplica la espera, hasta un tope.
 *
 * | Fallos | Espera |
 * |---|---|
 * | 1 y 2 | ninguna |
 * | 3 | 1 minuto |
 * | 4 | 2 minutos |
 * | 5 | 4 minutos |
 * | 6 | 8 minutos |
 * | 7 en adelante | 15 minutos |
 *
 * La cuenta se lleva por **correo y dirección IP juntos**, no por uno de los
 * dos: por correo solo, cualquiera podría dejar fuera del sistema a otro con
 * solo fallar tres veces a su nombre; por IP sola, una empresa entera detrás de
 * la misma salida a internet se bloquearía a sí misma.
 *
 * Vive en la caché y no en la base de datos porque es información efímera: el
 * rastro permanente de los intentos es la tabla `accesos`, que se escribe igual.
 *
 * El **ámbito** separa cuentas distintas de la misma persona. La contraseña y el
 * código del segundo factor se cuentan aparte, y no por gusto: si compartieran
 * la cuenta, entrar bien con la contraseña —que llama a `limpiar()`— borraría
 * los fallos del código, y quien tuviera la contraseña podría probar códigos sin
 * límite volviendo a entrar cada tres intentos.
 */
class LimitadorDeIntentos
{
    /**
     * Fallos que no cuestan espera.
     */
    public const FALLOS_LIBRES = 2;

    /**
     * Lo que espera el primer fallo que sí cuenta, en segundos.
     */
    public const ESPERA_INICIAL = 60;

    /**
     * El tope al que llega la duplicación, en segundos.
     */
    public const ESPERA_MAXIMA = 900;

    /**
     * Cuánto se recuerdan los fallos de alguien que deja de intentar.
     */
    public const MEMORIA = 86400;

    /**
     * El ámbito de la contraseña, que es el que se usa si no se dice otro.
     */
    public const ACCESO = 'acceso';

    /**
     * El ámbito del código del segundo factor.
     */
    public const SEGUNDO_FACTOR = 'segundo-factor';

    public function __construct(private readonly Cache $cache) {}

    /**
     * Los segundos que faltan para poder intentarlo otra vez. Cero si se puede.
     */
    public function segundosDeEspera(string $correo, ?string $ip, string $ambito = self::ACCESO): int
    {
        $intento = $this->intentos($correo, $ip, $ambito);

        if ($intento['disponible_en'] === null) {
            return 0;
        }

        return max(0, $intento['disponible_en'] - Carbon::now()->getTimestamp());
    }

    public function estaBloqueado(string $correo, ?string $ip, string $ambito = self::ACCESO): bool
    {
        return $this->segundosDeEspera($correo, $ip, $ambito) > 0;
    }

    /**
     * Anota un fallo y devuelve la espera que le corresponde.
     */
    public function registrarFallo(string $correo, ?string $ip, string $ambito = self::ACCESO): int
    {
        $fallos = $this->intentos($correo, $ip, $ambito)['fallos'] + 1;
        $espera = $this->esperaPara($fallos);

        $this->cache->put($this->clave($correo, $ip, $ambito), [
            'fallos' => $fallos,
            'disponible_en' => $espera === 0
                ? null
                : Carbon::now()->getTimestamp() + $espera,
        ], self::MEMORIA);

        return $espera;
    }

    /**
     * Se llama al entrar bien: quien recordó su contraseña vuelve a empezar de
     * cero, o arrastraría la espera de ayer.
     */
    public function limpiar(string $correo, ?string $ip, string $ambito = self::ACCESO): void
    {
        $this->cache->forget($this->clave($correo, $ip, $ambito));
    }

    /**
     * La espera dicha en la unidad en que se entiende.
     *
     * «Espera 180 segundos» obliga a dividir mentalmente; «espera 3 minutos»,
     * no. Por debajo del minuto sí se dicen segundos, que es la única forma de
     * no redondear a «1 minuto» algo que dura veinte segundos.
     *
     * El texto vive aquí, junto a la cuenta que lo produce, porque lo dicen dos
     * pantallas: la contraseña y el código del segundo factor.
     */
    public function mensajeDeEspera(int $segundos): string
    {
        if ($segundos < 60) {
            return "Demasiados intentos fallidos. Espera {$segundos} segundos e inténtalo de nuevo.";
        }

        $minutos = (int) ceil($segundos / 60);
        $unidad = $minutos === 1 ? 'minuto' : 'minutos';

        return "Demasiados intentos fallidos. Espera {$minutos} {$unidad} e inténtalo de nuevo.";
    }

    private function esperaPara(int $fallos): int
    {
        if ($fallos <= self::FALLOS_LIBRES) {
            return 0;
        }

        $espera = self::ESPERA_INICIAL * (2 ** ($fallos - self::FALLOS_LIBRES - 1));

        return (int) min($espera, self::ESPERA_MAXIMA);
    }

    /**
     * @return array{fallos: int, disponible_en: int|null}
     */
    private function intentos(string $correo, ?string $ip, string $ambito): array
    {
        /** @var array{fallos: int, disponible_en: int|null}|null $guardado */
        $guardado = $this->cache->get($this->clave($correo, $ip, $ambito));

        return $guardado ?? ['fallos' => 0, 'disponible_en' => null];
    }

    private function clave(string $correo, ?string $ip, string $ambito): string
    {
        return $ambito.':intentos:'.sha1(mb_strtolower($correo).'|'.($ip ?? 'sin-ip'));
    }
}
