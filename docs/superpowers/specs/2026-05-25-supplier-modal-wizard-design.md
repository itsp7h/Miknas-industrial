# Supplier Select Modal — Two-Step Wizard Redesign

**Date:** 2026-05-25
**Status:** Approved

---

## Problem

The current `supplier-select-modal` has two tabs — "Full Order" and "By Item" — that are mutually exclusive: selecting suppliers in one mode makes the other irrelevant. But the tab UI implies they are equal parallel options, causing confusion. There is no enforcement preventing a user from switching tabs mid-flow with selections already made.

---

## Solution

Replace the tab bar with a two-step wizard flow:

1. **Step 1 — Choose Method:** The modal opens on a method-selection screen with two large clickable cards. No tabs. No supplier list yet.
2. **Step 2 — Select Suppliers:** Clicking a card transitions into that mode's supplier selection UI (the existing Full Order list or By Item per-item dropdowns, structurally unchanged).

Going back always clears selections (fresh start — no preserved state per mode).

---

## Step 1: Method Selection Screen

**Header:**
- Title: "Request for Quotation"
- Subtitle: "How do you want to assign suppliers?"
- Close (×) button

**Body — two clickable cards (full-width, stacked vertically):**

| Card | Icon | Title | Subtitle |
|---|---|---|---|
| Full Order | 📦 | Full Order | One set of suppliers handles the entire purchase request |
| By Item | 🔀 | By Item | Assign different suppliers to specific items in this request |

Each card has a right-pointing chevron (›). On hover: border turns blue, background tints to `#f8fbff`. Clicking immediately transitions to Step 2 — no confirmation needed.

**Footer:** Cancel button only (no Save & Continue on Step 1).

---

## Step 2: Supplier Selection Screen

**Header (replaces tab bar entirely):**
- Top row: "← Change method" link (blue, with left chevron) · mode badge (e.g. `📦 Full Order` in blue pill or `🔀 By Item` in green pill)
- Below: Title "Select Suppliers", subtitle "Choose who receives the quote request"

Clicking "← Change method" returns to Step 1 and **clears all current selections** (checkboxes unchecked, channel inputs reset).

**Body:** Identical to the existing Full Order pane or By Item pane depending on the chosen mode. No structural changes to the selection UI itself.

**Footer:** Same as current — count label left, Cancel + "Save & Continue →" right.

---

## State & Form Submission

- The hidden `<input name="mode">` continues to drive the server-side logic (`global` vs `by_item`).
- On "← Change method": all checkboxes are unchecked programmatically, channel inputs reset to `email`, `mode` input cleared, then transition back to Step 1.
- Form submission logic (`submitSuppliers()`) is unchanged.

---

## What Does NOT Change

- The supplier list rendering (search, checkboxes, channel toggles, "Added" badges)
- The By Item per-item dropdown panels
- The per-supplier channel section in By Item mode
- Form action, method, CSRF, field names
- `openSupplierModal()` / `closeSupplierModal()` entry points used by the parent view
- The `pipe-modal` CSS class and backdrop behaviour

---

## Files Affected

| File | Change |
|---|---|
| `resources/views/components/purchase/supplier-select-modal.blade.php` | Replace tab bar with Step 1 method-selection HTML; add mode badge + back link to Step 2 header; add `showStep()` / `goBack()` JS functions |

No controller, route, or migration changes required.
