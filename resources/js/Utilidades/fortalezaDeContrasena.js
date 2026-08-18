/**
 * Qué tan fuerte es una contraseña, para el indicador de la pantalla de
 * restablecer (documento 3, sección 5.1).
 *
 * Es una ayuda visual, no un control: lo que se exige de verdad lo valida el
 * servidor con `ReglasDeContrasena`, que pide ocho caracteres con letras y
 * números. Por eso aquí no se bloquea nada, solo se informa.
 */

export const LONGITUD_MINIMA = 8;

const NIVELES = [
    { nivel: 0, etiqueta: '', porcentaje: 0 },
    { nivel: 1, etiqueta: 'Muy débil', porcentaje: 25 },
    { nivel: 2, etiqueta: 'Débil', porcentaje: 50 },
    { nivel: 3, etiqueta: 'Aceptable', porcentaje: 75 },
    { nivel: 4, etiqueta: 'Fuerte', porcentaje: 100 },
];

/**
 * @param {string} contrasena
 * @returns {{ nivel: number, etiqueta: string, porcentaje: number }}
 */
export function fortalezaDeContrasena(contrasena) {
    const valor = contrasena ?? '';

    if (valor.length === 0) {
        return NIVELES[0];
    }

    let puntos = 0;

    if (valor.length >= LONGITUD_MINIMA) puntos += 1;
    if (/[a-záéíóúñ]/i.test(valor) && /\d/.test(valor)) puntos += 1;
    if (valor.length >= 12) puntos += 1;
    if (/[^\w\s]/.test(valor) || (/[a-záéíóúñ]/.test(valor) && /[A-ZÁÉÍÓÚÑ]/.test(valor))) puntos += 1;

    // Una contraseña que no llega al mínimo nunca pasa de «muy débil», por
    // larga que sea la lista de caracteres raros que traiga.
    if (valor.length < LONGITUD_MINIMA) {
        return NIVELES[1];
    }

    return NIVELES[Math.max(1, puntos)];
}
