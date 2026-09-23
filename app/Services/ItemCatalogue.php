<?php

namespace App\Services;

use App\Events\ItemSaved;
use App\Models\Item;

/**
 * The item master, as the MPR form meets it.
 *
 * A request names materials by description. Most are things the company
 * already stocks, so the form completes them and takes the unit from the
 * catalogue; the rest are new, and a material someone has actually requested
 * belongs in the catalogue rather than being typed afresh every time.
 */
class ItemCatalogue
{
    /** What an item gets when the request did not say. */
    public const DEFAULT_UNIT = 'PCS';

    /** Items are matched by name, ignoring case and surrounding space. */
    public function findByName(?string $name): ?Item
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return Item::whereRaw('LOWER(item_name) = ?', [mb_strtolower($name)])->first();
    }

    /**
     * The catalogue entry for this description, created if there is none.
     *
     * New entries are raw materials: an MPR asks for what goes into the work,
     * never for the company's own finished product. They carry only what was
     * typed — a name and a unit — so whoever owns the item master can see what
     * still needs pricing and a minimum level.
     *
     * A row left without a unit still becomes an item, and items must have one.
     * It gets PCS, the neutral count, for whoever owns the master to correct —
     * rather than the request failing over a field the form does not require.
     */
    public function findOrCreateByName(?string $name, ?string $unit = null): ?Item
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        if ($existing = $this->findByName($name)) {
            return $existing;
        }

        $item = Item::create([
            'item_code' => $this->nextCode(),
            'item_name' => $name,
            'category' => 'raw_material',
            'unit_of_measure' => trim((string) $unit) ?: self::DEFAULT_UNIT,
            'minimum_stock_level' => 0,
            'cost_price' => 0,
            'is_active' => true,
        ]);

        // The inventory pages are live, so a material added from an MPR shows
        // up on them without a reload, like one added on the page itself.
        event(new ItemSaved($item));

        return $item;
    }

    /** Matches ItemController's own codes, so the two sources cannot collide. */
    private function nextCode(): string
    {
        return 'ITEM-'.str_pad((string) (Item::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
