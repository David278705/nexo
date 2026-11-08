<?php

namespace App\CargaInicial\Plantillas;

use App\CargaInicial\CatalogosDeCarga;
use App\CargaInicial\Columna;
use App\CargaInicial\ErrorDeCarga;
use App\CargaInicial\FilaTraducida;
use App\CargaInicial\PlantaDeLaEmpresa;
use App\CargaInicial\Plantilla;
use App\CargaInicial\TextoDeCelda;
use App\Contratacion\EventoContractual;
use App\Contratacion\Genero;
use App\Contratacion\RegistroDeEmpleado;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoDocumento;
use App\Contratacion\TipoRiesgo;
use App\Http\Requests\RegistrarEmpleado;
use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Eps;
use App\Models\FondoPension;
use App\Models\Pais;
use App\Models\Vinculacion;
use Illuminate\Support\Carbon;

/**
 * La plantilla 1, «Empleados actuales», la única obligatoria y la que las otras
 * cinco necesitan cargada antes de poder cargarse ellas (documento 2, 6.3).
 *
 * Sus treinta y dos columnas acaban en los mismos campos que digita el asistente
 * de cuatro pasos, y sus reglas **son las de `RegistrarEmpleado`**, sin copiar
 * ninguna. Lo que la plantilla añade son cuatro cosas que el formulario no tiene
 * porque no las necesita:
 *
 * - **El saldo de vacaciones es obligatorio.** Es la única ausencia que el
 *   documento 2, 6.2 manda tratar como bloqueante, y la plantilla lo dice con
 *   mayúsculas: «el dato más importante de toda la carga». Sin él el saldo
 *   arrancaría en cero y quedaría radicalmente equivocado desde el primer día.
 * - **La fecha de ingreso no puede ser futura.** Lo pide el documento 2, 6.2 y
 *   lo justifica la propia plantilla: aquí van «únicamente las personas
 *   vinculadas hoy». El formulario sí admite una fecha futura, porque registrar
 *   a quien empieza el lunes es legítimo; cargarla en el histórico inicial no.
 * - **Hasta seis prórrogas del contrato a término fijo**, en pares de fecha de
 *   inicio y fecha de fin (documento 6, D.7 y HD-09). Antes de esta tarea la
 *   plantilla solo guardaba un número de prórrogas y la fecha de inicio del
 *   periodo vigente, y ese dato nunca se convertía en un tramo del historial:
 *   con un contrato de varias prórrogas no había dónde poner cuándo empezó y
 *   terminó cada una. Ahora cada par se convierte en un tramo real de
 *   `periodos_contractuales`, por el mismo camino que «Renovar contrato»
 *   (`escribir()`), así que el tope de cuatro años y el cupo de prórrogas
 *   cortas (documento 6, C.1 y C.2) se calculan sobre el historial de verdad.
 * - **Ningún documento se repite dentro del archivo** (documento 2, 6.2).
 *
 * **Lo que no se toca es la regla 5 del proyecto.** El documento es único por
 * empresa en `personas`, así que un documento repetido en el archivo es un
 * error del archivo, no dos personas.
 */
