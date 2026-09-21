import { describe, it, expect, afterEach } from 'vitest';
import { money, amount, qty, setActiveCurrency, activeCurrency, currencySymbol } from './currency';

afterEach(() => setActiveCurrency('BHD'));

describe('currency', () => {
    it('writes Bahraini Dinar as BD at three decimals', () => {
        expect(money(1234.5)).toBe('BD 1,234.500');
        // Two decimals was losing a fils: 0.006 printed as 0.01.
        expect(money(0.006)).toBe('BD 0.006');
        expect(money(null)).toBe('BD 0.000');
    });

    it('follows the configured currency, with its own precision', () => {
        setActiveCurrency('USD');
        expect(money(1234.5)).toBe('$ 1,234.50');
        expect(activeCurrency()).toBe('USD');
        expect(currencySymbol()).toBe('$');
    });

    it('ignores a currency it does not know rather than losing the symbol', () => {
        setActiveCurrency('ZZZ');
        expect(activeCurrency()).toBe('BHD');
        expect(money(1, 'ZZZ')).toBe('BD 1.000');
    });

    /** What multiple currencies will need: a per-row override, already working. */
    it('takes a currency per call, not just the configured one', () => {
        expect(money(1234.5, 'KWD')).toBe('KD 1,234.500');
        expect(money(1234.5, 'SAR')).toBe('SR 1,234.50');
    });

    it('gives the bare figure when the column is already headed with a currency', () => {
        expect(amount(1234.5)).toBe('1,234.500');
    });

    /** Quantities are bags and kilos, not amounts; they carry no symbol. */
    it('keeps quantities free of any currency', () => {
        expect(qty(1234.5)).toBe('1,234.50');
        expect(qty(null)).toBe('0.00');
    });
});
