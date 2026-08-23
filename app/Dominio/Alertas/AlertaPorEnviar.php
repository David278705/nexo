<?php

namespace App\Dominio\Alertas;

use App\Models\Vinculacion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Un correo que una regla decidió que hay que mandar.
 *
 * Es lo único que las reglas de disparo devuelven, y trae el texto **ya
 * escrito**: quién escribe el asunto y el cuerpo es la regla, que es la que
 * conoce su tabla del capítulo 9 y sabe si el aviso dice «mañana» o «en dos
 * días». La infraestructura no redacta nada; registra, encola y envía.
 *
 * Las dos fechas son distintas y las dos importan:
 *
 * - `fechaDeEnvio` es el día en que sale el correo, ya corrido al día hábil
 *   anterior si hacía falta. Es la que entra en la clave de idempotencia.
 * - `fechaDelHecho` es el día del que habla el mensaje, el que va entre
 *   paréntesis en el texto (documento 1, 9.1).
 */
class AlertaPorEnviar
{
    private function __construct(
        public readonly TipoDeAlerta $tipo,
        public readonly ?Vinculacion $vinculacion,
        public readonly Carbon $fechaDeEnvio,
        public readonly Carbon $fechaDelHecho,
        public readonly string $asunto,
        public readonly string $cuerpo,
        private readonly ?string $claveUnica = null,
    ) {}

    /**
     * Una alerta de un empleado concreto: periodo de prueba, vencimiento,
     * prórrogas y topes (documento 1, 9.7, fila «individuales»).
     */
    public static function paraEmpleado(
        TipoDeAlerta $tipo,
        Vinculacion $vinculacion,
        CarbonInterface $fechaDeEnvio,
        CarbonInterface $fechaDelHecho,
        string $asunto,
        string $cuerpo,
    ): self {
        return new self(
            $tipo,
            $vinculacion,
            self::dia($fechaDeEnvio),
            self::dia($fechaDelHecho),
            $asunto,
            $cuerpo,
        );
    }

    /**
     * Una alerta de la empresa entera, sin vinculación: los cuatro resúmenes
     * mensuales (documento 1, 9.7, fila «resumen mensual»).
     */
    public static function paraLaEmpresa(
        TipoDeAlerta $tipo,
        CarbonInterface $fechaDeEnvio,
        CarbonInterface $fechaDelHecho,
        string $asunto,
        string $cuerpo,
    ): self {
        return new self(
            $tipo,
            null,
            self::dia($fechaDeEnvio),
            self::dia($fechaDelHecho),
            $asunto,
            $cuerpo,
        );
    }

    /**
     * El correo de prueba de entregabilidad (F3-06, documento 2, 5.5).
     *
     * Es el único que **no** es idempotente por día, y es a propósito. La regla
     * de la sección 5.2 protege de que el proceso diario mande dos veces lo
     * mismo sin que nadie se lo haya pedido; aquí ocurre lo contrario: quien
     * está peleándose con el SPF de su dominio necesita mandar la prueba, mirar
     * la bandeja, corregir el registro DNS y volver a mandarla, todo la misma
     * mañana. Por eso la clave lleva la hora, y cada intento deja su propia fila
     * en el registro de envíos, que es lo que se está comprobando.
     */
    public static function deEntregabilidad(
        CarbonInterface $momento,
        string $asunto,
        string $cuerpo,
    ): self {
        return new self(
            TipoDeAlerta::PruebaDeEntregabilidad,
            null,
            self::dia($momento),
            self::dia($momento),
            $asunto,
            $cuerpo,
            claveUnica: TipoDeAlerta::PruebaDeEntregabilidad->value.'::'.$momento->format('Y-m-d H:i:s'),
        );
    }

    /**
     * La clave de idempotencia de la sección 5.2: tipo, vinculación y fecha
     * programada.
     *
     * Se arma aquí y no en quien la guarda para que las tres partes no puedan
     * separarse de la fila que describen. Un resumen mensual no tiene
     * vinculación y deja ese hueco vacío: por empresa y por día solo puede
     * haber uno.
     */
    public function clave(): string
    {
        return $this->claveUnica ?? implode(':', [
            $this->tipo->value,
            $this->vinculacion?->getKey() ?? '',
            $this->fechaDeEnvio->toDateString(),
        ]);
    }

    private static function dia(CarbonInterface $fecha): Carbon
    {
        return Carbon::parse($fecha->toDateString());
    }
}
