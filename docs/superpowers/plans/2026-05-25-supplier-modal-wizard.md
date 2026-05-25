# Supplier Select Modal — Two-Step Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the mutually-exclusive tab bar in the supplier select modal with a two-step wizard: Step 1 picks the supply method (Full Order / By Item), Step 2 shows the appropriate supplier selection UI with a "← Change method" back link that clears all selections.

**Architecture:** Single Blade component file change only — no controller, route, or migration work. The HTML is restructured into `#sup-step1` (method cards) and `#sup-step2` (existing form), both children of the modal shell. Two new JS functions (`showStep` / `goBack`) drive transitions; the existing form, panes, and submission logic are untouched.

**Tech Stack:** Laravel Blade, vanilla JS, inline CSS (Tailwind JIT not used — inline styles only per project convention)

**Spec:** `docs/superpowers/specs/2026-05-25-supplier-modal-wizard-design.md`

---

### Task 1: Replace the header tab bar with the mode badge row

**Files:**
- Modify: `resources/views/components/purchase/supplier-select-modal.blade.php`

The current header has a tab bar (`stab-global`, `stab-item`). Remove it. Replace with a hidden `#sup-mode-badge-row` div (shown only in Step 2). Also add `id="sup-modal-subtitle"` to the subtitle element so JS can update it dynamically. Change the initial title to "Request for Quotation" and subtitle to "How do you want to assign suppliers?". Change `padding:20px 24px 0` → `padding:20px 24px 16px` (the `0` bottom padding was a tab-flush hack no longer needed).

- [ ] **Step 1: Replace the header block**

In `resources/views/components/purchase/supplier-select-modal.blade.php`, find and replace this exact block:

```blade
    {{-- Header --}}
    <div style="padding:20px 24px 0;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;">
        <div>
          <div id="sup-modal-title" style="font-size:17px;font-weight:700;color:#0f172a;">Select Suppliers</div>
          <div style="font-size:12px;color:#64748b;margin-top:3px;">Choose who receives the quote request</div>
        </div>
        <button onclick="closeSupplierModal()" aria-label="Close"
          style="width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:18px;color:#64748b;display:flex;align-items:center;justify-content:center;flex-shrink:0;">×</button>
      </div>

      {{-- Tab bar --}}
      <div style="display:flex;gap:0;">
        <button id="stab-global" type="button" onclick="switchSupTab('global')"
          style="padding:10px 20px;font-size:13px;font-weight:700;border:none;background:none;cursor:pointer;color:#2563eb;border-bottom:2px solid #2563eb;margin-bottom:-1px;">
          Full Order
        </button>
        <button id="stab-item" type="button" onclick="switchSupTab('item')"
          style="padding:10px 20px;font-size:13px;font-weight:700;border:none;background:none;cursor:pointer;color:#94a3b8;border-bottom:2px solid transparent;margin-bottom:-1px;">
          By Item
        </button>
      </div>
    </div>
```

Replace with:

```blade
    {{-- Header --}}
    <div style="padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:4px;">
        <div>
          <div id="sup-modal-title" style="font-size:17px;font-weight:700;color:#0f172a;">Request for Quotation</div>
          <div id="sup-modal-subtitle" style="font-size:12px;color:#64748b;margin-top:3px;">How do you want to assign suppliers?</div>
        </div>
        <button onclick="closeSupplierModal()" aria-label="Close"
          style="width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:18px;color:#64748b;display:flex;align-items:center;justify-content:center;flex-shrink:0;">×</button>
      </div>
      {{-- Mode badge row: hidden in Step 1, shown in Step 2 --}}
      <div id="sup-mode-badge-row" style="display:none;align-items:center;gap:8px;margin-top:10px;">
        <button type="button" onclick="goBack()"
          style="display:flex;align-items:center;gap:4px;font-size:12px;color:#2563eb;background:none;border:none;cursor:pointer;padding:0;font-weight:600;">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
          Change method
        </button>
        <span style="color:#cbd5e1;">·</span>
        <span id="sup-mode-badge" style="font-size:11px;padding:2px 9px;border-radius:10px;font-weight:700;"></span>
      </div>
    </div>
```

- [ ] **Step 2: Verify the file saved correctly**

