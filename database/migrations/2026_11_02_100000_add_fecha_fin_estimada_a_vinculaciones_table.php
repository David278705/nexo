<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La fecha de finalización estimada del contrato de obra o labor (`OL-01`).
 *
 * Decidido por David el 2026-11-02: la obra o labor también maneja periodo de
 * prueba, calculado con las mismas reglas del término fijo sobre una fecha que
 * la empresa **estima**. Es opcional: sin ella no hay periodo de prueba.
 *
 * **Es una columna aparte y no `fecha_fin`**, y a propósito. `fecha_fin` es el
 * vencimiento pactado del término fijo, y medio sistema lo lee así: las alertas
 * de renovación, los contratos vencidos sin gestionar, el filtro «vence» del
 * listado, el tope de cuatro años. Una fecha estimada metida ahí haría que una
 * obra o labor «venciera» y disparara avisos de renovación sobre un contrato
 * que no se renueva. Esta columna solo la lee el periodo de prueba.
 *
 * No necesita política nueva: `vinculaciones` ya tiene su RLS desde la
 * migración que la creó, y una columna más no cambia quién alcanza la fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinculaciones', function (Blueprint $tabla) {
            $tabla->date('fecha_fin_estimada')->nullable()->after('fecha_fin');
        });
    }

    public function down(): void
    {
        Schema::table('vinculaciones', function (Blueprint $tabla) {
            $tabla->dropColumn('fecha_fin_estimada');
        });
    }
};
