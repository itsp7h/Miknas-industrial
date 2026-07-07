# Quote Workspace Redesign

## Context

A previous change merged the separate `/quotes` (supplier list) and `/compare`
(item comparison) pages into one `purchase/quotes/workspace.blade.php` view
with three tabs: "By Supplier", "Comparison & Award", "Awarded Suppliers".
Feedback: the tab split still feels like two old pages stitched together —
"By Supplier" duplicates data already visible per-item in "Comparison & Award".

## Design

**Tabs:** Two, down from three.
1. **Comparison & Award** (default/only real working tab)
2. **Awarded Suppliers** (unchanged — grouped-by-supplier award summary)

"By Supplier" is removed entirely.

**Comparison & Award — row-level redesign:**

Each supplier's row inside an item's comparison table gains an inline detail
line directly under the supplier name, carrying what used to live in the
By Supplier cards:

```
Al Reem Co.
  14 days · Net 30 · bulk discount available
```

- Lead time and payment terms join with " · " separators; omit a segment if
  the quote doesn't have that field.
- Quote notes render as full text, wrapping naturally (no truncation/tooltip).
- This detail line sits above the existing "adjusted description" line
  (supplier_description quirk) if present, in the same cell.
- The per-quote "item breakdown" table from the old By Supplier tab is
  dropped without replacement — the comparison grid already shows every
  item × every supplier, which is a strict superset of that breakdown.

**Routing:** `purchase.requests.quotes` and `purchase.requests.compare`
continue to resolve to the same view. Since there is only one real working
tab now, drop the `initialTab` distinction — both routes always land on
"Comparison & Award". The controller's two thin methods
(`index`/`compare`) stay as-is for route-name compatibility but no longer
need to pass a tab hint.

**Awarded Suppliers tab:** No changes to its content or rendering logic.

**Empty state:** Unchanged — "No quotes yet" card when `$quotes->isEmpty()`.

## Out of scope

- No changes to the award/unaward AJAX endpoints or controller logic beyond
  removing the now-unused `initialTab` param.
- No changes to `pipeline/show.blade.php` links — both existing links
  ("View Quotes", "Compare & Award") keep working, just land on the same tab.
