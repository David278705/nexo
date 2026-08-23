<?php

namespace App\CargaInicial;

/**
 * Lo que la simulación tiene que decir: cuántas filas venían, cuáles están
 * bien, cuáles no y por qué (documento 2, 6.1, paso 3).
 *
 * Se cuenta por **filas** y no por errores. Una fila con tres celdas mal es una
 * fila mala, no tres, y el proveedor que le enseña esto al cliente necesita
 * decirle cuántas personas hay que corregir, no cuántas veces se equivocó.
 *
 * `valores()` entrega las filas limpias ya traducidas a campos del sistema. Hoy
 * no las escribe nadie —esta clase no toca la base de datos ni una vez, que es
 * toda la gracia del modo simulación— y son lo que la ejecución de la carga
 * recibirá cuando el archivo esté sin errores.
 */
class ResultadoDeSimulacion
{
    /**
     * @param  list<FilaTraducida>  $filas
     * @param  list<ErrorDeCarga>  $errores  ordenados por fila
     */
    public function __construct(
        public readonly Plantilla $plantilla,
        public readonly array $filas,
        public readonly array $errores,
    ) {}

    public function filasLeidas(): int
    {
        return count($this->filas);
    }

    public function filasConError(): int
    {
        return count(array_unique(array_map(
            fn (ErrorDeCarga $error): int => $error->fila,
            $this->errores,
        )));
    }

    public function filasCorrectas(): int
    {
        return $this->filasLeidas() - $this->filasConError();
    }

    public function hayErrores(): bool
    {
        return $this->errores !== [];
    }

    /**
     * Los motivos de una fila, tal como se leen en el informe.
     *
     * @return list<string>
     */
    public function motivosDe(int $fila): array
    {
        return array_values(array_map(
            fn (ErrorDeCarga $error): string => $error->motivo,
            array_filter($this->errores, fn (ErrorDeCarga $error): bool => $error->fila === $fila),
        ));
    }

    /**
     * Las filas que se podrían cargar, ya en campos del sistema.
     *
     * @return list<array<string, mixed>>
     */
    public function valores(): array
    {
        $conError = array_map(fn (ErrorDeCarga $error): int => $error->fila, $this->errores);

        return array_values(array_map(
            fn (FilaTraducida $fila): array => $fila->valores(),
            array_filter(
                $this->filas,
                fn (FilaTraducida $fila): bool => ! in_array($fila->numero, $conError, true),
            ),
        ));
    }

    /**
     * @return array{plantilla: string, filasLeidas: int, filasCorrectas: int, filasConError: int, errores: list<array{fila: int, columna: string|null, motivo: string, texto: string}>}
     */
    public function paraPantalla(): array
    {
        return [
            'plantilla' => $this->plantilla->nombre(),
            'filasLeidas' => $this->filasLeidas(),
            'filasCorrectas' => $this->filasCorrectas(),
            'filasConError' => $this->filasConError(),
            'errores' => array_map(
                fn (ErrorDeCarga $error): array => $error->paraPantalla(),
                $this->errores,
            ),
        ];
    }
}
