import { describe, expect, it } from 'vitest';
import { formatearPesos, leerPesos } from './pesos.js';

describe('leerPesos', () => {
    it('lee el punto como separador de miles, que es el error que corrige', () => {
        expect(leerPesos('380.000')).toBe('380000');
        expect(leerPesos('1.750.905')).toBe('1750905');
    });

    it('lee la coma como separador de centavos', () => {
        expect(leerPesos('380.000,50')).toBe('380000.50');
    });

    it('descarta el signo de pesos y los espacios', () => {
        expect(leerPesos('$ 380.000')).toBe('380000');
    });

    it('deja pasar el número ya limpio', () => {
        expect(leerPesos('380000')).toBe('380000');
    });

    it('deja vacío lo que está vacío, para que el servidor diga que falta', () => {
        expect(leerPesos('')).toBe('');
        expect(leerPesos('$ ')).toBe('');
    });

    it('conserva el cero, que es una bonificación válida', () => {
        expect(leerPesos('0')).toBe('0');
    });

    it('no recorta centavos de más: eso lo rechaza el servidor', () => {
        expect(leerPesos('10,505')).toBe('10.505');
    });
});

describe('formatearPesos', () => {
    it('pone los miles con punto y el signo de pesos', () => {
        expect(formatearPesos('380000')).toBe('$ 380.000');
        expect(formatearPesos('1750905')).toBe('$ 1.750.905');
    });

    it('no enseña centavos en cero, como llegan del servidor', () => {
        expect(formatearPesos('380000.00')).toBe('$ 380.000');
    });

    it('enseña los centavos con coma y dos cifras', () => {
        expect(formatearPesos('380000.5')).toBe('$ 380.000,50');
    });

    it('enseña el cero', () => {
        expect(formatearPesos('0')).toBe('$ 0');
    });

    it('deja vacío lo que está vacío', () => {
        expect(formatearPesos('')).toBe('');
        expect(formatearPesos(null)).toBe('');
    });

    it('lo que formatea se vuelve a leer igual', () => {
        for (const valor of ['380000', '1750905', '380000.50', '0']) {
            expect(leerPesos(formatearPesos(valor))).toBe(valor);
        }
    });
});
