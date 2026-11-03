<?php

namespace App\Dominio\Alertas;

use App\Autorizacion\Modulo;

/**
 * El catálogo cerrado de alertas del sistema (documento 1, 9.7), más el correo
 * de prueba de entregabilidad, que no es una alerta y por eso va al final y
 * `esDelCalendario()` lo distingue.
 *
 * Son las trece del «resumen del calendario» más las dos del periodo de prueba
 * sugerido de la obra o labor (`OL-01`): once individuales, una por
 * empleado, y cuatro agrupadas —tres mensuales y la de obra o labor, que es
 * bimestral (documento 1, 9.6 y 9.7)—. Las tres que la misma
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

    // Periodo de prueba sugerido de la obra o labor con fecha estimada (`OL-01`).
    // Son los dos avisos del término fijo, con nombre propio: el registro de
    // envíos no puede decir «término fijo» sobre un contrato que no lo es.

    case PruebaObraLaborDosDias = 'prueba_obra_labor_dos_dias';

    case PruebaObraLaborVispera = 'prueba_obra_labor_vispera';

    // Vencimiento, prórrogas y topes del término fijo (documento 1, 9.4).

    case PlazoDeRenovacion = 'plazo_de_renovacion';

    case EfectoDelSilencio = 'efecto_del_silencio';

    case UltimaRenovacionCorta = 'ultima_renovacion_corta';

    case CercaniaAlTope = 'cercania_al_tope';

    // Resúmenes mensuales (documento 1, 9.4, 9.5 y 9.6).

    case ContratosVencidosSinGestionar = 'contratos_vencidos_sin_gestionar';

    case AcumulacionDeQuinceDias = 'acumulacion_de_quince_dias';

    case AnioSinDisfruteMinimo = 'anio_sin_disfrute_minimo';

    // Bimestral, la única que no sale todos los meses (documento 1, 9.6).

    case ObraOLaborActivos = 'obra_o_labor_activos';

    /*
     * El correo de prueba de entregabilidad (F3-06, documento 2, 5.5).
     *
     * No es una alerta del capítulo 9 y por eso está debajo de la línea y
     * `esDelCalendario()` la separa: no la dispara ninguna regla, no la manda el
     * proceso diario y nadie la recibe por corresponderle. La manda a mano quien
     * acaba de configurar el dominio, con `alertas:prueba`, para comprobar que
     * el proveedor transaccional entrega en bandeja de entrada.
     *
     * Se guarda en `alertas_enviadas` como todo lo demás porque esa tabla es el
     * registro de envíos, y un correo de prueba que no dejara rastro no probaría
     * justamente lo que se quiere probar: que lo enviado queda registrado.
     */
    case PruebaDeEntregabilidad = 'prueba_de_entregabilidad';

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
            self::PruebaObraLaborDosDias => 'Periodo de prueba en obra o labor: quedan 2 días',
            self::PruebaObraLaborVispera => 'Periodo de prueba en obra o labor: último día mañana',
            self::PlazoDeRenovacion => 'Plazo para definir la renovación',
            self::EfectoDelSilencio => 'Último día para avisar la no renovación',
            self::UltimaRenovacionCorta => 'Última renovación corta posible',
            self::CercaniaAlTope => 'Cercanía al tope de cuatro años',
            self::ContratosVencidosSinGestionar => 'Contratos vencidos sin gestionar',
            self::AcumulacionDeQuinceDias => 'Vacaciones: acumulación de 15 días',
            self::AnioSinDisfruteMinimo => 'Vacaciones: año cumplido sin disfrute mínimo',
            self::ObraOLaborActivos => 'Contratos de obra o labor activos',
            self::PruebaDeEntregabilidad => 'Correo de prueba de entregabilidad',
        };
    }

    /**
     * El módulo cuyos administradores reciben esta alerta.
     *
     * Decide dos cosas a la vez, y tienen que ser la misma: a quién le llega el
     * correo (`DestinatariosDeAlertas`) y quién la ve en el panel de alertas
     * (`App\Alertas\AlertasDelUsuario`). El panel es el canal alterno del correo,
     * así que enseñar una alerta a quien no la habría recibido sería abrir por la
     * pantalla lo que el correo mantiene cerrado.
     *
     * El correo de prueba es de `Configuracion` —solo el superadministrador—
     * porque no habla de ningún empleado: es una comprobación de operación.
     */
    public function modulo(): Modulo
    {
        return match ($this) {
            self::AcumulacionDeQuinceDias, self::AnioSinDisfruteMinimo => Modulo::Vacaciones,
            self::PruebaDeEntregabilidad => Modulo::Configuracion,
            default => Modulo::Contratos,
        };
    }

    /**
     * Si es una de las alertas del «resumen del calendario» (documento 1, 9.7).
     *
     * Lo es todo lo que dispara una regla. La única que no lo es —el correo de
     * prueba de entregabilidad— existe para operar el sistema, no para avisarle
     * nada a nadie.
     */
    public function esDelCalendario(): bool
    {
        return $this !== self::PruebaDeEntregabilidad;
    }

    /**
     * Si la alerta va agrupada en un resumen o sale una por empleado
     * (documento 1, 9.1 y 9.7).
     *
     * Un resumen no cuelga de ninguna vinculación, y por eso esta pregunta
     * decide también si `vinculacion_id` puede ser nulo. Lo que decide es «va
     * agrupada», no cada cuánto sale: la de obra o labor es bimestral y
     * también responde `true`, porque su correo tampoco es de nadie en
     * particular.
     */
    public function esResumenMensual(): bool
    {
        return match ($this) {
            self::ContratosVencidosSinGestionar,
            self::AcumulacionDeQuinceDias,
            self::AnioSinDisfruteMinimo,
            self::ObraOLaborActivos => true,
            default => false,
        };
    }
}
