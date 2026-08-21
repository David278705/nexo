<?php

namespace App\Novedades;

use App\Autorizacion\PoliticaDeModulos;
use App\Contratacion\FichaDeEmpleado;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vinculacion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\OptionalProp;

/**
 * El primer paso del flujo de novedades: quién es el empleado (documento 3,
 * sección 5.5, pasos 2 a 4).
 *
 * El usuario escribe un número de documento y, **al salir del campo**, el
 * servidor responde una de tres cosas: que no corresponde a nadie, que
 * corresponde a alguien que ya no está activo, o la ficha del empleado con lo
 * que hace falta ver antes de registrarle una novedad.
 *
 * ## Por qué vive en el servidor y se pide con una recarga parcial
 *
 * Porque el navegador no puede —ni debe— tener la lista de empleados de la
 * empresa para buscar en ella, y porque el saldo de vacaciones **no se calcula
 * en el navegador jamás** (regla 2 del proyecto). La prop se comparte como
 * `Inertia::optional()`, así que no cuesta nada en la carga de la pantalla y solo
 * se resuelve cuando el modal la pide con `router.reload({ only: [...] })`. Sin
 * una sola llamada de `axios`, que es la regla de interfaz.
 *
 * ## Por qué se comparte y no la sirve cada controlador
 *
 * El flujo es el mismo en los cinco módulos, y cinco controladores armando la
 * misma búsqueda son cinco fichas que se separan a la primera corrección. Se
 * comparte una vez en `HandleInertiaRequests` y cualquier pantalla del cliente
 * la tiene; el componente `ModalDeNovedad` es el único que la pide.
 *
 * ## Quién puede pedirla
 *
 * Solo quien pueda registrar alguna de las cinco novedades. La ficha enseña
 * cargo, fecha de ingreso y saldo de vacaciones: no es un dato público de la
 * empresa y no se le resuelve a quien no tiene ningún módulo de novedades.
 */
final class EmpleadoDeNovedad
{
    /**
     * El nombre de la prop. Es el mismo en el servidor y en el componente, y por
     * eso es una constante y no un texto escrito dos veces.
     */
    public const PROP = 'empleadoDeNovedad';

    /**
     * El texto de ayuda bajo el campo, literal del prototipo.
     */
    public const AYUDA = 'El empleado debe estar registrado y activo. Si acaba de ingresar y aún no está en el sistema, regístralo primero.';

    /**
     * El error de la sección 5.5, paso 3, literal del prototipo.
     */
    public const NO_EXISTE = 'El documento :documento no corresponde a ningún empleado activo. Verifica el número o registra primero al empleado.';

    /**
     * El mismo caso visto con más información: la persona existe, pero su
     * vinculación ya terminó. El documento pide un solo mensaje —«si no existe o
     * no está activo»—; se separa porque decirle a quien digita que la persona
     * sí está en el sistema le ahorra volver a buscarla, y porque el remedio es
     * otro: registrar una vinculación nueva, no corregir el número.
     */
    public const INACTIVO = ':nombre está registrada en el sistema, pero su vinculación ya no está activa. No se registran novedades sobre una persona inactiva; si volvió a la empresa, regístrala como un ingreso nuevo.';

    /**
     * La prop compartida con todas las pantallas del cliente.
     */
    public static function prop(Request $peticion): OptionalProp
    {
        return Inertia::optional(fn (): ?array => self::para(
            $peticion->user(),
            self::documentoConsultado($peticion),
        ));
    }

    /**
     * La respuesta a un documento digitado, o `null` cuando no se ha digitado
     * nada todavía.
     *
     * @return array{documento: string, encontrado: bool, error: string|null, vinculacion: int|null, nombre: string|null, datos: list<array{etiqueta: string, valor: string, pendiente: bool, nota: string}>}|null
     */
    public static function para(?User $usuario, ?string $numeroDeDocumento): ?array
    {
        $documento = trim((string) $numeroDeDocumento);

        if ($documento === '' || ! $usuario instanceof User || ! self::puedeRegistrarNovedades($usuario)) {
            return null;
        }

        $persona = Persona::query()
            ->conDocumento($documento)
            ->with('vinculaciones')
            ->first();

        if (! $persona instanceof Persona) {
            return self::sinEmpleado($documento, str_replace(':documento', $documento, self::NO_EXISTE));
        }

        $vinculacion = $persona->vinculaciones
            ->first(fn (Vinculacion $vinculacion): bool => $vinculacion->estaActiva());

        if (! $vinculacion instanceof Vinculacion) {
            return self::sinEmpleado(
                $documento,
                str_replace(':nombre', $persona->nombreCompleto(), self::INACTIVO),
            );
        }

        return [
            'documento' => $documento,
            'encontrado' => true,
            'error' => null,
            'vinculacion' => (int) $vinculacion->getKey(),
            'nombre' => $persona->nombreCompleto(),
            'datos' => self::ficha($vinculacion),
        ];
    }

    /**
     * La franja que encabeza el modal grande: nombre, documento, cargo, fecha de
     * ingreso y saldo de vacaciones (documento 3, 5.5, paso 4).
     *
     * La arma `FichaDeEmpleado`, que es quien ya sabía armar el resumen de la
     * pantalla del empleado. Aquí no se formatea ni se cuenta nada.
     *
     * @return list<array{etiqueta: string, valor: string, pendiente: bool, nota: string}>
     */
    private static function ficha(Vinculacion $vinculacion): array
    {
        return FichaDeEmpleado::de($vinculacion)->resumenParaNovedad();
    }

    /**
     * @return array{documento: string, encontrado: bool, error: string, vinculacion: null, nombre: null, datos: list<never>}
     */
    private static function sinEmpleado(string $documento, string $error): array
    {
        return [
            'documento' => $documento,
            'encontrado' => false,
            'error' => $error,
            'vinculacion' => null,
            'nombre' => null,
            'datos' => [],
        ];
    }

    /**
     * ¿Este usuario registra alguna de las cinco novedades?
     *
     * Se pregunta a la misma política que el middleware `modulo:`, así que la
     * respuesta no puede discrepar de la del resto del sistema.
     */
    private static function puedeRegistrarNovedades(User $usuario): bool
    {
        $politica = app(PoliticaDeModulos::class);

        foreach (TipoDeNovedad::modulos() as $modulo) {
            if ($politica->puedeAcceder($usuario, $modulo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * El documento que llega en la consulta, solo si llegó como texto.
     *
     * El modal manda lo que haya en el campo, y eso puede ser un arreglo si
     * alguien manipula la dirección. Se descarta sin ruido, como en la
     * advertencia de reingreso: esto es una búsqueda, no una validación de
     * formulario.
     */
    private static function documentoConsultado(Request $peticion): ?string
    {
        $valor = $peticion->query('documento');

        return is_string($valor) ? $valor : null;
    }

    /**
     * Lo fijo del flujo: la etiqueta del campo, su ayuda y el título de cada uno
     * de los cinco módulos.
     *
     * Va como prop compartida y no escrito dentro del componente Vue por lo
     * mismo que la advertencia de reingreso: los textos del sistema viven en el
     * servidor, donde una prueba puede comprobar que no se les cambió una coma.
     * Son cadenas fijas, así que no cuestan una consulta.
     *
     * @return array<string, mixed>
     */
    public static function textos(): array
    {
        return [
            'etiqueta' => 'Número de documento del empleado',
            'ayuda' => self::AYUDA,
            'tipos' => array_map(
                fn (TipoDeNovedad $tipo): array => $tipo->paraLaPantalla(),
                TipoDeNovedad::cases(),
            ),
        ];
    }
}
