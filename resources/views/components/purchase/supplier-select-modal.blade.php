@props([
    'pr',
    'suppliers',
    'selectedIds' => [],
])

{{-- ============================================================
     SELECT SUPPLIERS MODAL
     ============================================================ --}}
<div id="supplier-modal" class="pipe-modal" role="dialog" aria-modal="true" aria-labelledby="sup-modal-title">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:680px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,.3);overflow:hidden;">

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
      @csrf
      <input type="hidden" name="mode" id="sup-mode" value="">

      {{-- ── GLOBAL PANE ── --}}
      <div id="sup-global-pane" style="flex:1;overflow-y:auto;overscroll-behavior:contain;display:flex;flex-direction:column;">
        {{-- Search --}}
        <div style="padding:14px 24px 10px;flex-shrink:0;">
          <div style="position:relative;">
            <svg style="position:absolute;left:11px;top:50%;transform:translateY(-50%);pointer-events:none;"
                 width="14" height="14" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35"/>
            </svg>
            <input id="sup-search" type="text" placeholder="Search suppliers…" oninput="filterGlobalSups(this.value)"
              style="width:100%;box-sizing:border-box;padding:9px 12px 9px 34px;border:1px solid #e2e8f0;border-radius:9px;font-size:13px;outline:none;"
              onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
          </div>
        </div>
        <div id="sup-list" style="flex:1;">
          @forelse($suppliers as $sup)
          @php $alreadyAdded = in_array($sup->id, $selectedIds); @endphp
          <div class="g-sup-item" data-name="{{ strtolower($sup->name) }}"
            style="padding:11px 24px;display:flex;align-items:center;gap:14px;border-bottom:1px solid #f8fafc;transition:background .1s;
                   {{ $alreadyAdded ? 'opacity:.45;pointer-events:none;' : '' }}">
            <input type="checkbox" name="supplier_ids[]" value="{{ $sup->id }}" id="gsup-{{ $sup->id }}"
              {{ $alreadyAdded ? 'disabled checked' : '' }}
              onchange="toggleGlobalChan({{ $sup->id }}, this.checked); updateGlobalCount();"
              style="width:17px;height:17px;accent-color:#2563eb;cursor:pointer;flex-shrink:0;">
            <label for="gsup-{{ $sup->id }}" style="flex:1;cursor:{{ $alreadyAdded ? 'default' : 'pointer' }};">
              <div style="font-size:13px;font-weight:600;color:#0f172a;">
                {{ $sup->name }}
                @if($alreadyAdded)
                  <span style="font-size:10px;background:#dcfce7;color:#15803d;padding:1px 6px;border-radius:10px;margin-left:6px;font-weight:700;">Added</span>
                @endif
              </div>
              @if($sup->email || $sup->phone)
              <div style="font-size:11px;color:#94a3b8;margin-top:1px;">
                {{ collect([$sup->email, $sup->phone])->filter()->implode(' · ') }}
              </div>
              @endif
            </label>
            @if(!$alreadyAdded)
            <div id="gchan-{{ $sup->id }}" style="display:none;flex-shrink:0;">
              <input type="hidden" name="channel_{{ $sup->id }}" id="gchan-val-{{ $sup->id }}" value="email">
              <div style="display:flex;border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
                <span onclick="setGChan({{ $sup->id }},'email')" id="gce-{{ $sup->id }}"
                  style="padding:5px 9px;font-size:10px;font-weight:700;cursor:pointer;background:#eff6ff;color:#2563eb;">Email</span>
                <span onclick="setGChan({{ $sup->id }},'whatsapp')" id="gcw-{{ $sup->id }}"
                  style="padding:5px 9px;font-size:10px;font-weight:700;cursor:pointer;background:#fff;color:#94a3b8;border-left:1px solid #e2e8f0;">WA</span>
                <span onclick="setGChan({{ $sup->id }},'both')" id="gcb-{{ $sup->id }}"
                  style="padding:5px 9px;font-size:10px;font-weight:700;cursor:pointer;background:#fff;color:#94a3b8;border-left:1px solid #e2e8f0;">Both</span>
              </div>
            </div>
            @endif
          </div>
          @empty
          <div style="padding:40px;text-align:center;color:#94a3b8;font-size:13px;">No active suppliers found.</div>
          @endforelse
          <div id="no-sup-msg" style="display:none;padding:30px;text-align:center;color:#94a3b8;font-size:13px;">No suppliers match your search.</div>
        </div>
      </div>

      {{-- ── BY ITEM PANE ── --}}
      <div id="sup-item-pane" style="flex:1;overflow-y:auto;overscroll-behavior:contain;display:none;flex-direction:column;">
        @if($pr->items->isEmpty())
          <div style="padding:40px;text-align:center;color:#94a3b8;font-size:13px;">This request has no items yet.</div>
        @else
          {{-- Column headers --}}
          <div style="display:grid;grid-template-columns:1fr 175px 120px;gap:12px;padding:9px 24px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-shrink:0;">
            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;">Item</div>
            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;">Assign Suppliers</div>
            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;">Channel</div>
          </div>

          {{-- Item rows --}}
          @foreach($pr->items as $item)
          <div style="display:grid;grid-template-columns:1fr 175px 120px;gap:12px;align-items:center;padding:13px 24px;border-bottom:1px solid #f8fafc;">
            <div>
              <div style="font-size:13px;font-weight:600;color:#0f172a;line-height:1.3;">{{ $item->description }}</div>
              <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                Qty: {{ rtrim(rtrim(number_format($item->quantity_required,2),'0'),'.') }}{{ $item->unit ? ' '.$item->unit : '' }}
              </div>
            </div>
            <div>
              <button type="button" id="idd-btn-{{ $item->id }}"
                onclick="toggleItemDd(event,{{ $item->id }})"
                data-itemname="{{ $item->description }}"
                data-itemqty="{{ rtrim(rtrim(number_format($item->quantity_required,2),'0'),'.') }}{{ $item->unit ? ' '.$item->unit : '' }}"
                style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;
                       display:flex;align-items:center;justify-content:space-between;gap:6px;text-align:left;transition:border .15s;">
                <span id="idd-label-{{ $item->id }}" style="font-size:12px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;">
                  Select suppliers…
                </span>
                <svg width="11" height="11" fill="none" stroke="#94a3b8" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
            </div>
            <div id="ichan-item-btn-{{ $item->id }}"
              style="display:flex;border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden;opacity:.35;pointer-events:none;">
              <span onclick="setItemChan({{ $item->id }},'email')" id="ichan-ie-{{ $item->id }}"
                style="flex:1;padding:7px 4px;font-size:10px;font-weight:700;cursor:pointer;text-align:center;background:#eff6ff;color:#2563eb;">Email</span>
              <span onclick="setItemChan({{ $item->id }},'whatsapp')" id="ichan-iw-{{ $item->id }}"
                style="flex:1;padding:7px 4px;font-size:10px;font-weight:700;cursor:pointer;text-align:center;background:#fff;color:#94a3b8;border-left:1.5px solid #e2e8f0;">WA</span>
              <span onclick="setItemChan({{ $item->id }},'both')" id="ichan-ib-{{ $item->id }}"
                style="flex:1;padding:7px 4px;font-size:10px;font-weight:700;cursor:pointer;text-align:center;background:#fff;color:#94a3b8;border-left:1.5px solid #e2e8f0;">Both</span>
            </div>
          </div>
          @endforeach

          {{-- Hidden channel inputs — read by backend as channel_{sup_id} --}}
          <div style="display:none;">
            @foreach($suppliers as $sup)
              <input type="hidden" name="channel_{{ $sup->id }}" id="ichan-val-{{ $sup->id }}" value="email">
            @endforeach
          </div>
        @endif
      </div>

      {{-- Dropdown panels: fixed-positioned, one per item — must be inside form so checkboxes are submitted --}}
      @foreach($pr->items as $item)
      <div id="idd-{{ $item->id }}"
         style="display:none;position:fixed;z-index:99999;background:#fff;border:1.5px solid #e2e8f0;
                border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.18);overflow:hidden;min-width:240px;">
        <div style="padding:10px 10px 6px;">
          <input type="text" placeholder="Search suppliers…"
            oninput="filterItemDd({{ $item->id }}, this.value)"
            style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;outline:none;"
            onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
        </div>
        <div style="max-height:210px;overflow-y:auto;padding-bottom:4px;">
          @forelse($suppliers as $sup)
          @php $alreadyAdded = in_array($sup->id, $selectedIds); @endphp
          <label class="idd-row-{{ $item->id }}" data-name="{{ strtolower($sup->name) }}"
            style="display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:{{ $alreadyAdded ? 'default' : 'pointer' }};
                   {{ $alreadyAdded ? 'opacity:.45;' : '' }}"
            onmouseover="{{ $alreadyAdded ? '' : "this.style.background='#f8fafc'" }}"
            onmouseout="this.style.background='transparent'">
            <input type="checkbox" name="item_suppliers[{{ $item->id }}][]" value="{{ $sup->id }}"
              data-supname="{{ $sup->name }}"
              id="isup-{{ $item->id }}-{{ $sup->id }}"
              {{ $alreadyAdded ? 'disabled checked' : '' }}
              onchange="onItemSupChange({{ $item->id }}, {{ $sup->id }})"
              style="width:15px;height:15px;accent-color:#2563eb;cursor:{{ $alreadyAdded ? 'default' : 'pointer' }};flex-shrink:0;">
            <div style="min-width:0;">
              <div style="font-size:12px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                {{ $sup->name }}
                @if($alreadyAdded)
                  <span style="font-size:9px;background:#dcfce7;color:#15803d;padding:1px 5px;border-radius:8px;margin-left:4px;">Added</span>
                @endif
              </div>
              @if($sup->email)
              <div style="font-size:10px;color:#94a3b8;">{{ $sup->email }}</div>
              @endif
            </div>
          </label>
          @empty
          <div style="padding:16px;text-align:center;color:#94a3b8;font-size:12px;">No suppliers found.</div>
          @endforelse
          <div id="idd-no-{{ $item->id }}" style="display:none;padding:14px;text-align:center;color:#94a3b8;font-size:12px;">No results</div>
        </div>
      </div>
      @endforeach
    </form>

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

    {{-- Step 3: Links --}}
    <div id="sup-step3" style="flex:1;display:none;flex-direction:column;min-height:0;">
      <div id="sup-summary-body" style="flex:1;overflow-y:auto;overscroll-behavior:contain;padding:20px 24px;">
      </div>
      <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#fafafa;">
        <div style="font-size:12px;color:#64748b;" id="sup-link-count"></div>
        <button type="button" onclick="doneWithLinks()"
          style="padding:8px 22px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
          Done ✓
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// ---- Supplier modal ----
var _supTab = 'global';

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

  document.getElementById('sup-global-pane').style.display = method === 'global' ? 'flex' : 'none';
  document.getElementById('sup-item-pane').style.display   = method === 'item'   ? 'flex' : 'none';
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
  document.querySelectorAll('[id^="ichan-item-btn-"]').forEach(function(el) {
    el.style.opacity = '.35'; el.style.pointerEvents = 'none';
  });
  document.querySelectorAll('[id^="ichan-ie-"]').forEach(function(el) { el.style.background='#eff6ff'; el.style.color='#2563eb'; });
  document.querySelectorAll('[id^="ichan-iw-"],[id^="ichan-ib-"]').forEach(function(el) { el.style.background='#fff'; el.style.color='#94a3b8'; });
  _itemChan = {};

  document.getElementById('sup-mode').value = '';
  document.getElementById('sup-modal-title').textContent = 'Request for Quotation';
  document.getElementById('sup-modal-subtitle').textContent = 'How do you want to assign suppliers?';
  document.getElementById('sup-mode-badge-row').style.display = 'none';
  document.getElementById('sup-step1').style.display = 'flex';
  document.getElementById('sup-step2').style.display = 'none';
  document.getElementById('sup-step3').style.display = 'none';
  closeAllItemDd();
}
document.getElementById('supplier-modal').addEventListener('click', function(e) {
  if (e.target === this) closeSupplierModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    if (typeof closeSignModal === 'function') closeSignModal();
    closeSupplierModal();
  }
});

