<?php

namespace App\Movimientos;

/**
 * Las dos clases de movimiento del documento 1, 8.1: aumento salarial —mismo
 * cargo, sube el salario— y promoción, que además cambia de cargo.
 *
 * La diferencia entre las dos no es decorativa: de ella depende el cargo con
 * el que queda el empleado. «Si el movimiento es solo aumento salarial, se
 * repite el mismo cargo» es literal de la sección 8.1, así que el cargo
 * resultante de un aumento **no lo digita nadie**: lo decide
 * `cargoResultante()`, y por eso vive aquí y no en el formulario ni en el
 * controlador.
 */
enum TipoDeMovimiento: string
{
    case AumentoSalarial = 'aumento_salarial';

    case Promocion = 'promocion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AumentoSalarial => 'Aumento salarial',
            self::Promocion => 'Promoción',
        };
    }

    /**
     * Lo que el tipo significa, entre paréntesis en el selector del formulario:
     * «Aumento salarial — mismo cargo». Son los dos textos del prototipo.
     */
    public function descripcion(): string
    {
        return match ($this) {
            self::AumentoSalarial => 'mismo cargo',
            self::Promocion => 'cambia de cargo',
        };
    }

    /**
     * ¿Este movimiento cambia el cargo del empleado?
     */
    public function cambiaElCargo(): bool
    {
        return $this === self::Promocion;
    }

    /**
     * El cargo con el que queda el empleado después del movimiento.
     *
     * En un aumento salarial se repite el actual y se ignora cualquier otro que
     * haya llegado: el formulario lo enseña de solo lectura, y el servidor no
     * se fía de que siga siéndolo.
     */
    public function cargoResultante(string $cargoActual, ?string $cargoPedido): string
    {
        if (! $this->cambiaElCargo()) {
            return $cargoActual;
        }

        $pedido = trim((string) $cargoPedido);

        return $pedido === '' ? $cargoActual : $pedido;
    }

    /**
     * Las opciones del filtro del listado, con el nombre corto.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElegir(): array
    {
        return array_map(
            fn (self $tipo): array => ['valor' => $tipo->value, 'texto' => $tipo->etiqueta()],
            self::cases(),
        );
    }

    /**
     * Las opciones del formulario, que llevan además la consecuencia: quien
     * elige tiene que saber que una de las dos le va a cambiar el cargo.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function paraElFormulario(): array
    {
        return array_map(
            fn (self $tipo): array => [
                'valor' => $tipo->value,
                'texto' => $tipo->etiqueta().' — '.$tipo->descripcion(),
            ],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $tipo): string => $tipo->value, self::cases());
    }
}
