<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pantalla que ve un usuario cuyo servicio está suspendido (documento 4,
 * capítulo 6).
 *
 * Es una sola dirección y es la única que `BloquearEmpresaSuspendida` deja
 * pasar, junto con cerrar sesión. Dice tres cosas y ninguna más: qué pasó, que
 * su información está intacta, y que puede pedirla.
 *
 * Esa última frase no es un detalle de cortesía: la sección A.4 del documento 6
 * lo dice con esas palabras —«retener la información como presión por facturas
 * impagadas es mal negocio y jurídicamente delicado; la vía correcta ante el
 * impago es suspender el servicio, no retener los datos»— y el capítulo 6 lo
 * repite: «el cliente en mora puede pedir su información y hay que
 * entregársela». Escribirlo en la pantalla es lo que hace que la promesa no
 * dependa de que alguien se acuerde.
 *
 * Quien llega aquí sin estar suspendido —porque acaban de reactivarle— se va al
 * inicio: la pantalla no puede quedarse contando algo que ya no es cierto.
 */
class ServicioSuspendidoController extends Controller
{
    public const TITULO = 'El servicio está suspendido';

    public const EXPLICACION = 'La suscripción de tu empresa está suspendida, así que por ahora '
        .'nadie puede entrar al sistema.';

    /**
     * Las tres cosas que hay que saber estando aquí.
     *
     * @var list<string>
     */
    public const PUNTOS = [
        'No se borró nada. Toda la información de tu empresa sigue completa y los cálculos '
            .'siguen corriendo por dentro.',
        'Al reactivar el servicio, todo sigue donde estaba: no hay que volver a cargar ni a '
            .'registrar nada.',
        'Si necesitas tu información mientras el servicio está suspendido, puedes pedirla y se '
            .'te entrega. Nunca se retiene como forma de presión.',
    ];

    public function mostrar(): Response|RedirectResponse
    {
        $empresa = tenant_actual();

        if (! $empresa instanceof Tenant || ! $empresa->estaSuspendida()) {
            return redirect()->route('inicio');
        }

        return Inertia::render('ServicioSuspendido', [
            'empresa' => (string) $empresa->getAttribute('nombre'),
            'titulo' => self::TITULO,
            'explicacion' => self::EXPLICACION,
            'puntos' => self::PUNTOS,
        ]);
    }
}