// ── Global tab helpers ──
function filterGlobalSups(q) {
  q = q.toLowerCase().trim();
  var items = document.querySelectorAll('.g-sup-item');
  var visible = 0;
  items.forEach(function(item) {
    var match = !q || item.dataset.name.indexOf(q) !== -1;
    item.style.display = match ? 'flex' : 'none';
    if (match) visible++;
  });
  document.getElementById('no-sup-msg').style.display = (visible === 0 && q) ? 'block' : 'none';
}

function toggleGlobalChan(id, checked) {
  var el = document.getElementById('gchan-' + id);
  if (el) el.style.display = checked ? 'block' : 'none';
  updateFooter();
}

var chanStyles = {
  email:    { bg:'#eff6ff', fg:'#2563eb' },
  whatsapp: { bg:'#f0fdf4', fg:'#15803d' },
  both:     { bg:'#fef3c7', fg:'#92400e' },
};
function applyBtnStyles(prefix, id, val) {
  [['email','e'],['whatsapp','w'],['both','b']].forEach(function(p) {
    var el = document.getElementById(prefix + p[1] + '-' + id);
    if (!el) return;
    if (p[0] === val) { el.style.background = chanStyles[val].bg; el.style.color = chanStyles[val].fg; }
    else              { el.style.background = '#fff'; el.style.color = '#94a3b8'; }
  });
}
function setGChan(id, val) {
  document.getElementById('gchan-val-' + id).value = val;
  applyBtnStyles('gc', id, val);
}

