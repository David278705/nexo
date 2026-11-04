/**
 * Leer y escribir pesos como se escriben en Colombia: punto para los miles y
 * coma para los centavos (`PROD-11`).
 *
 * Son dos funciones puras para que se puedan probar sin montar ningún
 * componente: `CampoDinero` es lo único que las usa.
 */

/**
 * El número limpio que entiende el servidor, a partir de lo que se escribió.
 *
 * «$ 380.000» → «380000» · «380.000,50» → «380000.50» · «» → «».
 *
 * Todo lo que no sea dígito ni coma se descarta: el punto, el signo de pesos,
 * los espacios. La primera coma separa los centavos; lo demás se ignora.
 * Los centavos no se recortan aquí: si sobran, lo dice la validación del
 * servidor (`decimal:0,2`), que es la que manda.
 *
 * @param {string|number|null|undefined} texto
 * @returns {string}
 */
export function leerPesos(texto) {
    const crudo = String(texto ?? '');
    const [entero, ...resto] = crudo.split(',');
    const pesos = entero.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    const centavos = resto.join('').replace(/\D/g, '');

    if (pesos === '' && centavos === '') {
        return '';
    }

    return centavos === '' ? pesos || '0' : `${pesos || '0'}.${centavos}`;
}

/**
 * Lo que se enseña en la caja al salir de ella.
 *
 * Recibe el número limpio —«380000.5»— o lo que venga del servidor —un
 * decimal como «380000.00»—, y devuelve «$ 380.000,50» o «$ 380.000». Los
 * centavos en cero no se enseñan: en nómina colombiana casi nunca los hay, y
 * «,00» en cada salario es ruido.
 *
 * @param {string|number|null|undefined} valor
 * @returns {string}
 */
export function formatearPesos(valor) {
    const texto = String(valor ?? '').trim();

    if (texto === '') {
        return '';
    }

    const [entero, centavos = ''] = texto.split('.');
    const pesos = (entero.replace(/\D/g, '') || '0').replace(/^0+(?=\d)/, '');
    const conMiles = pesos.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const sinCeros = centavos.replace(/0+$/, '');

    if (sinCeros === '') {
        return `$ ${conMiles}`;
    }

    return `$ ${conMiles},${centavos.padEnd(2, '0')}`;
}
