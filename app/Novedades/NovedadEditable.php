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
 * **Desde REV-03 la implementan también movimientos y terminaciones.** No son
 * novedades del capítulo 6 y su borrado no es quitar una fila —devuelve el
 * salario anterior, revive a un empleado inactivo—, pero lo que la pantalla
 * necesita saber de ellos es exactamente lo mismo: de quién es el registro, con
 * qué valores se rellena el formulario y qué frase se lee antes de eliminarlo.
 * Lo que cambia es la consecuencia, y la consecuencia se escribe en
 * `ConfirmacionDeBorrado` y se ejecuta en `Vinculacion`, no aquí.
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
     * Lo que el formulario de edición enseña de solo lectura y **no** es un
     * campo suyo.
     *
     * Las tres novedades del capítulo 6 devuelven un arreglo vacío: sus campos
     * de solo lectura —los días contados, el saldo— los calcula el servidor a
     * partir de lo que se está digitando, no del registro guardado.
     *
     * Un movimiento sí tiene algo que contar aquí. Su formulario enseña «Cargo
     * actual» y «Salario actual», que al registrar son los del empleado y al
     * corregir son los que el propio movimiento guardó como anteriores: el
     * empleado ya lleva encima los nuevos, así que preguntárselos a él
     * enseñaría el resultado del movimiento en la casilla del punto de partida.
     *
     * @return array<string, string>
     */
    public function contextoDeEdicion(): array;

    /**
     * La frase completa que se lee antes de eliminar, con la consecuencia ya
     * calculada (documento 3, 6.3). La escribe `ConfirmacionDeBorrado`.
     */
    public function confirmacionDeBorrado(): string;
}
