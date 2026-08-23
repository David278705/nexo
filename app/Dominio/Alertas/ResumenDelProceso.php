<?php

namespace App\Dominio\Alertas;

use App\Models\Tenant;
use Throwable;

/**
 * Lo que pasó en una corrida del proceso diario.
 *
 * Existe por dos motivos que apuntan al mismo sitio. El primero es el comando:
 * quien lo lanza a mano tiene que ver algo más que un cursor que vuelve. El
 * segundo es la salud del sistema del panel del proveedor (F5-06), que pregunta
 * exactamente esto —cuántas empresas se recorrieron, cuántas alertas salieron y
 * cuáles empresas fallaron—.
 *
 * `repetidas` es el número interesante: son las alertas que el índice único
 * rechazó por estar ya mandadas. En la segunda corrida del mismo día debe ser
 * igual al total de la primera, y esa es la forma de ver que la idempotencia
 * está trabajando en vez de suponerlo.
 */
class ResumenDelProceso
{
    private int $empresas = 0;

    private int $registradas = 0;

    private int $repetidas = 0;

    private int $sinDestinatarios = 0;

    private int $saldosRefrescados = 0;

    /** @var list<array{empresa: string, motivo: string}> */
    private array $fallidas = [];

    public function contarEmpresa(): void
    {
        $this->empresas++;
    }

    public function contarRegistrada(): void
    {
        $this->registradas++;
    }

    public function contarRepetida(): void
    {
        $this->repetidas++;
    }

    public function contarSinDestinatarios(): void
    {
        $this->sinDestinatarios++;
    }

    /**
     * Cuántas filas de saldo quedaron en la caché de una empresa, que son
     * cuántos empleados activos tiene. Se suman las de todas.
     */
    public function contarSaldosRefrescados(int $cuantos): void
    {
        $this->saldosRefrescados += $cuantos;
    }

    public function contarEmpresaFallida(Tenant $empresa, Throwable $error): void
    {
        $this->fallidas[] = [
            'empresa' => (string) $empresa->getAttribute('nombre'),
            'motivo' => $error->getMessage(),
        ];
    }

    public function empresas(): int
    {
        return $this->empresas;
    }

    public function registradas(): int
    {
        return $this->registradas;
    }

    public function repetidas(): int
    {
        return $this->repetidas;
    }

    public function sinDestinatarios(): int
    {
        return $this->sinDestinatarios;
    }

    public function saldosRefrescados(): int
    {
        return $this->saldosRefrescados;
    }

    /**
     * @return list<array{empresa: string, motivo: string}>
     */
    public function fallidas(): array
    {
        return $this->fallidas;
    }

    /**
     * La línea que el comando escribe en la consola y que queda en el registro
     * del programador de tareas.
     */
    public function resumen(): string
    {
        return sprintf(
            '%d empresas · %d alertas nuevas · %d ya enviadas · %d saldos en caché · %d empresas con error',
            $this->empresas,
            $this->registradas,
            $this->repetidas,
            $this->saldosRefrescados,
            count($this->fallidas),
        );
    }
}
