<?php

namespace App\Contratacion;

use App\Autorizacion\Modulo;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Ciudad;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\PerfilSociodemografico;
use App\Models\PeriodoContractual;
use App\Models\Persona;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * La ficha del empleado (documento 3, sección 5.3).
 *
 * Arma lo que la pantalla enseña: el encabezado con el resumen siempre visible
 * y el contenido de las cuatro pestañas —datos personales, contrato, novedades
 * y movimientos—. Vive fuera del controlador por lo mismo que
 * `ListadoDeEmpleados`: el resumen del empleado se va a repetir en el modal
 * grande de novedades (documento 3, 5.5), y dos sitios que arman el mismo
 * encabezado terminan enseñando cosas distintas.
 *
 * ## El saldo de vacaciones
 *
 * El encabezado lo lleva siempre y desde F2-02 lleva el número: lo calcula
 * `SaldoDeVacaciones`, que es la regla 2 del proyecto —el saldo se calcula, no
 * se guarda— y no hay ninguna columna que leer. Cuando existan las vacaciones
 * disfrutadas (F2-05) y las suspensiones (F2-07), el número las descuenta solo
 * y aquí no cambia una línea.
 *
 * ## Las cuatro pestañas están llenas
 *
 * Los movimientos desde F2-08 y las novedades desde HD-01: las tres clases
 * —incapacidades, vacaciones y licencias— se juntan en una sola tabla, que es
 * lo que pide la sección 5.3. Ninguna de las dos hace cuentas aquí: los días de
 * cada novedad los da su modelo, que llama al motor.
 */
class FichaDeEmpleado
{
    private function __construct(
        private readonly Vinculacion $vinculacion,
        private readonly Persona $persona,
    ) {}

    public static function de(Vinculacion $vinculacion): self
    {
        $vinculacion->loadMissing([
            'persona.ciudadNacimiento.departamento',
            'persona.ciudadExpedicion.departamento',
            'persona.ciudadResidencia.departamento',
            'persona.paisNacimiento',
            'persona.paisExpedicion',
            'persona.perfil',
            'periodos',
            'movimientos',
            'incapacidades',
            'vacaciones',
            'licencias',
            'terminacion',
            'tenant',
            'eps',
            'fondoPension',
            'arl',
            'cajaCompensacion',
            'banco',
        ]);

        // `Vacacion::dias()` cuenta días hábiles y para eso necesita la empresa
        // dueña de la vinculación, que es esta misma. Sin esto, cada fila
        // volvería a la base a buscar la vinculación que ya tenemos delante.
        $vinculacion->vacaciones->each(
            fn (Vacacion $vacacion) => $vacacion->setRelation('vinculacion', $vinculacion)
        );

        return new self($vinculacion, $vinculacion->persona);
    }

    /**
     * Todas las props de la pantalla.
     *
     * @return array<string, mixed>
     */
    public function paraLaPantalla(): array
    {
        return [
            'empleado' => $this->encabezado(),
            'resumen' => $this->resumen(),
            'datos' => $this->datosPersonales(),
            'contrato' => $this->contrato(),
            'novedades' => $this->novedades(),
            'movimientos' => $this->movimientos(),
        ];
    }

    /**
     * Nombre, cargo y estado: lo que identifica al empleado en la pantalla.
     *
     * @return array{id: int, nombre: string, cargo: string, estado: string, activo: bool}
     */
    public function encabezado(): array
    {
        $estado = $this->vinculacion->getAttribute('estado');

        return [
            'id' => (int) $this->vinculacion->getKey(),
            'nombre' => $this->persona->nombreCompleto(),
            'cargo' => (string) $this->vinculacion->getAttribute('cargo'),
            'estado' => $estado instanceof EstadoVinculacion ? $estado->etiqueta() : '',
            'activo' => $this->vinculacion->estaActiva(),
        ];
    }