function updateGlobalCount() { updateFooter(); }

// ── By-item tab helpers ──
var _openItemDd = null;
var _itemChan = {};

function setItemChan(itemId, val) {
  _itemChan[itemId] = val;
  ['e','w','b'].forEach(function(k) {
    var map = { e:'email', w:'whatsapp', b:'both' };
    var el = document.getElementById('ichan-i' + k + '-' + itemId);
    if (!el) return;
    if (map[k] === val) { el.style.background = chanStyles[val].bg; el.style.color = chanStyles[val].fg; }
    else { el.style.background = '#fff'; el.style.color = '#94a3b8'; }
  });
  document.querySelectorAll('input[name="item_suppliers[' + itemId + '][]"]:checked:not([disabled])').forEach(function(cb) {
    var inp = document.getElementById('ichan-val-' + cb.value);
    if (inp) inp.value = val;
  });
}

function toggleItemDd(event, itemId) {
  event.stopPropagation();
  var dd = document.getElementById('idd-' + itemId);
  if (!dd) return;

  if (_openItemDd === itemId) {
    dd.style.display = 'none';
    _openItemDd = null;
    return;
  }
  closeAllItemDd();

  var btn = document.getElementById('idd-btn-' + itemId);
  var rect = btn.getBoundingClientRect();
  var ddWidth = 260;
  var left = Math.min(rect.left, window.innerWidth - ddWidth - 8);
  dd.style.left    = left + 'px';
  dd.style.top     = (rect.bottom + 4) + 'px';
  dd.style.width   = ddWidth + 'px';
  dd.style.display = 'block';
  _openItemDd = itemId;

  var inp = dd.querySelector('input[type="text"]');
  if (inp) setTimeout(function(){ inp.focus(); }, 30);
}

