<?php

namespace App\CargaInicial\Plantillas;

use App\CargaInicial\CatalogosDeCarga;
use App\CargaInicial\Columna;
use App\CargaInicial\ErrorDeCarga;
use App\CargaInicial\FilaTraducida;
use App\CargaInicial\PlantaDeLaEmpresa;
use App\CargaInicial\Plantilla;
use App\CargaInicial\TextoDeCelda;
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
 * - **Las dos columnas del contrato vigente**, que en el sistema viven en el
 *   historial de periodos y no en la vinculación. Aquí se leen y se comprueban;
 *   quien las convierta en periodos contractuales es la ejecución de la carga.
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
            Columna::texto('Lugar de expedición del documento', 'expedicion.ciudad'),

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
            Columna::fecha('Fecha de inicio del contrato vigente', 'fecha_inicio_periodo_vigente'),
            Columna::entero('Prórrogas ya realizadas', 'prorrogas_realizadas'),

            Columna::dinero('Salario mensual', 'salario'),
            Columna::dinero('Bonificación mensual', 'bonificacion'),
            Columna::catalogo('Entidad bancaria', 'banco_id', Banco::class),

            Columna::decimal('Días de vacaciones disponibles hoy', 'saldo_inicial_vacaciones'),
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

    public function completar(FilaTraducida $fila, CatalogosDeCarga $catalogos, PlantaDeLaEmpresa $planta): void
    {
        $this->nacimiento($fila, $catalogos);
        $this->residencia($fila, $catalogos);
        $this->expedicion($fila, $catalogos);
    }

    public function reglas(FilaTraducida $fila): array
    {
        return [
            ...$this->formulario($fila)->rules(),
            ...$this->delArchivo($fila),
        ];
    }

    public function atributos(): array
    {
        return [
            ...$this->formulario(new FilaTraducida(0))->attributes(),
            'fecha_inicio_periodo_vigente' => 'fecha de inicio del contrato vigente',
            'prorrogas_realizadas' => 'número de prórrogas ya realizadas',
            'saldo_inicial_vacaciones' => 'días de vacaciones disponibles hoy',
        ];
    }

    public function mensajes(): array
    {
        return [
            ...$this->formulario(new FilaTraducida(0))->messages(),
            'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura: en esta plantilla van únicamente las personas vinculadas hoy.',
            'saldo_inicial_vacaciones.required' => 'Los días de vacaciones disponibles hoy son obligatorios: sin ese dato el saldo de esta persona arrancaría en cero.',
            'fecha_inicio_periodo_vigente.after_or_equal' => 'El contrato vigente no puede empezar antes de la fecha de ingreso.',
            'fecha_inicio_periodo_vigente.before' => 'El contrato vigente tiene que empezar antes de su fecha de finalización.',
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
     * **«Prórrogas ya realizadas» sí llega a la vinculación, pero no como
     * tramo (REV-09).** `RegistroDeEmpleado` la guarda aparte, en
     * `prorrogas_previas_a_la_carga`, y `HistorialDeContrato::prorrogasCortas()`
     * la suma al conteo que hace sobre los tramos de verdad: es lo único que el
     * cupo de prórrogas cortas del artículo 46 necesitaba de ese número. El
     * historial que se ve en pantalla sigue sin inventar ninguna fila.
     */
    public function escribir(array $valores, Carbon $fechaDeLaCarga): void
    {
        app(RegistroDeEmpleado::class)->ejecutar([
            ...$valores,
            'fecha_corte_saldo' => $fechaDeLaCarga->toDateString(),
        ]);
    }

    public function dependeDeLaPlanta(): bool
    {
        return false;
    }

    public function fechaDeCorte(Carbon $fechaDeLaCarga): ?Carbon
    {
        return $fechaDeLaCarga->copy();
    }

    protected function equivalencias(): array
    {
        return [
            'expedicion.departamento' => 'Lugar de expedición del documento',
        ];
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
    private function delArchivo(FilaTraducida $fila): array
    {
        $ingreso = $fila->valor('fecha_ingreso');
        $fin = $fila->valor('fecha_fin');

        $vigente = ['nullable', 'date'];

        if (is_string($ingreso)) {
            $vigente[] = 'after_or_equal:fecha_ingreso';
        }

        if (is_string($fin)) {
            $vigente[] = 'before:fecha_fin';
        }

        return [
            'fecha_ingreso' => ['required', 'date', 'before_or_equal:today'],
            'fecha_inicio_periodo_vigente' => $vigente,
            'prorrogas_realizadas' => ['nullable', 'integer', 'min:0'],

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
     * nacimiento: el país decide si el departamento y la ciudad se resuelven
     * contra el catálogo o se dejan como texto libre. Sin país escrito se
     * asume Colombia, que es el comportamiento que tenía la plantilla antes
     * de esta columna y el que sigue teniendo casi cualquier archivo real.
     *
     * A diferencia del nacimiento, la plantilla no trae departamento y ciudad
     * en columnas separadas: «Lugar de expedición del documento» es una sola
     * celda. Dentro de Colombia eso ya se resolvía contra los municipios, con
     * el departamento entre paréntesis solo para desambiguar un nombre
     * repetido —hay dos Albania y dos Guadalupe—. Fuera de Colombia, donde no
     * hay catálogo contra el que resolver nada, esa misma sintaxis pasa a ser
     * obligatoria: es la única forma de que la celda traiga las dos cosas que
     * pide `RegistrarEmpleado`.
     */
    private function expedicion(FilaTraducida $fila, CatalogosDeCarga $catalogos): void
    {
        $lugar = $fila->valor('expedicion.ciudad');

        if (! is_string($lugar)) {
            return;
        }

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

        if ($codigo !== Pais::COLOMBIA) {
            $this->expedicionEnElExterior($fila, $lugar);

            return;
        }

        [$nombre, $departamento] = $this->conDepartamentoEntreParentesis($lugar);

        if ($departamento !== null) {
            $identificador = $catalogos->departamento($departamento);

            if ($identificador === null) {
                $motivo = $this->esBogota($departamento)
                    ? $this->motivoDeBogota('Lugar de expedición del documento', 'Lugar de expedición del documento')
                    : "«{$departamento}» no es un departamento de Colombia.";

                $this->sinResolver($fila, 'expedicion', 'Lugar de expedición del documento', $motivo);

                return;
            }

            $this->municipioDe($fila, 'expedicion', $identificador, $nombre, 'Lugar de expedición del documento', $catalogos);

            return;
        }

        $candidatos = $catalogos->ciudadesLlamadas($nombre);

        if ($candidatos === []) {
            $motivo = $this->esBogota($nombre)
                ? $this->motivoDeBogota('Lugar de expedición del documento', 'Lugar de expedición del documento')
                : "«{$lugar}» no es un municipio de Colombia. Escríbalo tal como aparece en la lista de ciudades del sistema.";

            $this->sinResolver($fila, 'expedicion', 'Lugar de expedición del documento', $motivo);

            return;
        }

        if (count($candidatos) > 1) {
            $departamentos = implode(', ', array_map(
                fn (array $candidato): string => (string) $catalogos->nombreDeDepartamento($candidato['departamento']),
                $candidatos,
            ));

            $this->sinResolver(
                $fila,
                'expedicion',
                'Lugar de expedición del documento',
                "Hay más de un municipio llamado «{$nombre}» ({$departamentos}). Escríbalo con su departamento entre paréntesis, por ejemplo «{$nombre} (".
                    (string) $catalogos->nombreDeDepartamento($candidatos[0]['departamento']).')».',
            );

            return;
        }

        $fila->poner('expedicion.departamento', $candidatos[0]['departamento']);
        $fila->poner('expedicion.ciudad', $candidatos[0]['id']);
    }

    /**
     * Fuera de Colombia el departamento y la ciudad quedan como texto libre,
     * igual que el nacimiento (documento 6, A.2): el sistema no tiene con qué
     * comprobarlos y la responsabilidad de que estén bien es del cliente.
     */
    private function expedicionEnElExterior(FilaTraducida $fila, string $lugar): void
    {
        [$ciudad, $departamento] = $this->conDepartamentoEntreParentesis($lugar);

        if ($departamento === null) {
            $this->sinResolver(
                $fila,
                'expedicion',
                'Lugar de expedición del documento',
                'Fuera de Colombia, escriba el lugar de expedición como «Ciudad (Departamento o región)», por ejemplo «Madrid (Madrid)».',
            );

            return;
        }

        $fila->poner('expedicion.ciudad', $ciudad);
        $fila->poner('expedicion.departamento', $departamento);
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
     * escribir y en qué columna. Si las dos columnas son la misma —el lugar
     * de expedición, que junta departamento y ciudad en una sola celda—, la
     * instrucción no se repite.
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
     * Separa «Albania (La Guajira)» en sus dos partes. Sin paréntesis, el
     * departamento es nulo.
     *
     * @return array{string, string|null}
     */
    private function conDepartamentoEntreParentesis(string $escrito): array
    {
        if (preg_match('/^(.+?)\s*\((.+)\)$/u', $escrito, $partes) !== 1) {
            return [$escrito, null];
        }

        return [trim($partes[1]), trim($partes[2])];
    }
}