    /**
     * Los seis datos que se ven siempre, por encima de las pestañas, y los tres
     * de la salida cuando la persona ya no está activa (documento 1, cap. 7).
     *
     * El saldo de vacaciones va de último entre los seis de siempre, que es
     * donde lo pone el prototipo. Quien está activa no ve un hueco donde irían
     * los datos de salida: el arreglo simplemente no los trae.
     *
     * @return list<array{etiqueta: string, valor: string, pendiente: bool, nota: string}>
     */
    public function resumen(): array
    {
        $datos = [
            self::dato('Documento', $this->persona->numero_documento),
            self::dato('Fecha de ingreso', Formato::fecha($this->vinculacion->fecha_ingreso)),
            self::dato('Antigüedad', $this->antiguedad()),
            self::dato('Tipo de contrato', self::texto($this->vinculacion->tipo_contrato)),
            self::dato('Salario', Formato::dinero($this->vinculacion->getAttribute('salario'))),
            $this->saldoDeVacaciones(),
        ];

        if (! $this->vinculacion->estaActiva()) {
            array_push($datos, ...$this->datosDeSalida());
        }

        return $datos;
    }

    /**
     * Fecha, tipo y motivo de la salida, solo para quien ya no está activo.
     *
     * Cuánto duró la vinculación no se repite aquí: «Antigüedad», que ya va
     * entre los seis datos de siempre, mide hasta `fechaDeSalida()` desde
     * F2-09 y es exactamente esa cuenta.
     *
     * `terminacion` puede no existir: una vinculación inactiva sin ese
     * registro es la carga histórica que F5-02 todavía no trae (ver el
     * comentario de `Vinculacion::fechaDeSalida()`), y entonces solo hay fecha
     * de salida —de `fecha_fin`— y ni tipo ni motivo que enseñar.
     *
     * @return list<array{etiqueta: string, valor: string, pendiente: bool, nota: string}>
     */
    private function datosDeSalida(): array
    {
        $terminacion = $this->vinculacion->terminacion;

        return [
            self::dato('Fecha de salida', Formato::fecha($this->vinculacion->fechaDeSalida())),
            self::dato('Tipo de salida', $terminacion === null ? Formato::VACIO : $terminacion->tipo_salida->etiqueta()),
            self::dato('Motivo', $terminacion === null ? Formato::VACIO : $terminacion->motivo),
        ];
    }

    /**
     * El mismo resumen, recortado a lo que encabeza el modal grande de novedades
     * (documento 3, 5.5, paso 4): nombre, documento, cargo, fecha de ingreso y
     * saldo de vacaciones.
     *
     * Es un recorte y no otra ficha: lo pinta el mismo componente
     * `ResumenDeEmpleado` y los valores salen de los mismos métodos. Lo que el
     * modal no lleva es el salario ni la antigüedad, que no hacen falta para
     * registrar una incapacidad y sí son un dato de más en pantalla.
     *
     * @return list<array{etiqueta: string, valor: string, pendiente: bool, nota: string}>
     */
    public function resumenParaNovedad(): array
    {
        return [
            self::dato('Empleado', $this->persona->nombreCompleto()),
            self::dato('Documento', $this->persona->numero_documento),
            self::dato('Cargo', self::texto($this->vinculacion->getAttribute('cargo'))),
            self::dato('Fecha de ingreso', Formato::fecha($this->vinculacion->fecha_ingreso)),
            $this->saldoDeVacaciones(),
        ];
    }

    /**
     * El saldo de vacaciones del encabezado, calculado a hoy.
     *
     * Sale del motor y no de ninguna columna, que es la regla 2. Aquí no se
     * cuenta ni un día: se pide y se escribe.
     *
     * @return array{etiqueta: string, valor: string, pendiente: bool, nota: string}
     */
    public function saldoDeVacaciones(): array
    {
        $saldo = SaldoDeVacaciones::de($this->vinculacion)->al(Carbon::today());

        return [
            'etiqueta' => 'Saldo de vacaciones',
            'valor' => Formato::diasDecimales($saldo),
            'pendiente' => false,
            'nota' => self::notaDelSaldo(),
        ];
    }