function closeAllItemDd() {
  if (_openItemDd !== null) {
    var dd = document.getElementById('idd-' + _openItemDd);
    if (dd) dd.style.display = 'none';
    _openItemDd = null;
  }
}

function filterItemDd(itemId, q) {
  q = q.toLowerCase().trim();
  var rows = document.querySelectorAll('.idd-row-' + itemId);
  var visible = 0;
  rows.forEach(function(row) {
    var match = !q || (row.dataset.name || '').indexOf(q) !== -1;
    row.style.display = match ? 'flex' : 'none';
    if (match) visible++;
  });
  var noEl = document.getElementById('idd-no-' + itemId);
  if (noEl) noEl.style.display = (visible === 0 && q) ? 'block' : 'none';
}

function updateItemDdLabel(itemId) {
  var checked = document.querySelectorAll('[id^="isup-' + itemId + '-"]:checked:not([disabled])');
  var label = document.getElementById('idd-label-' + itemId);
  if (!label) return;
  if (checked.length === 0) {
    label.textContent = 'Select suppliers…';
    label.style.color = '#94a3b8';
  } else {
    var names = Array.from(checked).map(function(c) { return c.dataset.supname || c.value; });
    label.textContent = names.join(', ');
    label.style.color = '#0f172a';
  }
}

