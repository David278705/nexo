<?php

namespace App\Novedades;

use App\Models\Vinculacion;

/**
 * Lo que una novedad tiene que saber contestar para poder editarse y
 * eliminarse por pantalla (documento 1, 6.2).
 *
 * Las tres novedades del capítulo 6 —incapacidades, vacaciones y licencias—
 * comparten el flujo entero: el mismo modal de edición, la misma confirmación
 * de borrado y la misma prop `novedad` que las lleva a la pantalla. Lo único
 * que cambia entre ellas son los campos del formulario y la frase que describe
 * el registro, y eso es exactamente lo que declara esta interfaz.
 *
 * No la implementan movimientos ni terminaciones: no son novedades del
 * capítulo 6 y borrarlas tendría un efecto —deshacer un aumento, revivir a un
 * empleado inactivo— que ningún documento define todavía.
 */
interface NovedadEditable
{
    /**
     * El empleado al que pertenece la novedad.
     *
     * Se llama así y no `vinculacion()` porque ese nombre ya lo ocupa la
     * relación de Eloquent en los tres modelos, y una relación devuelve un
     * `BelongsTo`, no la vinculación. Aquí lo que hace falta es el empleado
     * mismo: de él salen la ficha del modal y el nombre de la confirmación.
     */
    public function vinculacionDeLaNovedad(): Vinculacion;

    /**
     * Los valores con los que se rellena el formulario al abrirlo para editar.
     *
     * Las fechas van en `Y-m-d`, que es lo que entiende `CampoFecha`, y no en
     * el formato colombiano de la tabla: la fila de la tabla es para leerla y
     * estos valores son para volver a enviarlos.
     *
     * @return array<string, string|bool|int|null>
     */
    public function valoresParaEdicion(): array;

    /**
     * La frase completa que se lee antes de eliminar, con la consecuencia ya
     * calculada (documento 3, 6.3). La escribe `ConfirmacionDeBorrado`.
     */
    public function confirmacionDeBorrado(): string;
}
