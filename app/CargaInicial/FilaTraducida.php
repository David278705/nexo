<?php

namespace App\CargaInicial;

/**
 * Una fila de la plantilla ya convertida a los campos del sistema, con los
 * errores que se encontraron al convertirla.
 *
 * Es lo que va y viene entre las tres etapas de la simulación —leer la celda,
 * completar lo que no sale de una sola celda, validar el conjunto—, y por eso
 * no es inmutable: cada etapa añade valores o errores a la misma fila.
 *
 * Lleva la cuenta de **qué campos ya fallaron** para no reportar dos veces la
 * misma celda. Sin eso, una fecha ilegible produciría dos líneas en el informe:
 * «no es una fecha» al leerla y «es obligatoria» al validar el campo que quedó
 * vacío. Dos mensajes para un solo error hacen que un informe de tres errores
 * parezca de seis y que nadie confíe en la cuenta.
 */
final class FilaTraducida
{
    /** @var array<string, mixed> */
    private array $valores = [];

    /** @var list<ErrorDeCarga> */
    private array $errores = [];

    /** @var list<string> */
    private array $camposConError = [];

    public function __construct(public readonly int $numero) {}

    public function poner(string $campo, mixed $valor): void
    {
        data_set($this->valores, $campo, $valor);
    }

    public function valor(string $campo): mixed
    {
        return data_get($this->valores, $campo);
    }

    /**
     * @return array<string, mixed>
     */
    public function valores(): array
    {
        return $this->valores;
    }

    /**
     * Anota un error de esta fila. Los campos que se le pasen quedan marcados y
     * las reglas de validación ya no volverán a hablar de ellos.
     *
     * Son varios más veces de las que parece: una sola celda mal escrita puede
     * dejar sin resolver dos campos del sistema —el municipio que no existe deja
     * también sin departamento—, y los dos tienen que callarse para que el
     * informe cuente un error y no dos.
     */
    public function error(?string $columna, string $motivo, string ...$campos): void
    {
        $this->errores[] = new ErrorDeCarga($this->numero, $columna, $motivo);

        foreach ($campos as $campo) {
            $this->camposConError[] = $campo;
        }
    }

    public function yaFallo(string $campo): bool
    {
        return in_array($campo, $this->camposConError, true);
    }

    public function tieneErrores(): bool
    {
        return $this->errores !== [];
    }

    /**
     * @return list<ErrorDeCarga>
     */
    public function errores(): array
    {
        return $this->errores;
    }
}