Open `resources/views/components/purchase/supplier-select-modal.blade.php` and confirm:
- No `stab-global` or `stab-item` buttons exist
- `sup-mode-badge-row` div is present with `display:none`
- Title reads "Request for Quotation"
- Subtitle element has `id="sup-modal-subtitle"`

---

### Task 2: Add Step 1 method-selection cards and wrap Step 2

**Files:**
- Modify: `resources/views/components/purchase/supplier-select-modal.blade.php`

Insert `#sup-step1` (method cards + Step 1 Cancel footer) immediately after the header closing `</div>`. Then wrap the existing `<form>` and the existing footer `<div>` inside a new `#sup-step2` div. Step 1 is `display:flex` on load; Step 2 is `display:none`.

- [ ] **Step 1: Insert Step 1 cards immediately after the header**

Find this exact line (the comment that opens the form section):

```blade
    {{-- Single form wrapping both panes --}}
    <form id="sup-form" action="{{ route('purchase.requests.rfq.select', $pr) }}" method="POST"
          style="flex:1;overflow:hidden;display:flex;flex-direction:column;min-height:0;">
```

Replace with:

```blade
    {{-- Step 1: Method selection --}}
    <div id="sup-step1" style="flex:1;display:flex;flex-direction:column;">
      <div style="padding:24px;display:flex;flex-direction:column;gap:12px;flex:1;">

        <button type="button" onclick="showStep('global')"
          style="width:100%;text-align:left;border:2px solid #e2e8f0;border-radius:12px;padding:18px 20px;cursor:pointer;display:flex;align-items:center;gap:16px;background:#fff;transition:border-color .15s,background .15s;"
          onmouseover="this.style.borderColor='#2563eb';this.style.background='#f8fbff'"
          onmouseout="this.style.borderColor='#e2e8f0';this.style.background='#fff'">
          <div style="width:44px;height:44px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">📦</div>
          <div style="flex:1;">
            <div style="font-size:14px;font-weight:700;color:#0f172a;">Full Order</div>
            <div style="font-size:12px;color:#64748b;margin-top:3px;">One set of suppliers handles the entire purchase request</div>
          </div>
          <svg width="16" height="16" fill="none" stroke="#cbd5e1" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/></svg>
        </button>

        <button type="button" onclick="showStep('item')"
          style="width:100%;text-align:left;border:2px solid #e2e8f0;border-radius:12px;padding:18px 20px;cursor:pointer;display:flex;align-items:center;gap:16px;background:#fff;transition:border-color .15s,background .15s;"
          onmouseover="this.style.borderColor='#2563eb';this.style.background='#f8fbff'"
          onmouseout="this.style.borderColor='#e2e8f0';this.style.background='#fff'">
          <div style="width:44px;height:44px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🔀</div>
          <div style="flex:1;">
            <div style="font-size:14px;font-weight:700;color:#0f172a;">By Item</div>
            <div style="font-size:12px;color:#64748b;margin-top:3px;">Assign different suppliers to specific items in this request</div>
          </div>
          <svg width="16" height="16" fill="none" stroke="#cbd5e1" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/></svg>
        </button>

      </div>
      <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;flex-shrink:0;background:#fafafa;">
        <button type="button" onclick="closeSupplierModal()"
          style="padding:8px 18px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#fff;cursor:pointer;">
          Cancel
        </button>
      </div>
    </div>

    {{-- Step 2: Supplier selection --}}
    <div id="sup-step2" style="flex:1;display:none;flex-direction:column;min-height:0;">

    {{-- Single form wrapping both panes --}}
    <form id="sup-form" action="{{ route('purchase.requests.rfq.select', $pr) }}" method="POST"
          style="flex:1;overflow:hidden;display:flex;flex-direction:column;min-height:0;">
```

- [ ] **Step 2: Change `sup-mode` initial value and close Step 2 wrapper**

Find this exact line inside the form (just after `@csrf`):

```blade
      <input type="hidden" name="mode" id="sup-mode" value="global">
```

Replace with:

```blade
      <input type="hidden" name="mode" id="sup-mode" value="">
```

- [ ] **Step 3: Close the Step 2 wrapper after the existing footer**