    /**
     * La explicación del saldo, con los cuatro términos de la fórmula de
     * `SaldoDeVacaciones` (documento 2, 3.4).
     *
     * Es el único sitio donde está escrita: el encabezado de la ficha y el modal
     * de novedades piden el saldo a `saldoDeVacaciones()`, y la pantalla de
     * vacaciones enseña sus propios números sin esta nota. Una prueba ata el
     * texto a la fórmula (`FichaDeEmpleadoTest`), porque una nota que cuenta de
     * dónde sale lo que se suma y calla lo que se resta deja al usuario
     * creyendo que el saldo solo sube.
     *
     * Los 1,25 salen de la misma constante del motor y no de un número suelto.
     */
    private static function notaDelSaldo(): string
    {
        $porMes = str_replace('.', ',', (string) config('reglas_laborales.dias_vacaciones_por_mes'));

        return 'Calculado a hoy: el saldo inicial de la carga, más '.$porMes.' días por cada mes cumplido '
            .'desde el ingreso (o desde la fecha de corte, si el saldo vino de la carga inicial), '
            .'menos los días de vacaciones ya disfrutados, '
            .'menos la reducción por licencias, ausencias y suspensiones si la empresa tiene ese parámetro activo.';
    }

    /**
     * Cuánto lleva la persona vinculada, en años y meses cumplidos.
     *
     * Se mide hasta hoy mientras el vínculo esté vivo, y hasta la fecha de
     * salida cuando ya terminó: la antigüedad de quien se fue no sigue
     * creciendo. No es una cuenta laboral —no descuenta suspensiones ni nada
     * parecido—, es la distancia entre dos fechas, y por eso no vive en el
     * motor de cálculo.
     */
    public function antiguedad(): string
    {
        $desde = $this->vinculacion->fecha_ingreso->copy()->startOfDay();
        $hasta = ($this->vinculacion->fechaDeSalida() ?? Carbon::today())->copy()->startOfDay();

        if ($hasta->lessThan($desde)) {
            return '—';
        }

        $anios = (int) $desde->diffInYears($hasta);
        $meses = (int) $desde->copy()->addYears($anios)->diffInMonths($hasta);

        $partes = array_filter([
            $anios > 0 ? $anios.($anios === 1 ? ' año' : ' años') : '',
            $meses > 0 ? $meses.($meses === 1 ? ' mes' : ' meses') : '',
        ]);

        return $partes === [] ? 'Menos de un mes' : implode(', ', $partes);
    }

    /**
     * El contenido de la pestaña de datos personales.
     *
     * Cuatro bloques: identificación, residencia, seguridad social y el perfil
     * sociodemográfico, que solo aparece si la empresa lo tiene encendido
     * (documento 1, 5.2). Un perfil sin diligenciar se dice con una frase, no
     * con ocho renglones vacíos.
     *
     * @return list<array{titulo: string, campos: list<array{etiqueta: string, valor: string}>, vacio: string}>
     */
    public function datosPersonales(): array
    {
        $bloques = [
            self::bloque('Identificación', [
                'Tipo de documento' => self::texto($this->persona->getAttribute('tipo_documento')),
                'Número de documento' => $this->persona->numero_documento,
                'Fecha de expedición' => Formato::fecha($this->persona->getAttribute('fecha_expedicion_documento')),
                'Lugar de expedición' => $this->lugar('expedicion'),
                'Fecha de nacimiento' => Formato::fecha($this->persona->getAttribute('fecha_nacimiento')),
                'Lugar de nacimiento' => $this->lugar('nacimiento'),
                'Género' => self::texto($this->persona->getAttribute('genero')),
            ]),
            self::bloque('Residencia', [
                'Dirección' => self::texto($this->persona->getAttribute('direccion')),
                'Ciudad de residencia' => self::ciudad($this->persona->ciudadResidencia),
                'Celular' => self::texto($this->persona->getAttribute('celular')),
                'Correo electrónico' => self::texto($this->persona->getAttribute('correo')),
            ]),
            self::bloque('Seguridad social', [
                'EPS' => self::nombreDe($this->vinculacion->eps),
                'Fondo de pensión' => self::nombreDe($this->vinculacion->fondoPension),
                'ARL' => self::nombreDe($this->vinculacion->arl),
                'Nivel de riesgo' => self::texto($this->vinculacion->tipo_riesgo),
                'Caja de compensación' => self::nombreDe($this->vinculacion->cajaCompensacion),
                'Banco de nómina' => self::nombreDe($this->vinculacion->banco),
            ]),
        ];

        if (BloqueSociodemografico::habilitado()) {
            $bloques[] = $this->perfilSociodemografico();
        }

        return $bloques;
    }

