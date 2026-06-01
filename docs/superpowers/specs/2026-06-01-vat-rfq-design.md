# VAT Settings + RFQ Per-Item Vatable Checkbox — Design Spec

**Date:** 2026-06-01
**Status:** Approved

---

## Overview

Two connected features:

1. **Global VAT setting** — Admin sets a VAT rate (%) once in Settings. Stored in the existing `settings` key/value table as `vat_rate`.
2. **Per-item vatable checkbox on RFQ portal** — When a supplier fills in their quote, each item row has a "VAT?" checkbox. Ticking it applies the global VAT rate to that item's total. The footer shows a live breakdown: Subtotal → VAT → Grand Total. Everything is stored against the quote on submission.

---

## 1. VAT Global Setting

### Route & Controller
- `GET  settings/vat` → `VatSettingController@index` — renders the VAT settings page
- `POST settings/vat` → `VatSettingController@update` — saves the rate via AJAX, returns JSON

### Storage
- Uses the existing `Setting` model (`settings` table, key/value pairs)
- Key: `vat_rate`, value: decimal string e.g. `"10"` for 10%
- Default when not set: `0`

### View: `resources/views/settings/vat.blade.php`
- Extends `layouts.app`
- Single card with a number input: "VAT Rate (%)" — accepts decimals (e.g. 5, 10, 14.5)
- Save button submits via `fetch()` AJAX (no page reload, per project rules)
- Shows current saved value on load
- Success/error feedback via `showToast()`

### Sidebar
- Add "VAT Settings" link under the `@role('Admin')` System section in `layouts/app.blade.php`
- Active state: `request()->routeIs('settings.vat')`

---

## 2. RFQ Portal — Per-Item Vatable Checkbox

### Database
- New migration: add `is_vatable` boolean (default `false`) to `supplier_quote_items` table
- `SupplierQuoteItem::$fillable` gains `is_vatable`

### Controller: `RfqPortalController`

**`show()` method:**
- Load VAT rate: `$vatRate = (float) Setting::get('vat_rate', 0)`
- Pass `$vatRate` to `rfq.show` view

**`submit()` method:**
- Add validation rule: `'items.*.is_vatable' => ['nullable', 'boolean']`
- When creating each `SupplierQuoteItem`, set `is_vatable` from submitted checkbox value
- Calculate VAT: sum of `(unit_price × quantity)` for vatable items × `vatRate / 100`
- Store `total_amount` on `SupplierQuote` as the VAT-inclusive grand total

### View: `resources/views/rfq/show.blade.php`

**Table header** — add "VAT?" column:
```
# | Description | Qty | Unit | Unit Price (BD) | VAT? | Total (BD)
```

**Each item row** — add checkbox cell:
```html
<input type="checkbox" name="items[N][is_vatable]" value="1"
       onchange="calcRow(N, qty)" style="accent-color:#2563eb;width:16px;height:16px;">
```

**Footer** — replace single "Grand Total" row with three rows:
```
Subtotal (before VAT):   BD  XXX.XXX
VAT (10%):               BD  XXX.XXX   ← hidden when vatRate is 0
Grand Total:             BD  XXX.XXX
```

**JavaScript `calcRow()` update:**
- Each row calculates its own total (unit_price × qty), unchanged
- `recalcTotals()` called after every row change:
  - Sums all row totals → subtotal
  - Sums row totals for checked (vatable) rows × `vatRate/100` → vatAmount
  - grand = subtotal + vatAmount
  - Updates subtotal, VAT, and grand total display elements
- VAT rate injected as a JS variable: `var _vatRate = {{ $vatRate }};`
- VAT row is hidden when `_vatRate === 0`

### Mobile stacked layout
On mobile (≤640px), the table renders as cards. The VAT checkbox appears as a labelled row inside each card.

---

## 3. Data Flow Summary

```
Admin sets vat_rate = 10 in settings/vat
         ↓
RfqPortalController::show() reads vat_rate, passes to view
         ↓
Supplier ticks VAT on items 1 and 3
         ↓
JS: subtotal = sum(all items), vat = sum(vatable items) × 0.10, grand = subtotal + vat
         ↓
Supplier submits form
         ↓
RfqPortalController::submit():
  - stores is_vatable per SupplierQuoteItem
  - total_amount on SupplierQuote = subtotal + vat (VAT-inclusive)
```

---

## 4. Files Touched

| File | Change |
|------|--------|
| `routes/web.php` | Add `GET/POST settings/vat` routes |
| `app/Http/Controllers/Settings/VatSettingController.php` | New controller |
| `resources/views/settings/vat.blade.php` | New view |
| `resources/views/layouts/app.blade.php` | Add sidebar link |
| `database/migrations/..._add_is_vatable_to_supplier_quote_items.php` | New migration |
| `app/Models/SupplierQuoteItem.php` | Add `is_vatable` to `$fillable` |
| `app/Http/Controllers/Purchase/RfqPortalController.php` | Load VAT rate, store `is_vatable`, calc VAT total |
| `resources/views/rfq/show.blade.php` | Add checkbox column, VAT footer breakdown, JS update |

---

## 5. Out of Scope

- Showing VAT breakdown on the internal quote comparison view (can be added later)
- Per-supplier or per-item VAT rates (single global rate only)
- VAT registration numbers