class PlantillaDeEmpleados extends Plantilla
{
    /**
     * La clave con la que las otras cinco preguntan si esta ya se procesó
     * (documento 2, 6.3). Es constante y no solo el retorno de `clave()` para
     * que la comprobación de la dependencia no tenga que construir la plantilla.
     */
    public const CLAVE = 'empleados';

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'Empleados actuales';
    }

    public function hoja(): string
    {
        return 'Empleados';
    }

    public function columnas(): array
    {
        return [
            Columna::lista('Tipo de documento', 'tipo_documento', TipoDocumento::paraElegir()),
            Columna::texto('Número de documento', 'numero_documento'),
            Columna::texto('Primer nombre', 'primer_nombre'),
            Columna::texto('Segundo nombre', 'segundo_nombre'),
            Columna::texto('Primer apellido', 'primer_apellido'),
            Columna::texto('Segundo apellido', 'segundo_apellido'),
            Columna::lista('Género', 'genero', Genero::paraElegir()),
            Columna::fecha('Fecha de nacimiento', 'fecha_nacimiento'),

            Columna::texto('País de nacimiento', 'nacimiento.pais'),
            Columna::texto('Departamento de nacimiento', 'nacimiento.departamento'),
            Columna::texto('Ciudad de nacimiento', 'nacimiento.ciudad'),

            Columna::fecha('Fecha de expedición del documento', 'fecha_expedicion_documento'),
            Columna::texto('País de expedición del documento', 'expedicion.pais'),
            Columna::texto('Departamento de expedición del documento', 'expedicion.departamento'),
            Columna::texto('Ciudad de expedición del documento', 'expedicion.ciudad'),

            Columna::texto('Dirección de residencia', 'direccion'),
            Columna::texto('Departamento de residencia', 'residencia.departamento'),
            Columna::texto('Ciudad de residencia', 'residencia.ciudad'),
            Columna::texto('Celular', 'celular'),
            Columna::texto('Correo electrónico', 'correo'),

            Columna::catalogo('EPS', 'eps_id', Eps::class),
            Columna::catalogo('Fondo de pensión', 'fondo_pension_id', FondoPension::class),
            Columna::catalogo('ARL', 'arl_id', Arl::class),
            Columna::lista('Nivel de riesgo', 'tipo_riesgo', TipoRiesgo::paraElegir(), self::NIVELES_DE_RIESGO),
            Columna::catalogo('Caja de compensación', 'caja_compensacion_id', CajaCompensacion::class),

            Columna::texto('Cargo', 'cargo'),
            Columna::lista('Tipo de contrato', 'tipo_contrato', TipoContrato::paraElegir()),
            Columna::fecha('Fecha de ingreso', 'fecha_ingreso'),
            Columna::fecha('Fecha de finalización del contrato', 'fecha_fin'),
            ...$this->columnasDeProrrogas(),

            Columna::dinero('Salario mensual', 'salario'),
            Columna::dinero('Bonificación mensual', 'bonificacion'),
            Columna::catalogo('Entidad bancaria', 'banco_id', Banco::class),

            Columna::decimal('Días de vacaciones disponibles hoy', 'saldo_inicial_vacaciones'),

            // Solo obra o labor y opcional (`PROD-23`). Va al final del archivo
            // y no junto a la fecha de finalización: insertarla en medio habría
            // corrido las validaciones de cada celda de la plantilla real, y el
            // lector encuentra las columnas por su título, no por su posición.
            Columna::fecha('Fecha de finalización estimada', 'fecha_fin_estimada'),
        ];
    }

    /**
     * Las plantillas escriben el nivel de riesgo con su número romano a secas y
     * el sistema lo guarda con su descripción («Riesgo I – Mínimo»). Las dos
     * formas señalan el mismo nivel sin ninguna ambigüedad.
     *
     * @var array<string, string>
     */
    private const NIVELES_DE_RIESGO = [
        'I' => 'riesgo_i',
        'II' => 'riesgo_ii',
        'III' => 'riesgo_iii',
        'IV' => 'riesgo_iv',
        'V' => 'riesgo_v',
        'Riesgo I' => 'riesgo_i',
        'Riesgo II' => 'riesgo_ii',
        'Riesgo III' => 'riesgo_iii',
        'Riesgo IV' => 'riesgo_iv',
        'Riesgo V' => 'riesgo_v',
    ];

    /**
     * Cuántas prórrogas caben en la plantilla (documento 6, D.7, y HD-09).
     */
    private const MAXIMO_DE_PRORROGAS = 6;

    public function completar(FilaTraducida $fila, CatalogosDeCarga $catalogos, PlantaDeLaEmpresa $planta): void
    {
        $this->nacimiento($fila, $catalogos);
        $this->residencia($fila, $catalogos);
        $this->expedicion($fila, $catalogos);
        $this->prorrogas($fila);
    }

    public function reglas(FilaTraducida $fila): array
    {
        return [
            ...$this->formulario($fila)->rules(),
            ...$this->delArchivo(),
        ];
    }

    public function atributos(): array
    {
        return [
            ...$this->formulario(new FilaTraducida(0))->attributes(),
            'saldo_inicial_vacaciones' => 'días de vacaciones disponibles hoy',
        ];
    }

    public function mensajes(): array
    {
        return [
            ...$this->formulario(new FilaTraducida(0))->messages(),
            'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura: en esta plantilla van únicamente las personas vinculadas hoy.',
            'saldo_inicial_vacaciones.required' => 'Los días de vacaciones disponibles hoy son obligatorios: sin ese dato el saldo de esta persona arrancaría en cero.',
        ];
    }

    public function revisarElConjunto(array $filas, PlantaDeLaEmpresa $planta): array
    {
        $errores = [];
        $vistos = [];

        foreach ($filas as $fila) {
            $documento = $fila->valor('numero_documento');

            if (! is_string($documento)) {
                continue;
            }

            if (isset($vistos[$documento])) {
                $errores[] = new ErrorDeCarga(
                    $fila->numero,
                    'Número de documento',
                    "El documento {$documento} ya está en la fila {$vistos[$documento]}. Una persona ocupa una sola fila de esta plantilla.",
                );

                continue;
            }

            $vistos[$documento] = $fila->numero;

            // Y tampoco puede estar ya en la empresa. La carga es «un proceso
            // de una sola vez» (documento 1, 13.1) y volver a pasar el mismo
            // archivo no falla por sí solo: abriría una segunda vinculación
            // activa a cada persona, que es la clase de duplicado que nadie ve
            // hasta que el listado enseña a la planta entera repetida.
            if ($planta->vinculacionActiva($documento) !== null) {
                $errores[] = new ErrorDeCarga(
                    $fila->numero,
                    'Número de documento',
                    "El documento {$documento} ya está vinculado y activo en esta empresa. ".
                    'Quítelo del archivo: esta plantilla trae quienes todavía no están en el sistema.',
                );
            }
        }

        return $errores;
    }

    /**
     * La vinculación entra por donde entra la del asistente de cuatro pasos:
     * `RegistroDeEmpleado`, que reutiliza la persona si el documento ya existía
     * y deja que el estado nazca activo solo (regla 5.3).
     *
     * Lo único que la carga añade son los dos datos del saldo, y **ninguno de
     * los dos es un saldo guardado**: son el punto de partida del cálculo, que
     * es justo lo que la regla 2 del proyecto permite. El corte es la fecha de
     * la carga porque la plantilla pregunta por los «días de vacaciones
     * disponibles **hoy**»; a partir de ahí el motor sigue acumulando solo.
     *
     * **Las dos columnas del contrato vigente no se convierten en tramos del
     * historial y es una decisión, no un olvido.** «Fecha de inicio del contrato
     * vigente» y «Prórrogas ya realizadas» viven en el sistema en
     * `periodos_contractuales`, una fila por tramo con sus dos fechas. Con un
     * número de prórrogas y una sola fecha, reconstruir la cadena obligaría a
     * inventarse las fechas de los tramos intermedios, y el principio 13.1 del
     * documento 1 lo prohíbe con esas palabras: «no se inventan registros».
     *
     * **Las prórrogas sí se convierten en tramos del historial (HD-09).**
     * `registrarProrrogas()` las agrega una por una, en orden, por el mismo
     * camino que agregaría «Renovar contrato» desde la pantalla.
     */
    public function escribir(array $valores, Carbon $fechaDeLaCarga): void
    {
        $vinculacion = app(RegistroDeEmpleado::class)->ejecutar([
            ...$valores,
            'fecha_corte_saldo' => $fechaDeLaCarga->toDateString(),
        ]);

        $this->registrarProrrogas($vinculacion, $valores);
    }

    /**
     * Reconstruye tramo a tramo las prórrogas que trae la carga (HD-09), por
     * el mismo camino que las registraría «Renovar contrato» en pantalla:
     * `Vinculacion::registrarPeriodo()`. Así una prórroga cargada y una
     * digitada acaban idénticas en `periodos_contractuales`, y el tope de
     * cuatro años y el cupo de prórrogas cortas (documento 6, C.1 y C.2) se
     * calculan sobre el historial real.
     *
     * **El tipo de prórroga se registra como pactada.** La plantilla no
     * pregunta si cada prórroga fue pactada por escrito o llegó por silencio
     * —solo pide sus dos fechas—, y la distinción no cambia ningún cálculo:
     * C.1 ya estableció que el cupo de tres prórrogas cortas es de la cadena
     * entera, sin mirar el tipo. Aquí solo decide el texto con el que se lee
     * el tramo en el historial.
     *
     * **El periodo inicial nació hace un instante con la fecha de
     * finalización vigente**, porque `ObservadorDeVinculacion` no sabe de
     * prórrogas. Si hay al menos una, su fin real es el día anterior al
     * inicio de la primera —los tramos son siempre contiguos, la misma regla
     * con la que `Vinculacion::renovar()` calcula dónde empieza el
     * siguiente— y se corrige antes de encadenarlas.
     *
     * @param  array<string, mixed>  $valores
     */
    private function registrarProrrogas(Vinculacion $vinculacion, array $valores): void
    {
        /** @var array<int, array{inicio?: string|null, fin?: string|null}> $todas */
        $todas = $valores['prorrogas'] ?? [];

        $completas = array_filter(
            $todas,
            fn (array $tramo): bool => isset($tramo['inicio'], $tramo['fin']),
        );

        if ($completas === []) {
            return;
        }

        ksort($completas);

        $primera = reset($completas);

        $inicial = $vinculacion->periodos()->where('orden', 1)->first();
        $inicial?->update(['fecha_fin' => Carbon::parse((string) $primera['inicio'])->subDay()]);

        foreach ($completas as $tramo) {
            $vinculacion->registrarPeriodo(
                EventoContractual::ProrrogaPactada,
                TipoContrato::Fijo,
                Carbon::parse((string) $tramo['inicio']),
                Carbon::parse((string) $tramo['fin']),
            );
        }
    }

    public function dependeDeLaPlanta(): bool
    {
        return false;
    }

    public function fechaDeCorte(Carbon $fechaDeLaCarga): ?Carbon
    {
        return $fechaDeLaCarga->copy();
    }

    /**
     * El formulario de registro de empleado alimentado con la fila, para que
     * sus reglas se decidan con los mismos datos con los que se decidirían en
     * pantalla: si el país es Colombia y si el contrato es a término fijo.
     */
    private function formulario(FilaTraducida $fila): RegistrarEmpleado
    {
        return RegistrarEmpleado::create('/', 'POST', $fila->valores());
    }

    /**
     * Lo que la plantilla exige y el formulario no, porque son condiciones del
     * archivo y no de la pantalla.
     *
     * @return array<string, mixed>
     */
    private function delArchivo(): array
    {
        return [
            'fecha_ingreso' => ['required', 'date', 'before_or_equal:today'],

            // El único campo cuya ausencia bloquea la carga (documento 2, 6.2).
            'saldo_inicial_vacaciones' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * El lugar de nacimiento sigue la regla 5.1.1: encadenado si es Colombia,
     * texto libre si no.
     */
    private function nacimiento(FilaTraducida $fila, CatalogosDeCarga $catalogos): void
    {
        $escrito = $fila->valor('nacimiento.pais');

        if (! is_string($escrito)) {
            return;
        }

        $codigo = $catalogos->pais($escrito);

        if ($codigo === null) {
            $fila->error(
                'País de nacimiento',
                "«{$escrito}» no está en la lista de países del sistema.",
                'nacimiento.pais',
            );

            return;
        }

        $fila->poner('nacimiento.pais', $codigo);

        // Fuera de Colombia el departamento y la ciudad se quedan como los
        // escribió el cliente: el sistema no tiene con qué comprobarlos y la
        // responsabilidad de que estén bien es suya (documento 6, A.2).
        if ($codigo === Pais::COLOMBIA) {
            $this->enColombia($fila, 'nacimiento', 'Departamento de nacimiento', 'Ciudad de nacimiento', $catalogos);
        }
    }

    /**
     * La residencia es siempre en Colombia: el sistema solo gestiona contratos
     * laborales colombianos, y por eso la plantilla no trae país de residencia.
     */
    private function residencia(FilaTraducida $fila, CatalogosDeCarga $catalogos): void
    {
        $this->enColombia($fila, 'residencia', 'Departamento de residencia', 'Ciudad de residencia', $catalogos);
    }

    /**
     * El lugar de expedición del documento sigue la regla 5.1.1, igual que el
     * nacimiento y la residencia: el país decide si el departamento y la
     * ciudad se resuelven contra el catálogo o se dejan como texto libre, cada
     * uno en su propia columna (HD-08). Sin país escrito se asume Colombia,
     * que es el comportamiento que ya tenía la plantilla.
     */
    private function expedicion(FilaTraducida $fila, CatalogosDeCarga $catalogos): void
    {
        $paisEscrito = $fila->valor('expedicion.pais');

        $codigo = is_string($paisEscrito) && $paisEscrito !== ''
            ? $catalogos->pais($paisEscrito)
            : Pais::COLOMBIA;

        if ($codigo === null) {
            $fila->error(
                'País de expedición del documento',
                "«{$paisEscrito}» no está en la lista de países del sistema.",
                'expedicion.pais',
            );

            return;
        }

        $fila->poner('expedicion.pais', $codigo);

        // Fuera de Colombia el departamento y la ciudad quedan como texto
        // libre, igual que el nacimiento (documento 6, A.2): el sistema no
        // tiene con qué comprobarlos y la responsabilidad de que estén bien
        // es del cliente.
        if ($codigo === Pais::COLOMBIA) {
            $this->enColombia($fila, 'expedicion', 'Departamento de expedición del documento', 'Ciudad de expedición del documento', $catalogos);
        }
    }

    /**
     * Un departamento y su municipio, encadenados como en el formulario: la
     * ciudad se busca dentro del departamento y no en el país entero.
     */
    private function enColombia(
        FilaTraducida $fila,
        string $prefijo,
        string $tituloDepartamento,
        string $tituloCiudad,
        CatalogosDeCarga $catalogos,
    ): void {
        $escrito = $fila->valor($prefijo.'.departamento');

        if (! is_string($escrito)) {
            return;
        }

        $departamento = $catalogos->departamento($escrito);

        if ($departamento === null) {
            $motivo = $this->esBogota($escrito)
                ? $this->motivoDeBogota($tituloDepartamento, $tituloCiudad)
                : "«{$escrito}» no es un departamento de Colombia.";

            $this->sinResolver($fila, $prefijo, $tituloDepartamento, $motivo);

            return;
        }

        $fila->poner($prefijo.'.departamento', $departamento);

        $this->municipioDe($fila, $prefijo, $departamento, $fila->valor($prefijo.'.ciudad'), $tituloCiudad, $catalogos, $tituloDepartamento);
    }

    private function municipioDe(
        FilaTraducida $fila,
        string $prefijo,
        int $departamento,
        mixed $escrito,
        string $titulo,
        CatalogosDeCarga $catalogos,
        ?string $tituloDepartamento = null,
    ): void {
        if (! is_string($escrito)) {
            return;
        }

        $ciudad = $catalogos->ciudadEn($departamento, $escrito);

        if ($ciudad === null) {
            $motivo = $this->esBogota($escrito)
                ? $this->motivoDeBogota($tituloDepartamento ?? $titulo, $titulo)
                : "«{$escrito}» no es un municipio de ".(string) $catalogos->nombreDeDepartamento($departamento).'.';

            $this->sinResolver($fila, $prefijo, $titulo, $motivo);

            return;
        }

        $fila->poner($prefijo.'.departamento', $departamento);
        $fila->poner($prefijo.'.ciudad', $ciudad);
    }

    /**
     * Si lo que se escribió es alguna forma de «Bogotá»: es el único caso en
     * que el rechazo genérico —«no es un departamento/municipio»— no basta,
     * porque Bogotá no es ninguna de las dos cosas que busca quien la escribe
     * ahí. Es Distrito Capital, y el catálogo la tiene con su propio nombre,
     * «Bogotá D.C.» (documento 6, D.7).
     */
    private function esBogota(string $escrito): bool
    {
        $comparable = (string) preg_replace('/[^a-z ]/', '', TextoDeCelda::comparable($escrito));
        $comparable = trim((string) preg_replace('/\s+/', ' ', $comparable));

        return in_array($comparable, ['bogota', 'bogota dc'], true);
    }

    /**
     * El motivo específico de Bogotá, con el nombre exacto que hay que
     * escribir y en qué columna. Si las dos columnas fueran la misma, la
     * instrucción no se repetiría.
     */
    private function motivoDeBogota(string $tituloDepartamento, string $tituloCiudad): string
    {
        $instruccion = $tituloDepartamento === $tituloCiudad
            ? "Escriba «Bogotá D.C.» en «{$tituloDepartamento}»."
            : "Escriba «Bogotá D.C.» en «{$tituloDepartamento}» y «Bogotá D.C.» en «{$tituloCiudad}».";

        return 'Bogotá es Distrito Capital, no un municipio de ningún departamento: se busca como «Bogotá D.C.». '.$instruccion;
    }

    /**
     * Una ubicación que no se pudo resolver: se anota el motivo y **los dos
     * campos se vacían**.
     *
     * Vaciarlos no es cosmética. Las reglas del formulario comprueban el
     * departamento y el municipio contra sus catálogos por identificador, y
     * dejar ahí el nombre que escribió el cliente haría que la comprobación
     * buscara un número donde hay letras. Un error de digitación no puede
     * terminar en un error de base de datos.
     */
    private function sinResolver(FilaTraducida $fila, string $prefijo, string $titulo, string $motivo): void
    {
        $fila->poner($prefijo.'.departamento', null);
        $fila->poner($prefijo.'.ciudad', null);

        $fila->error($titulo, $motivo, $prefijo.'.departamento', $prefijo.'.ciudad');
    }

    /**
     * Los pares de fecha de inicio y fecha de fin de las hasta seis prórrogas
     * (documento 6, D.7, y HD-09), en un bloque marcado «solo para término
     * fijo» del prototipo real.
     *
     * @return list<Columna>
     */
    private function columnasDeProrrogas(): array
    {
        $columnas = [];

        for ($n = 1; $n <= self::MAXIMO_DE_PRORROGAS; $n++) {
            $columnas[] = Columna::fecha("Fecha de inicio de la prórroga {$n}", "prorrogas.{$n}.inicio");
            $columnas[] = Columna::fecha("Fecha de fin de la prórroga {$n}", "prorrogas.{$n}.fin");
        }

        return $columnas;
    }

    /**
     * Valida el bloque de prórrogas de la fila: que sean solo del contrato a
     * término fijo, que no salten ninguna y que encadenen sin huecos con la
     * fecha de ingreso y con «Fecha de finalización del contrato» (HD-09).
     */
    private function prorrogas(FilaTraducida $fila): void
    {
        $numeros = $this->prorrogasEscritas($fila);

        if ($numeros === null || $numeros === [] || $fila->yaFallo('tipo_contrato')) {
            return;
        }

        if ($fila->valor('tipo_contrato') !== TipoContrato::Fijo->value) {
            foreach ($numeros as $n) {
                $fila->error(
                    "Fecha de inicio de la prórroga {$n}",
                    'Las columnas de prórroga son solo para el contrato a término fijo: en las demás modalidades se dejan vacías.',
                    "prorrogas.{$n}.inicio",
                    "prorrogas.{$n}.fin",
                );
            }

            return;
        }

        $this->encadenarProrrogas($fila, $numeros);
    }

    /**
     * Los números de prórroga con sus dos fechas completas, en orden.
     *
     * Nulo cuando ya se anotó un error de par incompleto o de prórroga
     * saltada y no hay más que comprobar en esta fila. Una celda que ya
     * falló al leerse (una fecha ilegible, por ejemplo) tampoco se vuelve a
     * juzgar aquí: ese error ya quedó anotado por quien la leyó.
     *
     * @return list<int>|null
     */
    private function prorrogasEscritas(FilaTraducida $fila): ?array
    {
        $numeros = [];
        $huecoDesde = null;

        for ($n = 1; $n <= self::MAXIMO_DE_PRORROGAS; $n++) {
            $campoInicio = "prorrogas.{$n}.inicio";
            $campoFin = "prorrogas.{$n}.fin";

            if ($fila->yaFallo($campoInicio) || $fila->yaFallo($campoFin)) {
                return null;
            }

            $inicio = $fila->valor($campoInicio);
            $fin = $fila->valor($campoFin);

            if ($inicio === null && $fin === null) {
                $huecoDesde ??= $n;

                continue;
            }

            if ($inicio === null || $fin === null) {
                $fila->error(
                    "Fecha de inicio de la prórroga {$n}",
                    "La prórroga {$n} necesita las dos fechas: inicio y fin.",
                    $campoInicio,
                    $campoFin,
                );

                return null;
            }

            if ($huecoDesde !== null) {
                $fila->error(
                    "Fecha de inicio de la prórroga {$n}",
                    "No puede registrar la prórroga {$n} sin haber registrado antes la prórroga {$huecoDesde}.",
                    $campoInicio,
                    $campoFin,
                );

                return null;
            }

            $numeros[] = $n;
        }

        return $numeros;
    }

    /**
     * Que cada prórroga encadene sin huecos: la primera después de la fecha
     * de ingreso, cada una después el día siguiente a la anterior, y la
     * última exactamente en «Fecha de finalización del contrato» —que es de
     * donde sale la fecha de fin vigente de la vinculación—.
     *
     * @param  list<int>  $numeros
     */
    private function encadenarProrrogas(FilaTraducida $fila, array $numeros): void
    {
        $finDelContrato = $fila->valor('fecha_fin');

        if (! is_string($finDelContrato)) {
            // Sin fecha de finalización no hay con qué comprobar el
            // encadenamiento; su propia regla ya señala esa ausencia.
            return;
        }

        $ingresoEscrito = $fila->valor('fecha_ingreso');

        if (! is_string($ingresoEscrito)) {
            return;
        }

        $ingreso = Carbon::parse($ingresoEscrito);
        $anteriorFin = null;
        $ultimo = end($numeros);

        foreach ($numeros as $n) {
            $inicio = Carbon::parse((string) $fila->valor("prorrogas.{$n}.inicio"));
            $fin = Carbon::parse((string) $fila->valor("prorrogas.{$n}.fin"));

            if ($anteriorFin === null) {
                // La primera prórroga solo tiene que empezar después del
                // ingreso: el fin del periodo inicial se deriva de esta
                // misma fecha, no se pregunta aparte.
                if (! $inicio->greaterThan($ingreso)) {
                    $fila->error(
                        "Fecha de inicio de la prórroga {$n}",
                        "La prórroga {$n} tiene que empezar después de la fecha de ingreso.",
                        "prorrogas.{$n}.inicio",
                    );

                    return;
                }
            } elseif (! $inicio->equalTo($anteriorFin->copy()->addDay())) {
                $fila->error(
                    "Fecha de inicio de la prórroga {$n}",
                    "La prórroga {$n} tiene que empezar el ".$anteriorFin->copy()->addDay()->format('d/m/Y').
                        ', el día siguiente a la finalización de la prórroga anterior.',
                    "prorrogas.{$n}.inicio",
                );

                return;
            }

            if (! $fin->greaterThan($inicio)) {
                $fila->error(
                    "Fecha de fin de la prórroga {$n}",
                    "La prórroga {$n} tiene que terminar después de que empieza.",
                    "prorrogas.{$n}.fin",
                );

                return;
            }

            if ($n === $ultimo && ! $fin->equalTo(Carbon::parse($finDelContrato))) {
                $fila->error(
                    "Fecha de fin de la prórroga {$n}",
                    'La última prórroga registrada tiene que terminar el mismo día que la «Fecha de finalización del contrato»: '.
                        $fin->format('d/m/Y').' contra '.Carbon::parse($finDelContrato)->format('d/m/Y').'.',
                    "prorrogas.{$n}.fin",
                );

                return;
            }

            $anteriorFin = $fin;
        }
    }
}
