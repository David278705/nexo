<?php

namespace App\Dominio\Alertas;

use App\Autorizacion\PoliticaDeModulos;
use App\Models\Tenant;
use App\Models\User;

/**
 * A quién le llega cada alerta.
 *
 * El capítulo 4 del documento 1 lo resuelve en una frase: las alertas llegan a
 * los administradores con acceso al módulo correspondiente, y por eso ese
 * cuarto parámetro de la empresa «se deduce y no se guarda» (F1-12). Aquí se
 * deduce.
 *
 * La pregunta se le hace a `PoliticaDeModulos`, la misma que responde el
 * middleware y el menú. Si se resolviera aquí leyendo `user_modulos` a mano,
 * habría dos respuestas posibles a «quién ve Vacaciones» y un día dirían cosas
 * distintas: el superadministrador, que tiene todos los módulos por su rol y
 * ninguna fila en esa tabla, sería el primero en caerse.
 */
class DestinatariosDeAlertas
{
    public function __construct(private readonly PoliticaDeModulos $politica) {}

    /**
     * Los correos de la empresa que deben recibir este tipo de alerta.
     *
     * Los usuarios se piden por `Tenant::usuarios()` y no con un `where` a
     * mano: `User` no lleva `PerteneceATenant` a propósito y esa relación es lo
     * que evita el filtro escrito a mano que prohíbe la regla de aislamiento.
     *
     * @return list<string>
     */
    public function para(Tenant $empresa, TipoDeAlerta $tipo): array
    {
        $modulo = $tipo->modulo();

        return $empresa->usuarios()
            ->with('modulos')
            ->get()
            ->filter(fn (User $usuario): bool => $this->politica->puedeAcceder($usuario, $modulo))
            ->pluck('email')
            ->unique()
            ->values()
            ->all();
    }
}
