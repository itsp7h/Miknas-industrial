<?php

namespace App\Support;

use App\Http\Controllers\Api\Purchase\PurchaseRequestController;

/**
 * A quote line priced in the supplier's unit rather than ours: "4 BAG at
 * 12.000, 1 BAG = 25 PCS". What compares, orders and stocks is the line in
 * our unit, so this turns one into the other in the one place both the portal
 * and the quotes workspace's correction use.
 */
final class SupplierUnit
{
    /** Every unit a supplier may quote in: our own list, so each one maps. */
    public static function units(): array
    {
        return PurchaseRequestController::UNITS;
    }

    /** Whether `$unit` is a unit a line asked for in `$ours` may be quoted in. */
    public static function allowed(?string $unit, ?string $ours): bool
    {
        return $unit === $ours || in_array($unit, self::units(), true);
    }

    /** Whether a line in `$unit` needs converting to `$ours` at all. */
    public static function differs(?string $unit, ?string $ours): bool
    {
        return filled($unit) && filled($ours) && $unit !== $ours;
    }

    /**
     * The line in our unit. `$factor` is how many of ours one of theirs holds.
     *
     * @return array{quantity: float, unit_price: float, total_price: float}
     */
    public static function figures(float $supplierQuantity, float $factor, float $supplierPrice): array
    {
        return [
            'quantity' => round($supplierQuantity * $factor, 3),
            'unit_price' => round($supplierPrice / $factor, 3),
            // From the supplier's own figures, so the total is what they quoted
            // rather than a per-PCS price rounded and multiplied back up.
            'total_price' => round($supplierQuantity * $supplierPrice, 3),
        ];
    }

    /**
     * The line before anyone has said what one of theirs holds in ours, which
     * is settled on the GRN. It is costed as if what they quote covers what
     * we asked for: their total over our quantity, so suppliers still rank on
     * the quotes page. The total is theirs exactly.
     *
     * @return array{quantity: float, unit_price: float, total_price: float}
     */
    public static function pendingFigures(float $supplierQuantity, float $supplierPrice, float $ourQuantity): array
    {
        $total = round($supplierQuantity * $supplierPrice, 3);

        return [
            'quantity' => $ourQuantity,
            'unit_price' => $ourQuantity > 0 ? round($total / $ourQuantity, 3) : 0.0,
            'total_price' => $total,
        ];
    }

    /** "1 BAG = 25 PCS", with no trailing zeros on the factor. */
    public static function describe(string $unit, float $factor, ?string $ours): string
    {
        return "1 {$unit} = ".self::number($factor).($ours ? " {$ours}" : '');
    }

    /** 25.0000 -> "25", 2.5000 -> "2.5". */
    public static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
