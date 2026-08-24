<?php

namespace App\Proveedor;

use App\Models\NotaCliente;
use App\Models\Tenant;
use App\Models\User;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * La bitácora interna sobre un cliente (documento 4, 4.3, pestaña «Bitácora»).
 *
 * «Notas internas del equipo sobre ese cliente: llamadas, quejas, promesas
 * hechas. Nadie recuerda dentro de seis meses qué se le prometió a quién.»
 *
 * Dos cosas la definen y las dos son decisiones, no omisiones:
 *
 * - **Es interna.** No se le enseña al cliente por ninguna vía, y por eso
 *   `NotaCliente` está en la lista `SIN_AUDITAR` del observador: la línea de
 *   auditoría llevaría el texto de la nota a la auditoría de esa empresa, que
 *   es la única tabla del sistema que el cliente sí puede leer entera.
 * - **Una nota no se edita.** Es un registro de lo que pasó, como la auditoría
 *   o los accesos: lo que se escribió en agosto tiene que seguir diciendo en
 *   febrero lo que se escribió en agosto. Lo que sí se puede es quitarla —con
 *   borrado lógico, regla 4—, que es lo que hace falta cuando se guardó en la
 *   ficha equivocada.
 */
class BitacoraDelCliente
{
    public const TITULO = 'Bitácora del cliente';

    public const AYUDA = 'Es interna del equipo: el cliente no la ve. Llamadas, quejas y promesas '
        .'hechas, para que dentro de seis meses se sepa qué se le prometió a quién.';

    /**
     * La ayuda del campo, no un marcador dentro de él: la regla de interfaz
     * pide etiquetas reales y no texto que desaparece al escribir.
     */
    public const AYUDA_DE_LA_NOTA = 'Lo que se habló, en una o dos frases. Queda con tu nombre y '
        .'con la fecha de hoy, y no se puede editar después.';

    public const VACIA_TITULO = 'Todavía no hay notas de este cliente';

    public const VACIA_DESCRIPCION = 'Aquí queda lo que se habló con él: llamadas, quejas y lo que '
        .'se le prometió. La primera nota suele ser la de la reunión de puesta en marcha.';

    private function __construct(private readonly Tenant $empresa) {}

    public static function de(Tenant $empresa): self
    {
        return new self($empresa);
    }

    /**
     * Escribe una nota. La fecha la pone el servidor: es el día en que se
     * escribió y no un campo que se pueda mover.
     */
    public function anotar(User $autor, string $texto): NotaCliente
    {
        return NotaCliente::query()->create([
            'tenant_id' => $this->empresa->getKey(),
            'user_id' => $autor->getKey(),
            'fecha' => Carbon::today(),
            'texto' => $texto,
        ]);
    }

    /**
     * La nota de esta empresa con ese identificador, o null.
     *
     * La empresa se comprueba aquí y no en el controlador por lo mismo que en
     * las dos acciones sobre usuarios de la ficha: la dirección lleva los dos
     * identificadores por separado y sin esta comprobación bastaría cambiar un
     * número para borrar la nota de otro cliente desde esta ficha.
     */
    public function nota(int $identificador): ?NotaCliente
    {
        $nota = NotaCliente::query()
            ->where('tenant_id', $this->empresa->getKey())
            ->find($identificador);

        return $nota instanceof NotaCliente ? $nota : null;
    }

    /**
     * La línea de tiempo del prototipo: la más reciente arriba.
     *
     * @return list<array<string, mixed>>
     */
    public function notas(): array
    {
        return NotaCliente::query()
            ->with('autor')
            ->where('tenant_id', $this->empresa->getKey())
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (NotaCliente $nota): array => [
                'id' => $nota->getKey(),
                'fecha' => Formato::fecha($nota->fecha),
                'autor' => (string) ($nota->autor()->first()?->getAttribute('name') ?? 'El proveedor'),
                'texto' => $nota->texto,
            ])
            ->values()
            ->all();
    }

    /**
     * Lo que se le dice al owner después de escribir una nota.
     *
     * Nombra la empresa por lo mismo que la confirmación de los parámetros: en
     * el panel se miran varias en la misma sesión.
     */
    public static function confirmacion(Tenant $empresa): string
    {
        return 'Nota guardada en la bitácora de '.$empresa->getAttribute('nombre')
            .'. Es interna: el cliente no la ve.';
    }

    public const CONFIRMACION_DE_BORRADO = 'Se quitó la nota. Sigue guardada en la base de datos '
        .'—aquí nada se borra de verdad—, pero ya no aparece en la bitácora.';
}
