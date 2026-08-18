<?php

namespace App\Multiempresa;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * El filtro por empresa. Lo pone Eloquent en cada consulta, no el programador.
 *
 * Si alguien escribe `where('tenant_id', ...)` a mano en un controlador, es
 * señal de que el modelo perdió el trait: esa condición sobra siempre.
 */
class AlcanceTenant implements Scope
{
    /**
     * La columna vive aquí y no en el trait porque PHP no deja leer una
     * constante de trait por el nombre del trait, solo por el de la clase que
     * lo usa. Aquí hay un único sitio del que la leen todos.
     */
    public const COLUMNA = 'tenant_id';

    /**
     * @param  Builder<covariant Model>  $consulta
     */
    public function apply(Builder $consulta, Model $modelo): void
    {
        $id = app(ContextoTenant::class)->id();

        if ($id === null) {
            // Falla cerrado. Sin empresa en contexto no se devuelve nada, en
            // lugar de devolverlo todo. Un comando mal escrito produce una
            // lista vacía, no la nómina de todos los clientes.
            $consulta->whereRaw('1 = 0');

            return;
        }

        $consulta->where(
            $modelo->qualifyColumn(self::COLUMNA),
            $id
        );
    }
}
