<?php

namespace App\Dominio\Alertas;

use App\Autorizacion\Modulo;

/**
 * El catálogo cerrado de alertas del sistema (documento 1, 9.7).
 *
 * Son las trece del «resumen del calendario»: nueve individuales, una por
 * empleado, y cuatro de resumen mensual, agrupadas. Las tres que la misma
 * tabla lista como «en pantalla, al momento» —saldo insuficiente, documento
 * inexistente, advertencia de reingreso— no están aquí y no deben estarlo: no
 * son correos, ya viven en sus pantallas y se construyeron en la fase 1 y 2.
 *
 * El catálogo se declara entero en F3-01, antes que sus reglas, por lo mismo
 * que `tipo` es una columna de texto legible y no un número: lo que se guarda
 * en `alertas_enviadas` tiene que poder leerse dentro de dos años en la consola
 * del proveedor. Las reglas de disparo llegan después —F3-03 el periodo de
 * prueba, F3-04 el término fijo, F3-05 los resúmenes— y cada una completa su
 * caso; ninguna inventa uno nuevo.
 *
 * `modulo()` es lo que decide **a quién le llega** cada alerta: el capítulo 4
 * del documento 1 dice que las alertas llegan a «los administradores con
 * acceso a cada módulo», así que cada tipo declara de qué módulo es. Las de
 * periodo de prueba y las de vencimiento son de Contratos —hablan del contrato
 * y de sus plazos, no de la ficha de la persona— y las dos de vacaciones son de
 * Vacaciones.
 */
enum TipoDeAlerta: string
{
    // Periodo de prueba, contrato a término indefinido (documento 1, 9.2).

    case PruebaIndefinidoUnMes = 'prueba_indefinido_un_mes';

    case PruebaIndefinidoQuinceDias = 'prueba_indefinido_quince_dias';

    case PruebaIndefinidoVispera = 'prueba_indefinido_vispera';

    // Periodo de prueba, contrato a término fijo (documento 1, 9.3).

    case PruebaFijoDosDias = 'prueba_fijo_dos_dias';

    case PruebaFijoVispera = 'prueba_fijo_vispera';

    // Vencimiento, prórrogas y topes del término fijo (documento 1, 9.4).

    case PlazoDeRenovacion = 'plazo_de_renovacion';

    case EfectoDelSilencio = 'efecto_del_silencio';

    case UltimaRenovacionCorta = 'ultima_renovacion_corta';

    case CercaniaAlTope = 'cercania_al_tope';

    // Resúmenes mensuales (documento 1, 9.4, 9.5 y 9.6).

    case ContratosVencidosSinGestionar = 'contratos_vencidos_sin_gestionar';

    case AcumulacionDeQuinceDias = 'acumulacion_de_quince_dias';

    case AnioSinDisfruteMinimo = 'anio_sin_disfrute_minimo';

    case ObraOLaborProlongada = 'obra_o_labor_prolongada';

    /**
     * Cómo se nombra la alerta en el registro de envíos y en la consola del
     * proveedor. No es el asunto del correo: ese lo escribe cada regla con el
     * nombre de la persona dentro.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PruebaIndefinidoUnMes => 'Periodo de prueba: un mes cumplido',
            self::PruebaIndefinidoQuinceDias => 'Periodo de prueba: quedan 15 días',
            self::PruebaIndefinidoVispera => 'Periodo de prueba: último día mañana',
            self::PruebaFijoDosDias => 'Periodo de prueba en término fijo: quedan 2 días',
            self::PruebaFijoVispera => 'Periodo de prueba en término fijo: último día mañana',
            self::PlazoDeRenovacion => 'Plazo para definir la renovación',
            self::EfectoDelSilencio => 'Último día para avisar la no renovación',
            self::UltimaRenovacionCorta => 'Última renovación corta posible',
            self::CercaniaAlTope => 'Cercanía al tope de cuatro años',
            self::ContratosVencidosSinGestionar => 'Contratos vencidos sin gestionar',
            self::AcumulacionDeQuinceDias => 'Vacaciones: acumulación de 15 días',
            self::AnioSinDisfruteMinimo => 'Vacaciones: año cumplido sin disfrute mínimo',
            self::ObraOLaborProlongada => 'Contratos de obra o labor prolongados',
        };
    }

    /**
     * El módulo cuyos administradores reciben esta alerta.
     */
    public function modulo(): Modulo
    {
        return match ($this) {
            self::AcumulacionDeQuinceDias, self::AnioSinDisfruteMinimo => Modulo::Vacaciones,
            default => Modulo::Contratos,
        };
    }

    /**
     * Si la alerta va agrupada en el resumen del mes o sale una por empleado
     * (documento 1, 9.1 y 9.7).
     *
     * Un resumen no cuelga de ninguna vinculación, y por eso esta pregunta
     * decide también si `vinculacion_id` puede ser nulo.
     */
    public function esResumenMensual(): bool
    {
        return match ($this) {
            self::ContratosVencidosSinGestionar,
            self::AcumulacionDeQuinceDias,
            self::AnioSinDisfruteMinimo,
            self::ObraOLaborProlongada => true,
            default => false,
        };
    }
}