Find this exact block at the end of the modal shell (the original footer + closing divs):

```blade
    {{-- Footer --}}
    <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#fafafa;">
      <div style="font-size:12px;color:#64748b;" id="sup-footer-msg">0 selected</div>
      <div style="display:flex;gap:10px;">
        <button type="button" onclick="closeSupplierModal()"
          style="padding:8px 18px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#fff;cursor:pointer;">
          Cancel
        </button>
        <button type="button" onclick="submitSuppliers()"
          style="padding:8px 22px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
          Save &amp; Continue →
        </button>
      </div>
    </div>
  </div>
</div>
```

Replace with:

```blade
    {{-- Footer --}}
    <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#fafafa;">
      <div style="font-size:12px;color:#64748b;" id="sup-footer-msg">0 selected</div>
      <div style="display:flex;gap:10px;">
        <button type="button" onclick="closeSupplierModal()"
          style="padding:8px 18px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#fff;cursor:pointer;">
          Cancel
        </button>
        <button type="button" onclick="submitSuppliers()"
          style="padding:8px 22px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
          Save &amp; Continue →
        </button>
      </div>
    </div>

    </div>{{-- /sup-step2 --}}
  </div>
</div>
```

- [ ] **Step 4: Verify structure**

Confirm the file now has this nesting order:
1. `#supplier-modal` → modal shell
2. Header (`#sup-mode-badge-row` inside)
3. `#sup-step1` with two card buttons + Cancel footer
4. `#sup-step2` containing `#sup-form` + the Step 2 footer
5. `#sup-step2` closing tag
6. Modal shell closing tags

---

### Task 3: Update JavaScript — add showStep/goBack, update openSupplierModal, remove switchSupTab

**Files:**
- Modify: `resources/views/components/purchase/supplier-select-modal.blade.php` (script block)

- [ ] **Step 1: Replace `openSupplierModal` and remove `switchSupTab`**

Find this exact block:

```javascript
function openSupplierModal() {
  document.getElementById('supplier-modal').classList.add('open');
  switchSupTab('global');
  document.getElementById('sup-search').focus();
}
function closeSupplierModal() {
  closeAllItemDd();
  document.getElementById('supplier-modal').classList.remove('open');
}
```

Replace with:

```javascript
function openSupplierModal() {
  document.getElementById('supplier-modal').classList.add('open');
  goBack();
}
function closeSupplierModal() {
  closeAllItemDd();
  document.getElementById('supplier-modal').classList.remove('open');
}
function showStep(method) {
  _supTab = method;
  document.getElementById('sup-mode').value = method === 'item' ? 'by_item' : 'global';

  var badge = document.getElementById('sup-mode-badge');
  if (method === 'global') {
    badge.textContent = '📦 Full Order';
    badge.style.background = '#eff6ff';
    badge.style.color = '#2563eb';
  } else {
    badge.textContent = '🔀 By Item';
    badge.style.background = '#f0fdf4';
    badge.style.color = '#15803d';
  }

  document.getElementById('sup-modal-title').textContent = 'Select Suppliers';
  document.getElementById('sup-modal-subtitle').textContent = 'Choose who receives the quote request';
  document.getElementById('sup-mode-badge-row').style.display = 'flex';
  document.getElementById('sup-step1').style.display = 'none';
  document.getElementById('sup-step2').style.display = 'flex';

  if (method === 'global') {
    setTimeout(function(){ document.getElementById('sup-search').focus(); }, 50);
  }
  updateFooter();
}
function goBack() {
  document.querySelectorAll('#sup-form input[type="checkbox"]:not([disabled])').forEach(function(cb) {
    cb.checked = false;
  });
  document.querySelectorAll('[id^="gchan-"]').forEach(function(el) { el.style.display = 'none'; });
  var searchEl = document.getElementById('sup-search');
  if (searchEl) { searchEl.value = ''; filterGlobalSups(''); }
  document.querySelectorAll('[id^="idd-label-"]').forEach(function(el) {
    el.textContent = 'Select suppliers…';
    el.style.color = '#94a3b8';
  });
  var ics = document.getElementById('item-chan-section');
  if (ics) ics.style.display = 'none';
  document.querySelectorAll('[id^="ic-"]').forEach(function(el) { el.style.display = 'none'; });

  document.getElementById('sup-mode').value = '';
  document.getElementById('sup-modal-title').textContent = 'Request for Quotation';
  document.getElementById('sup-modal-subtitle').textContent = 'How do you want to assign suppliers?';
  document.getElementById('sup-mode-badge-row').style.display = 'none';
  document.getElementById('sup-step1').style.display = 'flex';
  document.getElementById('sup-step2').style.display = 'none';
  closeAllItemDd();
}
```