    /**
     * El contenido de la pestaña de contrato.
     *
     * Lo vigente arriba y el historial completo debajo, empezando por el
     * periodo inicial. Las cuentas no se hacen aquí: son `HistorialDeContrato`,
     * que es el mismo objeto que usarán el detalle del contrato (F1-08) y las
     * validaciones de prórroga (F1-10).
     *
     * @return array{campos: list<array{etiqueta: string, valor: string}>, historial: list<array<string, string|int>>, notaDeHistorial: string}
     */
    public function contrato(): array
    {
        $historial = $this->vinculacion->historial();
        $tipo = $historial->tipoDeContratoVigente() ?? $this->vinculacion->tipo_contrato;

        $campos = [
            'Tipo de contrato' => self::texto($tipo),
            'Fecha de ingreso' => Formato::fecha($this->vinculacion->fecha_ingreso),
            'Fecha de vencimiento' => Formato::fecha($historial->fechaDeVencimiento()),
            'Prórrogas acumuladas' => (string) $historial->prorrogasAcumuladas(),
            'Salario' => Formato::dinero($this->vinculacion->getAttribute('salario')),
            'Bonificación' => Formato::dinero($this->vinculacion->getAttribute('bonificacion')),
        ];

        // Las dos cuentas del tope legal solo tienen sentido donde hay plazo
        // que topar: en el indefinido no existe el tope de cuatro años.
        if ($tipo->exigeFechaFin()) {
            $duracion = $historial->duracionTotalEnDias();

            $campos['Duración total del vínculo'] = $duracion === null
                ? Formato::VACIO
                : Formato::dias($duracion);
            $campos['Tope legal de cuatro años'] = Formato::fecha($historial->fechaDelTopeLegal());
        }

        return [
            'campos' => self::campos($campos),
            'historial' => self::filasDelHistorial($historial),
            'notaDeHistorial' => $historial->notaDeCargaInicial(),
        ];
    }

    /**
     * El historial de periodos tal como se ve en una tabla.
     *
     * Es público y estático porque el detalle del contrato de F1-08 enseña
     * exactamente el mismo historial, y dos versiones de la misma tabla se
     * separan a la primera corrección.
     *
     * @return list<array<string, string|int>>
     */
    public static function filasDelHistorial(HistorialDeContrato $historial): array
    {
        return $historial->periodos()
            ->map(fn (PeriodoContractual $tramo): array => [
                'id' => (int) $tramo->getKey(),
                'orden' => (int) $tramo->getAttribute('orden'),
                'evento' => self::texto($tramo->evento),
                'contrato' => self::texto($tramo->tipo_contrato),
                'desde' => Formato::fecha($tramo->fecha_inicio),
                'hasta' => Formato::fecha($tramo->fecha_fin),
            ])
            ->values()
            ->all();
    }