function onItemSupChange(itemId, supId) {
  updateItemDdLabel(itemId);

  // Activate/deactivate this item's channel picker
  var anyCheckedForItem = document.querySelectorAll('input[name="item_suppliers[' + itemId + '][]"]:checked:not([disabled])').length > 0;
  var picker = document.getElementById('ichan-item-btn-' + itemId);
  if (picker) {
    picker.style.opacity = anyCheckedForItem ? '1' : '.35';
    picker.style.pointerEvents = anyCheckedForItem ? 'auto' : 'none';
  }

  // Apply this item's current channel to the newly checked supplier
  var cb = document.getElementById('isup-' + itemId + '-' + supId);
  if (cb && cb.checked) {
    var chan = _itemChan[itemId] || 'email';
    var inp = document.getElementById('ichan-val-' + supId);
    if (inp) inp.value = chan;
  }

  updateFooter();
}

function setIChan(id, val) {
  document.getElementById('ichan-val-' + id).value = val;
  applyBtnStyles('ic', id, val);
}

document.addEventListener('click', function(e) {
  if (_openItemDd === null) return;
  var dd  = document.getElementById('idd-' + _openItemDd);
  var btn = document.getElementById('idd-btn-' + _openItemDd);
  if (dd && !dd.contains(e.target) && btn && !btn.contains(e.target)) {
    closeAllItemDd();
  }
});

function updateFooter() {
  var msg = document.getElementById('sup-footer-msg');
  if (_supTab === 'global') {
    var n = document.querySelectorAll('#sup-list input[type="checkbox"]:checked:not([disabled])').length;
    msg.textContent = n + ' supplier' + (n === 1 ? '' : 's') + ' selected';
  } else {
    var checked = document.querySelectorAll('input[name^="item_suppliers["]:checked:not([disabled])');
    var supIds = new Set();
    checked.forEach(function(c) { supIds.add(c.value); });
    var n = supIds.size;
    msg.textContent = n + ' supplier' + (n === 1 ? '' : 's') + ' assigned';
  }
}

var _rfqRedirect = null;

