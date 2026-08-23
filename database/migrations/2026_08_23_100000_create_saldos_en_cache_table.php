<?php

use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La caché de saldos de vacaciones (documento 2, 3.4).
 *
 * **Esta tabla no es la fuente de verdad de nada.** El saldo se calcula —regla 2
 * del proyecto— y aquí solo se copia el resultado para poder sumarlo de un
 * tirón: el panel de inicio necesita el acumulado de toda la planta, y pedirlo
 * empleado por empleado significa recorrer los meses de cada uno contra la base
 * de datos. Si un día la caché y el cálculo discrepan, manda el cálculo, y la
 * discrepancia se arregla sola en el refresco siguiente.
 *
 * Por eso la fila lleva `calculado_al`, la fecha a la que se refiere el saldo, y
 * `calculado_en`, el instante en que se escribió. Un número sin la fecha a la
 * que corresponde no se puede contrastar con nada.
 *
 * ## Las dos convenciones de las que se sale, y por qué
 *
 * - **No lleva borrado lógico.** La regla 4 protege la información: un registro
 *   borrado tiene que poder consultarse y saberse quién lo quitó. Aquí no hay
 *   información que proteger —cada fila se vuelve a producir entera con
 *   `CacheDeSaldos::refrescar()`— y una fila muerta que hubiera que excluir en
 *   cada lectura solo añadiría una forma de sumar de más.
 * - **No se audita.** `SaldoEnCache` está en la lista `SIN_AUDITAR` del
 *   observador. Sin eso, el refresco nocturno dejaría una línea de auditoría por
 *   empleado y por noche, sin autor y sin nada que contar.
 *
 * El aislamiento sí es el de siempre: `tenant_id`, el trait y la política de
 * RLS. Es una tabla que guarda una cifra por empleado, y una suma que cruzara
 * empresas sería exactamente la fuga que las dos capas existen para impedir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldos_en_cache', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->foreignId('vinculacion_id')->constrained('vinculaciones');

            // Días con fracción y precisión fija, como el dinero: el saldo se
            // acumula de 1,25 en 1,25 y se compara contra los quince días de un
            // periodo completo. Con coma flotante, esa comparación falla sola.
            // Cuatro decimales son los mismos que redondea `SaldoDeVacaciones`.
            // Va con signo: un saldo puede quedar negativo si se disfrutaron
            // días por anticipado.
            $tabla->decimal('dias', 8, 4);

            $tabla->date('calculado_al');
            $tabla->timestamp('calculado_en');

            $tabla->timestamps();

            // Una sola fila por empleado. El índice único es lo que hace que
            // refrescar dos veces la misma noche no pueda duplicar a nadie, del
            // mismo modo que la clave de `alertas_enviadas`.
            $tabla->unique(['tenant_id', 'vinculacion_id']);
        });

        PoliticaRls::activarEn('saldos_en_cache');
    }

    public function down(): void
    {
        PoliticaRls::desactivarEn('saldos_en_cache');

        Schema::dropIfExists('saldos_en_cache');
    }
};