    /**
     * Las tres clases de novedad juntas y en orden cronológico (documento 3,
     * 5.3): incapacidades, vacaciones y licencias de **esta** vinculación.
     *
     * Van ordenadas de la más reciente a la más antigua, igual que el histórico
     * de movimientos de la pestaña de al lado: en una ficha se entra a ver qué
     * pasó últimamente, no a leer la historia desde el principio.
     *
     * El aislamiento no se comprueba aquí y es deliberado: las tres relaciones
     * salen de modelos con `PerteneceATenant`, así que el filtro lo pone el
     * trait. Un `where` de empresa escrito a mano en este método sería la señal
     * de que algo falta más abajo (regla 1).
     *
     * **Los días no son comparables entre filas y por eso la pantalla lo dice.**
     * Los de vacaciones son hábiles y los de incapacidades y licencias
     * corridos: son tres reglas distintas del motor (regla 3) y juntarlas en una
     * columna sin explicarlo invitaría a sumarlas.
     *
     * @return list<array{id: string, clase: string, desde: string, hasta: string, dias: int, detalle: string}>
     */
    public function novedades(): array
    {
        $novedades = [
            ...$this->vinculacion->incapacidades->map(fn (Incapacidad $incapacidad): array => [
                'id' => 'incapacidad-'.$incapacidad->getKey(),
                'clase' => 'Incapacidad',
                'inicio' => $incapacidad->fecha_inicio,
                'fin' => $incapacidad->fecha_fin,
                'dias' => $incapacidad->dias(),
                'detalle' => self::detalleDeIncapacidad($incapacidad),
            ])->all(),
            ...$this->vinculacion->vacaciones->map(fn (Vacacion $vacacion): array => [
                'id' => 'vacacion-'.$vacacion->getKey(),
                'clase' => 'Vacaciones',
                'inicio' => $vacacion->fecha_inicio,
                'fin' => $vacacion->fecha_fin,
                'dias' => $vacacion->dias(),
                // La nota del traslape con una incapacidad (`HD-04`), o vacío
                // si este periodo no tiene ninguno.
                'detalle' => $vacacion->notaDeTraslape() === '' ? Formato::VACIO : $vacacion->notaDeTraslape(),
            ])->all(),
            ...$this->vinculacion->licencias->map(fn (Licencia $licencia): array => [
                // La clase es el tipo: llamar «Licencia» a una suspensión sería
                // decir otra cosa. Es lo que distingue a esta novedad y por eso
                // va en la columna que se lee primero, no en el detalle.
                'id' => 'licencia-'.$licencia->getKey(),
                'clase' => $licencia->tipo->etiqueta(),
                'inicio' => $licencia->fecha_inicio,
                'fin' => $licencia->fecha_fin,
                'dias' => $licencia->dias(),
                'detalle' => Formato::VACIO,
            ])->all(),
        ];

        usort(
            $novedades,
            fn (array $una, array $otra): int => $otra['inicio']->getTimestamp() <=> $una['inicio']->getTimestamp(),
        );

        return array_map(fn (array $novedad): array => [
            'id' => $novedad['id'],
            'clase' => $novedad['clase'],
            'desde' => Formato::fecha($novedad['inicio']),
            'hasta' => Formato::fecha($novedad['fin']),
            'dias' => $novedad['dias'],
            'detalle' => $novedad['detalle'],
        ], $novedades);
    }

    /**
     * «Común · Diagnóstico J06.9», o solo el origen para quien no puede ver el
     * diagnóstico.
     *
     * `codigo_diagnostico` es el dato sensible del documento 1, 6.3, y la regla
     * de pruebas y seguridad lo reserva a quien tiene permiso sobre novedades.
     * En el listado del módulo eso se cumplía solo —a esa pantalla no se llega
     * sin el middleware `modulo:incapacidades`—, pero a la ficha se entra con el
     * módulo de **empleados**, que es otro permiso distinto: aquí hay que
     * preguntar. Sin usuario autenticado, `Gate::allows()` dice que no, que es
     * el valor seguro.
     */
    private static function detalleDeIncapacidad(Incapacidad $incapacidad): string
    {
        $origen = $incapacidad->origen->etiqueta();

        if (! Gate::allows('modulo', Modulo::Incapacidades)) {
            return $origen;
        }

        return $origen.' · Diagnóstico '.$incapacidad->codigo_diagnostico;
    }

