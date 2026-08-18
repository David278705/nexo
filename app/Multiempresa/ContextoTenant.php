<?php

namespace App\Multiempresa;

use App\Models\Tenant;
use Closure;

/**
 * Guarda cuál es la empresa de la petición en curso.
 *
 * Se registra como singleton, así que se resuelve una sola vez por petición y
 * todo el resto del sistema —el global scope, los jobs, los comandos— lee de
 * aquí. Nadie más decide de qué empresa son los datos.
 */
class ContextoTenant
{
    private ?Tenant $tenant = null;

    public function fijar(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function olvidar(): void
    {
        $this->tenant = null;
    }

    public function hay(): bool
    {
        return $this->tenant instanceof Tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    /**
     * El identificador cuando no puede faltar: al escribir.
     */
    public function idObligatorio(string $modelo): int
    {
        return $this->id() ?? throw SinContextoDeTenant::alEscribir($modelo);
    }

    /**
     * Ejecuta algo dentro del contexto de una empresa y restaura el anterior
     * al terminar, pase lo que pase.
     *
     * Es la única forma admitida de cambiar de empresa fuera del middleware:
     * los jobs en cola la usan al arrancar y los comandos programados la usan
     * en cada vuelta del recorrido de empresas. El `finally` es el punto: si
     * el job falla a la mitad, el contexto no se queda pegado para el
     * siguiente, que es el error clásico de este patrón.
     *
     * @template TRetorno
     *
     * @param  Closure(Tenant): TRetorno  $callback
     * @return TRetorno
     */
    public function paraTenant(Tenant $tenant, Closure $callback): mixed
    {
        $anterior = $this->tenant;

        $this->fijar($tenant);

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $anterior;
        }
    }

    /**
     * Ejecuta algo sin ninguna empresa en contexto.
     *
     * Con el global scope fallando cerrado esto no «abre» nada: sirve para
     * comprobar en pruebas que sin contexto no se ve absolutamente nada.
     *
     * @template TRetorno
     *
     * @param  Closure(): TRetorno  $callback
     * @return TRetorno
     */
    public function sinTenant(Closure $callback): mixed
    {
        $anterior = $this->tenant;

        $this->olvidar();

        try {
            return $callback();
        } finally {
            $this->tenant = $anterior;
        }
    }
}