function submitSuppliers() {
  if (_supTab === 'global') {
    var checked = document.querySelectorAll('#sup-list input[type="checkbox"]:checked:not([disabled])');
    if (checked.length === 0) { showToast('Please select at least one supplier.', 'warn'); return; }
  } else {
    var checked = document.querySelectorAll('input[name^="item_suppliers["]:checked:not([disabled])');
    if (checked.length === 0) { showToast('Please assign at least one supplier to an item.', 'warn'); return; }
  }

  // Show step 3 with loading state immediately
  document.getElementById('sup-summary-body').innerHTML =
    '<div style="text-align:center;padding:40px;color:#64748b;font-size:13px;">Generating links…</div>';
  document.getElementById('sup-modal-title').textContent    = 'Quote Links';
  document.getElementById('sup-modal-subtitle').textContent = 'One-time-use links for each supplier';
  document.getElementById('sup-step2').style.display = 'none';
  document.getElementById('sup-step3').style.display = 'flex';

  var form = document.getElementById('sup-form');
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  fetch(form.action, {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
    body: new FormData(form),
  }).then(function(r) {
    return r.json().then(function(body) {
      if (!r.ok) return Promise.reject(body);
      return body;
    });
  }).then(function(data) {
    _rfqRedirect = data.redirect || null;
    showLinks(data.invitations || []);
  }).catch(function(err) {
    document.getElementById('sup-step3').style.display = 'none';
    document.getElementById('sup-step2').style.display = 'flex';
    document.getElementById('sup-modal-title').textContent    = 'Select Suppliers';
    document.getElementById('sup-modal-subtitle').textContent = 'Choose who receives the quote request';
    showToast((err && err.message) || 'Something went wrong.', 'error');
  });
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

var _chanBadge = {
  email:    '<span style="background:#eff6ff;color:#2563eb;padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700;flex-shrink:0;">Email</span>',
  whatsapp: '<span style="background:#f0fdf4;color:#15803d;padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700;flex-shrink:0;">WhatsApp</span>',
  both:     '<span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700;flex-shrink:0;">Email + WA</span>',
};

function showLinks(invitations) {
  var html = '';
  if (!invitations || invitations.length === 0) {
    html = '<div style="padding:30px;text-align:center;color:#94a3b8;font-size:13px;">No new invitations were created.</div>';
  } else {
    html += '<div style="font-size:12px;color:#64748b;margin-bottom:16px;">Each link is private to the supplier and valid until submitted or expired.</div>';
    invitations.forEach(function(inv, i) {
      var badge = _chanBadge[inv.channel] || '';
      html += '<div style="margin-bottom:10px;border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden;">';
      html += '<div style="padding:9px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:8px;">';
      html += '<span style="font-size:13px;font-weight:700;color:#0f172a;">' + escHtml(inv.supplier_name) + '</span>';
      html += badge;
      html += '</div>';
      html += '<div style="padding:10px 14px;display:flex;align-items:center;gap:8px;">';
      html += '<a href="' + escHtml(inv.url) + '" target="_blank"'
            + ' style="flex:1;display:flex;align-items:center;gap:8px;padding:9px 14px;'
            + 'background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:8px;text-decoration:none;'
            + 'color:#1d4ed8;font-size:12px;font-weight:600;transition:background .15s;overflow:hidden;"'
            + ' onmouseover="this.style.background=\'#dbeafe\'" onmouseout="this.style.background=\'#eff6ff\'">'
            + '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;">'
            + '<path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>'
            + '</svg>'
            + '<span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Open Quote Link</span>'
            + '</a>';
      html += '<button type="button" data-idx="' + i + '" data-url="' + escHtml(inv.url) + '" onclick="copyLink(this)"'
            + ' style="flex-shrink:0;padding:9px 14px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">Copy Link</button>';
      html += '</div>';
      html += '</div>';
    });
  }

  document.getElementById('sup-summary-body').innerHTML = html;
  document.getElementById('sup-modal-title').textContent    = 'Quote Links Ready';
  document.getElementById('sup-modal-subtitle').textContent = 'Share with suppliers via their preferred channel';
  var countEl = document.getElementById('sup-link-count');
  if (countEl) countEl.textContent = invitations.length + ' link' + (invitations.length === 1 ? '' : 's') + ' generated';
}

function copyLink(btn) {
  var url = btn.dataset.url;
  if (!url) return;
  navigator.clipboard.writeText(url).then(function() {
    btn.textContent = 'Copied!';
    btn.style.background = '#16a34a';
    setTimeout(function() { btn.textContent = 'Copy'; btn.style.background = '#2563eb'; }, 2000);
  }).catch(function() {
    // Fallback for older browsers
    var ta = document.createElement('textarea');
    ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); btn.textContent = 'Copied!'; btn.style.background = '#16a34a';
      setTimeout(function() { btn.textContent = 'Copy'; btn.style.background = '#2563eb'; }, 2000);
    } catch(e) { showToast('Could not copy — please copy manually.', 'warn'); }
    document.body.removeChild(ta);
  });
}

function doneWithLinks() {
  if (_rfqRedirect) { window.location.href = _rfqRedirect; }
  else { window.location.reload(); }
}
</script>
