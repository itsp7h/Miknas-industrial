/** BD to three decimals, matching the Blade workspace throughout. */
export const bd = (value) =>
    `BD ${Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })}`;

/** The Blade page trimmed trailing zeros off the VAT rate: 10.00 → 10. */
export const rate = (value) => String(Number(value ?? 0)).replace(/\.0+$/, '');