- [ ] **Step 2: Delete the now-dead `switchSupTab` function**

Find and delete this entire block:

```javascript
function switchSupTab(tab) {
  _supTab = tab;
  document.getElementById('sup-mode').value = tab === 'item' ? 'by_item' : 'global';

  document.getElementById('sup-global-pane').style.display = tab === 'global' ? 'flex' : 'none';
  document.getElementById('sup-item-pane').style.display   = tab === 'item'   ? 'flex' : 'none';

  var gBtn = document.getElementById('stab-global');
  var iBtn = document.getElementById('stab-item');
  if (tab === 'global') {
    gBtn.style.color = '#2563eb'; gBtn.style.borderBottomColor = '#2563eb';
    iBtn.style.color = '#94a3b8'; iBtn.style.borderBottomColor = 'transparent';
  } else {
    iBtn.style.color = '#2563eb'; iBtn.style.borderBottomColor = '#2563eb';
    gBtn.style.color = '#94a3b8'; gBtn.style.borderBottomColor = 'transparent';
  }
  updateFooter();
}
```

(Replace with nothing — delete the block entirely.)

- [ ] **Step 3: Verify the script block**

Confirm:
- `openSupplierModal` calls `goBack()` only
- `showStep` and `goBack` are both defined
- `switchSupTab` does not appear anywhere in the file
- `stab-global` and `stab-item` do not appear anywhere in the file

---

### Task 4: Manual verification

**Prerequisites:** Laravel dev server running (`php artisan serve`) and Vite running (`npm run dev`).

- [ ] **Step 1: Open a purchase request that has the supplier modal**

Navigate to `http://localhost:8000/purchase/requests`, open any request that has an "Assign Suppliers" or "Send RFQ" button, and click it.

**Expected:** Modal opens showing Step 1 — two cards ("Full Order", "By Item") and a Cancel button. No tabs visible.

- [ ] **Step 2: Test Full Order flow**

Click the "Full Order" card.

**Expected:**
- Modal transitions to Step 2
- Header shows "← Change method · 📦 Full Order" badge row
- Title changes to "Select Suppliers", subtitle to "Choose who receives the quote request"
- Global supplier list is visible with search input
- Search input is auto-focused

Select one or more suppliers via checkboxes. Confirm channel toggles appear per supplier selected.

- [ ] **Step 3: Test back navigation clears state**

Click "← Change method".

**Expected:**
- Returns to Step 1 (method cards)
- Title resets to "Request for Quotation"
- Mode badge row hidden

Click "Full Order" again.

**Expected:** All checkboxes unchecked, search cleared — fresh state.

- [ ] **Step 4: Test By Item flow**

Click "By Item" card.

**Expected:**
- Step 2 shows the per-item dropdown UI
- Mode badge shows "🔀 By Item" in green

Assign a supplier to one item. Click "← Change method". Click "By Item" again.

**Expected:** Item dropdown labels reset to "Select suppliers…", channel section hidden.

- [ ] **Step 5: Test submission still works**

Go to Full Order, select one supplier, click "Save & Continue →".

**Expected:** Form submits to `purchase.requests.rfq.select` with `mode=global` and the selected `supplier_ids[]`. No JS errors in the browser console.

- [ ] **Step 6: Test Escape key and backdrop click**

Open modal, press Escape.

**Expected:** Modal closes regardless of which step is active.

Open modal, click outside the modal card.

**Expected:** Modal closes.

---

### Task 5: Commit

- [ ] **Step 1: Stage the file**

```bash
git add resources/views/components/purchase/supplier-select-modal.blade.php
```

- [ ] **Step 2: Commit**

```bash
git commit -m "feat: replace supplier modal tabs with two-step wizard"
```
