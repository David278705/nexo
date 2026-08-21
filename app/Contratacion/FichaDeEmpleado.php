<?php

namespace App\Contratacion;

use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Ciudad;
use App\Models\PerfilSociodemografico;
use App\Models\PeriodoContractual;
use App\Models\Persona;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;
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
 * ## Lo que aquí no está
 *
 * Las novedades y los movimientos llegan vacíos porque sus módulos son F2-04,
 * F2-05, F2-07 y F2-08. La pestaña carga y explica qué se verá ahí; el día que
 * el módulo exista solo hay que llenar el arreglo.
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
            'eps',
            'fondoPension',
            'arl',
            'cajaCompensacion',
            'banco',
        ]);

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
     * Los seis datos que se ven siempre, por encima de las pestañas.
     *
     * El saldo de vacaciones va de último, que es donde lo pone el prototipo.
     *
     * @return list<array{etiqueta: string, valor: string, pendiente: bool, nota: string}>
     */
    public function resumen(): array
    {
        return [
            self::dato('Documento', $this->persona->numero_documento),
            self::dato('Fecha de ingreso', Formato::fecha($this->vinculacion->fecha_ingreso)),
            self::dato('Antigüedad', $this->antiguedad()),
            self::dato('Tipo de contrato', self::texto($this->vinculacion->tipo_contrato)),
            self::dato('Salario', Formato::dinero($this->vinculacion->getAttribute('salario'))),
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
            'nota' => 'Calculado a hoy: 1,25 días por cada mes cumplido desde el ingreso, o desde la fecha de corte si el saldo vino de la carga inicial.',
        ];
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
     * @return array{campos: list<array{etiqueta: string, valor: string}>, historial: list<array<string, string|int>>}
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
     * 5.3). Vacío hasta que existan sus módulos.
     *
     * @return list<array<string, string>>
     */
    public function novedades(): array
    {
        return [];
    }

    /**
     * Aumentos y promociones de la persona. Vacío hasta F2-08.
     *
     * @return list<array<string, string>>
     */
    public function movimientos(): array
    {
        return [];
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
