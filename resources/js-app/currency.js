/**
 * How money is written, in one place.
 *
 * Every money figure in the app goes through `money()`. Quantities do not —
 * they are counts of bags and kilos, not amounts, and prefixing them with a
 * currency would be wrong.
 *
 * `decimals` is per currency because it genuinely differs: the Gulf dinars run
 * to three (a Bahraini fils is a thousandth), most currencies to two. Rendering
 * BHD at two decimals was quietly losing precision — an item costing 0.006
 * printed as 0.01.
 *
 * Multiple currencies later: `money()` already takes a code, so a row carrying
 * its own currency becomes `money(row.amount, row.currency_code)` and nothing
 * else moves. What is missing for that is conversion, not formatting.
 */

export const CURRENCIES = {
    BHD: { symbol: 'BD', decimals: 3 },
    KWD: { symbol: 'KD', decimals: 3 },
    OMR: { symbol: 'OMR', decimals: 3 },
    SAR: { symbol: 'SR', decimals: 2 },
    AED: { symbol: 'AED', decimals: 2 },
    QAR: { symbol: 'QR', decimals: 2 },
    USD: { symbol: '$', decimals: 2 },
    EUR: { symbol: '€', decimals: 2 },
    GBP: { symbol: '£', decimals: 2 },
};

export const DEFAULT_CURRENCY = 'BHD';

// The one the app is configured for. Set once at boot from the shell, so every
// helper below can stay a plain function rather than a hook — there are money
// figures in table column definitions, which are not components.
let active = DEFAULT_CURRENCY;

export function setActiveCurrency(code) {
    if (code && CURRENCIES[code]) active = code;
}

export function activeCurrency() {
    return active;
}

export function currencySymbol(code = active) {
    return (CURRENCIES[code] ?? CURRENCIES[DEFAULT_CURRENCY]).symbol;
}

/** An amount with its symbol: "BD 1,234.500". */
export function money(value, code = active) {
    const currency = CURRENCIES[code] ?? CURRENCIES[DEFAULT_CURRENCY];

    return `${currency.symbol} ${amount(value, code)}`;
}

/** The figure alone, at the currency's precision — for a column already headed BD. */
export function amount(value, code = active) {
    const currency = CURRENCIES[code] ?? CURRENCIES[DEFAULT_CURRENCY];

    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: currency.decimals,
        maximumFractionDigits: currency.decimals,
    });
}

/** A count of things — bags, kilos, pieces. Never a currency. */
export function qty(value) {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
