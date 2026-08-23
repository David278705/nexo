<?php

namespace App\CargaInicial;

use App\Models\Persona;
use App\Models\Vinculacion;
use Illuminate\Support\Collection;

/**
 * Los empleados que la empresa ya tiene cargados, indexados por su número de
 * documento.
 *
 * Es lo que cumple la regla de dependencia entre plantillas del documento 2,
 * 6.3: las cinco plantillas de histórico y de perfil se vinculan por número de
 * documento, y ese documento tiene que existir en la plantilla de empleados
 * **ya cargada**. Aquí se resuelve esa búsqueda, una sola vez por archivo:
 * doscientas filas de histórico serían doscientas consultas iguales.
 *
 * **El aislamiento no se comprueba aquí y no es un olvido.** `Persona` y
 * `Vinculacion` llevan el trait `PerteneceATenant`, así que las dos consultas
 * ya salen filtradas por la empresa en contexto. Un documento de otra empresa
 * no aparece, y el informe dirá que no está en la planta, que es exactamente lo
 * que tiene que decir.
 */
class PlantaDeLaEmpresa
{
    /** @var Collection<string, Vinculacion>|null */
    private ?Collection $vinculaciones = null;

    /** @var Collection<string, Persona>|null */
    private ?Collection $personas = null;

    /**
     * La vinculación activa de ese documento, o nula si no hay ninguna.
     *
     * Solo las activas: es la regla absoluta de la sección 6.1 del documento 1,
     * la misma que aplican los cinco formularios de novedad.
     */
    public function vinculacionActiva(string $documento): ?Vinculacion
    {
        $this->vinculaciones ??= Vinculacion::query()
            ->activas()
            ->with('persona')
            ->get()
            ->keyBy(fn (Vinculacion $vinculacion): string => (string) $vinculacion->persona->getAttribute('numero_documento'));

        return $this->vinculaciones->get($documento);
    }

    /**
     * La persona con ese documento, activa o no: el perfil sociodemográfico
     * cuelga de la persona y no de la vinculación (documento 1, 5.2).
     */
    public function persona(string $documento): ?Persona
    {
        $this->personas ??= Persona::query()
            ->get()
            ->keyBy(fn (Persona $persona): string => (string) $persona->getAttribute('numero_documento'));

        return $this->personas->get($documento);
    }
}