    /**
     * El histórico de aumentos y promociones (documento 1, 8.1), del más
     * reciente al más antiguo.
     *
     * Cada fila enseña **el cargo y el salario anterior frente al nuevo**, que
     * es lo que la sección 8.1 pide del histórico. Es el único sitio donde se
     * lee el valor anterior guardado en la fila: la vinculación ya trae el
     * vigente, y el de antes solo existe aquí.
     *
     * @return list<array<string, string>>
     */
    public function movimientos(): array
    {
        return $this->vinculacion->movimientos
            ->map(fn (Movimiento $movimiento): array => [
                'fecha' => Formato::fecha($movimiento->fecha_efectividad),
                'clase' => $movimiento->tipo->etiqueta(),
                'cargo' => self::cambio($movimiento->cargo_anterior, $movimiento->cargo_nuevo),
                'salario' => self::cambio(
                    Formato::dinero($movimiento->getAttribute('salario_anterior')),
                    Formato::dinero($movimiento->getAttribute('salario_nuevo')),
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * «Analista Senior → Coordinadora de Operaciones».
     *
     * Un aumento salarial repite el cargo, y escribirlo dos veces con una
     * flecha en medio sería decirle al que mira que algo cambió cuando no
     * cambió nada.
     */
    private static function cambio(string $anterior, string $nuevo): string
    {
        return $anterior === $nuevo ? $nuevo : $anterior.' → '.$nuevo;
    }

    /**
     * @return array{titulo: string, campos: list<array{etiqueta: string, valor: string}>, vacio: string}
     */
    private function perfilSociodemografico(): array
    {
        $perfil = $this->persona->perfil;

        if (! $perfil instanceof PerfilSociodemografico || $perfil->estaVacio()) {
            return [
                'titulo' => 'Perfil sociodemográfico',
                'campos' => [],
                'vacio' => 'Esta persona todavía no tiene diligenciado el perfil sociodemográfico. Todos sus campos son opcionales.',
            ];
        }

        return self::bloque('Perfil sociodemográfico', [
            'Estado civil' => self::texto($perfil->getAttribute('estado_civil')),
            'Nivel educativo' => self::texto($perfil->getAttribute('nivel_educativo')),
            'Número de hijos' => self::texto($perfil->getAttribute('numero_hijos')),
            'Personas a cargo' => self::texto($perfil->getAttribute('personas_a_cargo')),
            'Tipo de vivienda' => self::texto($perfil->getAttribute('tipo_vivienda')),
            'Estrato' => $perfil->getAttribute('estrato') === null
                ? '—'
                : 'Estrato '.$perfil->getAttribute('estrato'),
            'Medio de transporte' => self::texto($perfil->getAttribute('medio_transporte')),
            'Tiempo de desplazamiento' => self::texto($perfil->getAttribute('tiempo_desplazamiento')),
        ]);
    }

    /**
     * «Medellín, Antioquia, Colombia», o lo que se haya digitado a mano cuando
     * el lugar está fuera del país (documento 1, 5.1.1).
     */
    private function lugar(string $sufijo): string
    {
        $ciudad = $this->persona->getRelationValue('ciudad'.ucfirst($sufijo));
        $pais = $this->persona->getRelationValue('pais'.ucfirst($sufijo));
        $nombreDelPais = self::nombreDe($pais);

        if ($ciudad instanceof Ciudad) {
            return self::ciudad($ciudad).', '.$nombreDelPais;
        }

        $partes = array_filter([
            trim((string) $this->persona->getAttribute('ciudad_'.$sufijo.'_exterior')),
            trim((string) $this->persona->getAttribute('departamento_'.$sufijo.'_exterior')),
            $nombreDelPais === '—' ? '' : $nombreDelPais,
        ]);

        return $partes === [] ? '—' : implode(', ', $partes);
    }

    private static function ciudad(mixed $ciudad): string
    {
        if (! $ciudad instanceof Ciudad) {
            return '—';
        }

        $departamento = $ciudad->getRelationValue('departamento');

        return (string) $ciudad->getAttribute('nombre')
            .($departamento === null ? '' : ', '.$departamento->getAttribute('nombre'));
    }

    /**
     * @param  array<string, string>  $campos
     * @return array{titulo: string, campos: list<array{etiqueta: string, valor: string}>, vacio: string}
     */
    private static function bloque(string $titulo, array $campos): array
    {
        return ['titulo' => $titulo, 'campos' => self::campos($campos), 'vacio' => ''];
    }

    /**
     * @param  array<string, string>  $campos
     * @return list<array{etiqueta: string, valor: string}>
     */
    private static function campos(array $campos): array
    {
        return array_map(
            fn (string $etiqueta, string $valor): array => ['etiqueta' => $etiqueta, 'valor' => $valor],
            array_keys($campos),
            array_values($campos),
        );
    }

    /**
     * @return array{etiqueta: string, valor: string, pendiente: bool, nota: string}
     */
    private static function dato(string $etiqueta, string $valor): array
    {
        return ['etiqueta' => $etiqueta, 'valor' => $valor, 'pendiente' => false, 'nota' => ''];
    }

    /**
     * El nombre de una entidad de catálogo: la EPS, la ARL, el banco, el país.
     */
    private static function nombreDe(mixed $entidad): string
    {
        return $entidad === null ? '—' : self::texto($entidad->getAttribute('nombre'));
    }

    /**
     * Lo que se pinta en pantalla de un valor cualquiera.
     *
     * Los enumerados se guardan como texto legible, pero lo legible para la
     * base de datos no es lo legible para el usuario: `etiqueta()` es lo que
     * lee la persona que abre la ficha.
     */
    private static function texto(mixed $valor): string
    {
        if ($valor instanceof UnitEnum && method_exists($valor, 'etiqueta')) {
            return $valor->etiqueta();
        }

        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? '—' : $texto;
    }
}
