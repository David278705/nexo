<?php

namespace App\Alertas;

use App\Autorizacion\PoliticaDeModulos;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Models\User;

/**
 * Qué alertas ve cada usuario en el panel.
 *
 * El panel de alertas es el **canal alterno del correo** (documento 2, 5.5):
 * enseña lo que ya se mandó, para quien no lo encuentra en su bandeja o para
 * cuando el correo falla. Siendo eso, tiene que alcanzar exactamente a las
 * mismas personas que el correo y no a una más: quien no recibiría el aviso de
 * vencimiento de un contrato tampoco puede leerlo en pantalla, porque dentro va
 * el nombre, el cargo y la fecha de salida de una persona.
 *
 * Y la pregunta ya está respondida en un solo sitio. `TipoDeAlerta::modulo()`
 * dice de qué módulo es cada alerta y `DestinatariosDeAlertas` lo usa para
 * decidir a qué correos sale. Aquí se usa el mismo par para decidir qué filas
 * se enseñan, así que el panel y el buzón no pueden discrepar: el día que una
 * alerta cambie de módulo, cambian las dos cosas a la vez.
 *
 * Por eso tener el módulo `Alertas` no abre nada por sí solo. Es el permiso
 * para entrar a la pantalla; lo que hay dentro lo siguen decidiendo los módulos
 * que el usuario ya tenía.
 */
class AlertasDelUsuario
{
    public function __construct(private readonly PoliticaDeModulos $politica) {}

    /**
     * Los tipos de alerta que este usuario habría recibido por correo.
     *
     * @return list<TipoDeAlerta>
     */
    public function tiposVisiblesPara(User $usuario): array
    {
        return array_values(array_filter(
            TipoDeAlerta::cases(),
            fn (TipoDeAlerta $tipo): bool => $this->politica->puedeAcceder($usuario, $tipo->modulo()),
        ));
    }

    /**
     * Esos mismos tipos como opciones del filtro de la pantalla.
     *
     * El filtro ofrece solo lo que se puede ver: una lista con «Vacaciones:
     * acumulación de 15 días» dentro le contaría a quien no tiene el módulo qué
     * alertas existen y le dejaría comprobar por el número de resultados si a
     * alguien le llegaron.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public function opcionesDeTipoPara(User $usuario): array
    {
        return array_map(
            fn (TipoDeAlerta $tipo): array => ['valor' => $tipo->value, 'texto' => $tipo->etiqueta()],
            $this->tiposVisiblesPara($usuario),
        );
    }
}
